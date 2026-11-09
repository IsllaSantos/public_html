<?php

require_once('config/auth.php');
require_once('config/database.php');

verificarPerfil('secretaria');

$nomeSecretaria = $_SESSION['nome'];
$id_clinica = $_SESSION['id_clinica'];

/* ABA ATIVA */

$abaInicial = $_GET['aba'] ?? 'dashboard';
if (!in_array($abaInicial, ['dashboard', 'medicos', 'pacientes', 'agendamentos', 'calendario'], true)) {
    $abaInicial = 'dashboard';
}

/* Dados só são buscados para a aba ativa (menos consultas ao banco remoto) */
$consultasHoje = $pendentes = $aguardandoAtendimento = $pacientesAtivos = 0;
$listaConsultas = $listaMedicos = $listaMedicosSelect = [];
$listaPacientes = $listaPacientesSelect = $listaAgendamentos = [];

$mensagemSucesso = '';
if (isset($_SESSION['mensagem_sucesso'])) {
    $mensagemSucesso = $_SESSION['mensagem_sucesso'];
    unset($_SESSION['mensagem_sucesso']);
}


/* ==============================
   NOME DA CLÍNICA
============================== */

$stmt = $conn->prepare("SELECT nome FROM clinica WHERE id_clinica = ?");
$stmt->bind_param("i", $id_clinica);
$stmt->execute();
$resClinica = $stmt->get_result()->fetch_assoc();
$nomeClinica = $resClinica['nome'] ?? 'Clínica';


/* ==============================
   DASHBOARD — MÉTRICAS
============================== */

if ($abaInicial === 'dashboard') {

$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM usuarios WHERE id_clinica = ? AND perfil = 'paciente' AND status = 'ativo'");
$stmt->bind_param("i", $id_clinica);
$stmt->execute();
$pacientesAtivos = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);

/* Uma única consulta para hoje / pendentes / aguardando (menos idas ao banco remoto) */
$stmt = $conn->prepare("
    SELECT
        COALESCE(SUM(c.data_consulta = CURDATE() AND c.status <> 'cancelada'), 0) AS hoje,
        COALESCE(SUM(c.status = 'agendada'), 0) AS pendentes,
        COALESCE(SUM(c.status IN ('aguardando_atendimento', 'aguardando_atendimen', 'aguardando')), 0) AS aguardando
    FROM consulta c
    INNER JOIN usuarios p ON c.id_paciente = p.id_usuario
    LEFT JOIN usuarios med ON c.id_medico = med.id_usuario
    WHERE (p.id_clinica = ? OR med.id_clinica = ?)
");
$stmt->bind_param("ii", $id_clinica, $id_clinica);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$consultasHoje = (int)($row['hoje'] ?? 0);
$pendentes = (int)($row['pendentes'] ?? 0);
$aguardandoAtendimento = (int)($row['aguardando'] ?? 0);




/* CONSULTAS PENDENTES PARA CONFIRMAÇÃO / PRÓXIMAS */

$consultasPendentes = $conn->prepare("
    SELECT
        c.id_consulta,
        c.id_paciente,
        c.id_medico,
        c.data_consulta,
        c.hora_consulta,
        c.status,
        c.motivo_consulta,
        pac.nome AS nome_paciente,
        med.nome AS nome_medico,
        med.especialidade AS especialidade_medico
    FROM consulta c
    INNER JOIN usuarios pac ON c.id_paciente = pac.id_usuario
    INNER JOIN usuarios med ON c.id_medico = med.id_usuario
    WHERE (pac.id_clinica = ? OR med.id_clinica = ?)
    AND (c.data_consulta >= CURDATE() OR c.status IN ('aguardando_atendimento', 'aguardando_atendimen', 'aguardando', 'em_atendimento'))
    ORDER BY
        CASE WHEN c.status IN ('aguardando_atendimento', 'aguardando_atendimen', 'aguardando') THEN 0
             WHEN c.status = 'em_atendimento' THEN 1
             WHEN c.status = 'agendada' THEN 2
             ELSE 3 END ASC,
        c.data_consulta ASC,
        c.hora_consulta ASC
    LIMIT 30
");
$consultasPendentes->bind_param("ii", $id_clinica, $id_clinica);
$consultasPendentes->execute();
$listaConsultas = $consultasPendentes->get_result()->fetch_all(MYSQLI_ASSOC);

}




/* ==============================
   ANIVERSARIANTES DO MÊS (dashboard)
============================== */

$aniversariantes = [];
if ($abaInicial === 'dashboard') {
    $stmtAn = @$conn->prepare("
        SELECT id_usuario, nome, telefone, data_nascimento
        FROM usuarios
        WHERE id_clinica = ? AND perfil = 'paciente' AND status = 'ativo'
        AND data_nascimento IS NOT NULL
        AND MONTH(data_nascimento) = MONTH(CURDATE())
        ORDER BY DAY(data_nascimento), nome
    ");
    if ($stmtAn) {
        $stmtAn->bind_param("i", $id_clinica);
        $stmtAn->execute();
        $aniversariantes = $stmtAn->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtAn->close();
    }
}


/* ==============================
   CALENDÁRIO SEMANAL (por médico)
============================== */

$calMedicos = [];
$calConsultas = [];
$calDisponiveis = [];
$calMedicoSel = intval($_GET['medico'] ?? 0);
$calSemana = intval($_GET['semana'] ?? 0);
$calInicio = new DateTime('monday this week');
$calInicio->modify(($calSemana >= 0 ? '+' : '') . $calSemana . ' week');
$calFim = (clone $calInicio)->modify('+6 day');

if ($abaInicial === 'calendario') {
    $stmtCm = $conn->prepare("SELECT id_usuario, nome, especialidade, duracao_consulta FROM usuarios WHERE id_clinica = ? AND perfil = 'medico' AND status = 'ativo' ORDER BY nome");
    $stmtCm->bind_param("i", $id_clinica);
    $stmtCm->execute();
    $calMedicos = $stmtCm->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtCm->close();

    if ($calMedicoSel <= 0 && !empty($calMedicos)) {
        $calMedicoSel = intval($calMedicos[0]['id_usuario']);
    }

    if ($calMedicoSel > 0) {
        $ini = $calInicio->format('Y-m-d');
        $fim = $calFim->format('Y-m-d');
        $stmtCc = $conn->prepare("
            SELECT c.id_consulta, c.data_consulta, c.hora_consulta, c.status, p.nome AS paciente
            FROM consulta c
            INNER JOIN usuarios p ON p.id_usuario = c.id_paciente
            INNER JOIN usuarios m ON m.id_usuario = c.id_medico
            WHERE c.id_medico = ? AND m.id_clinica = ?
            AND c.data_consulta BETWEEN ? AND ?
            AND c.status <> 'cancelada'
            ORDER BY c.data_consulta, c.hora_consulta
        ");
        $stmtCc->bind_param("iiss", $calMedicoSel, $id_clinica, $ini, $fim);
        $stmtCc->execute();
        foreach ($stmtCc->get_result()->fetch_all(MYSQLI_ASSOC) as $cc) {
            $calConsultas[$cc['data_consulta']][] = $cc;
        }
        $stmtCc->close();

        /* Disponibilidade cadastrada pelo médico na semana (horários livres = blocos - consultas) */
        $duracaoCal = 30;
        foreach ($calMedicos as $cmx) {
            if ((int)$cmx['id_usuario'] === $calMedicoSel) {
                $duracaoCal = intval($cmx['duracao_consulta'] ?? 30) ?: 30;
            }
        }
        $stmtAg = $conn->prepare("
            SELECT data_disp, hora_inicio, hora_fim
            FROM agenda_medico
            WHERE id_medico = ? AND ativo = 1 AND data_disp BETWEEN ? AND ?
            ORDER BY data_disp, hora_inicio
        ");
        $stmtAg->bind_param("iss", $calMedicoSel, $ini, $fim);
        $stmtAg->execute();
        foreach ($stmtAg->get_result()->fetch_all(MYSQLI_ASSOC) as $bl) {
            $d = $bl['data_disp'];
            $ocup = [];
            foreach ($calConsultas[$d] ?? [] as $cc) {
                $ocup[] = substr($cc['hora_consulta'], 0, 5);
            }
            $atual = strtotime($d . ' ' . $bl['hora_inicio']);
            $fimB  = strtotime($d . ' ' . $bl['hora_fim']);
            $livres = 0;
            while ($atual + ($duracaoCal * 60) <= $fimB) {
                if (!in_array(date('H:i', $atual), $ocup, true)) $livres++;
                $atual += $duracaoCal * 60;
            }
            $calDisponiveis[$d][] = [
                'inicio' => substr($bl['hora_inicio'], 0, 5),
                'fim'    => substr($bl['hora_fim'], 0, 5),
                'livres' => $livres,
            ];
        }
        $stmtAg->close();
    }
}

/* ==============================
   MÉDICOS DA CLÍNICA
============================== */

if ($abaInicial === 'medicos' || $abaInicial === 'agendamentos') {

$medicos = $conn->prepare("
    SELECT
        id_usuario, nome, email, perfil, status, id_clinica,
        cpf, rg, data_nascimento, sexo, telefone, celular,
        crm, uf_crm, especialidade,
        horario_inicio, horario_fim, duracao_consulta,
        data_cadastro
    FROM usuarios
    WHERE id_clinica = ?
    AND perfil = 'medico'
    ORDER BY nome
");
$medicos->bind_param("i", $id_clinica);
$medicos->execute();
$listaMedicos = $medicos->get_result()->fetch_all(MYSQLI_ASSOC);

/* MÉDICOS PARA SELECT (filtrado em PHP, sem nova consulta) */
$listaMedicosSelect = array_values(array_filter($listaMedicos, function ($m) {
    return $m['status'] === 'ativo';
}));

}


/* ==============================
   PACIENTES DA CLÍNICA
============================== */

if ($abaInicial === 'pacientes' || $abaInicial === 'agendamentos') {

$pacientes = $conn->prepare("
    SELECT
        id_usuario, nome, email, perfil, status, id_clinica,
        cpf, rg, data_nascimento, sexo, telefone, celular,
        cep, endereco, numero, complemento, bairro, cidade, estado,
        convenio, numero_carteirinha,
        contato_emergencia_nome, contato_emergencia_telefone,
        data_cadastro
    FROM usuarios
    WHERE id_clinica = ?
    AND perfil = 'paciente'
    ORDER BY nome
");
$pacientes->bind_param("i", $id_clinica);
$pacientes->execute();
$listaPacientes = $pacientes->get_result()->fetch_all(MYSQLI_ASSOC);

/* PACIENTES PARA SELECT / CONTAGEM (em PHP, sem nova consulta) */
$listaPacientesSelect = array_values(array_filter($listaPacientes, function ($p) {
    return $p['status'] === 'ativo';
}));

}


/* ==============================
   AGENDAMENTOS
============================== */

if ($abaInicial === 'agendamentos') {

$agendamentos = $conn->prepare("
    SELECT
        c.id_consulta,
        c.id_paciente,
        c.id_medico,
        c.data_consulta,
        c.hora_consulta,
        c.tipo_consulta,
        c.status,
        c.motivo_consulta,
        pac.nome AS nome_paciente,
        med.nome AS nome_medico,
        med.especialidade AS especialidade_medico
    FROM consulta c
    INNER JOIN usuarios pac ON c.id_paciente = pac.id_usuario
    INNER JOIN usuarios med ON c.id_medico = med.id_usuario
    WHERE (pac.id_clinica = ? OR med.id_clinica = ?)
    ORDER BY c.data_consulta DESC, c.hora_consulta DESC
    LIMIT 300
");
$agendamentos->bind_param("ii", $id_clinica, $id_clinica);
$agendamentos->execute();
$listaAgendamentos = $agendamentos->get_result()->fetch_all(MYSQLI_ASSOC);

}


/* ESTADOS */

$estados = [
    'AC','AL','AP','AM','BA','CE','DF','ES','GO','MA',
    'MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN',
    'RS','RO','RR','SC','SP','SE','TO'
];


/* ==============================
   ESPECIALIDADES
============================== */

$listaEspecialidades = [];
if ($abaInicial === 'medicos' || $abaInicial === 'agendamentos') {
    $resEspec = $conn->query("
        SELECT id_especialidade, nome
        FROM especialidades
        WHERE status = 'ativa'
        ORDER BY nome
    ");
    if ($resEspec) {
        while ($esp = $resEspec->fetch_assoc()) {
            $listaEspecialidades[] = $esp;
        }
    }
}

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Painel Secretaria - NyesClinic</title>

<link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>">

</head>

<body>

<header>

    <div class="logo">
        <a href="secretaria.php">
            Nyes<span>Clinic</span>
        </a>
    </div>

    <nav>

        <button onclick="showSecretariaTab('dashboard')" class="<?= $abaInicial === 'dashboard' ? 'active' : '' ?>">
            Dashboard
        </button>

        <button onclick="showSecretariaTab('medicos')" class="<?= $abaInicial === 'medicos' ? 'active' : '' ?>">
            Médicos
        </button>

        <button onclick="showSecretariaTab('pacientes')" class="<?= $abaInicial === 'pacientes' ? 'active' : '' ?>">
            Pacientes
        </button>

        <button onclick="showSecretariaTab('agendamentos')" class="<?= $abaInicial === 'agendamentos' ? 'active' : '' ?>">
            Agendamentos
        </button>

        <button onclick="showSecretariaTab('calendario')" class="<?= $abaInicial === 'calendario' ? 'active' : '' ?>">
            Calendário
        </button>

        <button onclick="window.location.href='logout.php'">
            Sair
        </button>

    </nav>

</header>


<div class="container">

    <div class="top-bar">

        <h2>
            Bem-vinda,

            <span style="color:var(--gold);">
                <?= htmlspecialchars($nomeSecretaria) ?>
            </span>
        </h2>

        <p style="color:#888;margin-top:5px;">
            <?= htmlspecialchars($nomeClinica) ?>
        </p>

    </div>

    <?php if (!empty($mensagemSucesso)): ?>
        <?php
            $isErro = (
                stripos($mensagemSucesso, 'não') !== false ||
                stripos($mensagemSucesso, 'erro') !== false ||
                stripos($mensagemSucesso, 'inválid') !== false ||
                stripos($mensagemSucesso, 'sem permissão') !== false ||
                stripos($mensagemSucesso, 'já cadastrado') !== false ||
                stripos($mensagemSucesso, 'obrigat') !== false
            );
        ?>
        <div class="card" style="margin-bottom:18px;border-left:4px solid <?= $isErro ? 'var(--danger,#dc2626)' : 'var(--success,#16a34a)' ?>;padding:12px 18px;">
            <p style="color:<?= $isErro ? 'var(--danger,#dc2626)' : 'var(--success,#16a34a)' ?>;font-weight:600;margin:0;display:flex;align-items:center;gap:8px;">
                <span><?= $isErro ? '⚠' : '✓' ?></span>
                <span><?= htmlspecialchars($mensagemSucesso) ?></span>
            </p>
        </div>
    <?php endif; ?>

</div>



<!-- ==============================
     DASHBOARD
============================== -->

<section
    id="dashboard"
    class="secretaria-tab <?= $abaInicial === 'dashboard' ? 'active' : '' ?>"
    style="<?= $abaInicial === 'dashboard' ? '' : 'display:none;' ?>"
>

    <div class="container">


        <!-- CARDS -->

        <div class="stats-row">

            <div class="card">
                <h3>Consultas Hoje</h3>
                <div style="font-size:2rem;font-weight:bold;color:var(--gold);">
                    <?= $consultasHoje ?>
                </div>
            </div>

            <div class="card">
                <h3>Pendentes</h3>
                <div style="font-size:2rem;font-weight:bold;color:orange;">
                    <?= $pendentes ?>
                </div>
            </div>

            <div class="card">
                <h3>Pacientes Ativos</h3>
                <div style="font-size:2rem;font-weight:bold;color:green;">
                    <?= $pacientesAtivos ?>
                </div>
            </div>

            <div class="card">
                <h3>Aguardando Atendimento</h3>
                <div style="font-size:2rem;font-weight:bold;color:#2980b9;">
                    <?= $aguardandoAtendimento ?>
                </div>
            </div>

        </div>


        <!-- CONFIRMAÇÃO DE CONSULTAS -->

        <div class="card">

            <div class="top-bar" style="margin-bottom:20px;">
                <h3>Próximas Consultas</h3>
                <input type="text" id="buscaProximas" placeholder="🔍 Filtrar paciente, médico..." onkeyup="filtrarTabelaSecretaria('buscaProximas', 'tbodyProximas')" class="field" style="width:260px;min-height:36px;padding:6px 12px;font-size:0.86rem;">
            </div>

            <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Horário</th>
                        <th>Paciente</th>
                        <th>Médico</th>
                        <th>Especialidade</th>
                        <th>Motivo</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>

                <tbody id="tbodyProximas">

                <?php foreach ($listaConsultas as $c): ?>

                    <tr>
                        <td><?= date('d/m/Y', strtotime($c['data_consulta'])) ?></td>
                        <td><?= substr($c['hora_consulta'], 0, 5) ?></td>
                        <td>
                            <a href="javascript:void(0)" class="user-name-link" onclick="visualizarDadosUsuario(<?= (int)$c['id_paciente'] ?>, 'secretaria')" title="Clique para visualizar os dados do paciente">
                                <?= htmlspecialchars($c['nome_paciente']) ?>
                            </a>
                        </td>
                        <td>
                            <a href="javascript:void(0)" class="user-name-link" onclick="visualizarDadosUsuario(<?= (int)$c['id_medico'] ?>, 'secretaria')" title="Clique para visualizar os dados do médico">
                                <?= htmlspecialchars($c['nome_medico']) ?>
                            </a>
                        </td>
                        <td><?= htmlspecialchars($c['especialidade_medico'] ?? '') ?></td>
                        <td><?= htmlspecialchars($c['motivo_consulta'] ?? '—') ?></td>

                        <td>
                            <?php if ($c['status'] === 'aguardando_atendimento' || $c['status'] === 'aguardando_atendimen' || $c['status'] === 'aguardando'): ?>
                                <span class="status status-aguardando">Aguardando Atendimento</span>
                            <?php elseif ($c['status'] === 'em_atendimento'): ?>
                                <span class="status status-em-atendimento">Em Atendimento</span>
                            <?php elseif ($c['status'] === 'finalizada'): ?>
                                <span class="status confirmed">Finalizada</span>
                            <?php elseif ($c['status'] === 'agendada'): ?>
                                <span class="status pending">Agendada</span>
                            <?php elseif ($c['status'] === 'cancelada'): ?>
                                <span class="status status-cancelada">Cancelada</span>
                            <?php else: ?>
                                <span class="status"><?= htmlspecialchars($c['status']) ?></span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?php if ($c['status'] === 'agendada'): ?>
                                <button type="button" class="menu-btn" style="background:#10b981;" onclick="alterarStatusConsulta(<?= $c['id_consulta'] ?>, 'aguardando_atendimento', this, 'dashboard')">
                                    ✓ Confirmar Presença
                                </button>
                                <button type="button" class="menu-btn" style="background:#ef4444;margin-left:5px;" onclick="alterarStatusConsulta(<?= $c['id_consulta'] ?>, 'cancelada', this, 'dashboard')">
                                    ✗ Cancelar
                                </button>
                            <?php else: ?>
                                <span style="color:#888;font-size:0.85rem;">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>

                <?php endforeach; ?>

                </tbody>
            </table>
            </div>

        </div>

        <!-- ANIVERSARIANTES DO MÊS -->
        <div class="card" style="margin-top:20px;">
            <h3>🎂 Aniversariantes do Mês</h3>
            <?php if (empty($aniversariantes)): ?>
                <p style="color:#888;margin-top:10px;">Nenhum paciente faz aniversário neste mês.</p>
            <?php else: ?>
                <table>
                    <thead><tr><th>Dia</th><th>Paciente</th><th>Telefone</th></tr></thead>
                    <tbody>
                    <?php foreach ($aniversariantes as $an): ?>
                        <tr>
                            <td><?= date('d/m', strtotime($an['data_nascimento'])) ?></td>
                            <td>
                                <a href="javascript:void(0)" class="user-name-link" onclick="visualizarDadosUsuario(<?= (int)$an['id_usuario'] ?>, 'secretaria')" title="Clique para visualizar os dados">
                                    <?= htmlspecialchars($an['nome']) ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($an['telefone'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

    </div>

</section>



<!-- ==============================
     CALENDÁRIO SEMANAL
============================== -->

<section
    id="calendario"
    class="secretaria-tab <?= $abaInicial === 'calendario' ? 'active' : '' ?>"
    style="<?= $abaInicial === 'calendario' ? '' : 'display:none;' ?>"
>
    <div class="container">
        <div class="card">

            <div class="top-bar" style="flex-wrap:wrap;gap:10px;">
                <h3>Calendário Semanal</h3>

                <form method="GET" action="secretaria.php" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                    <input type="hidden" name="aba" value="calendario">
                    <input type="hidden" name="semana" value="<?= $calSemana ?>">
                    <select name="medico" class="field" onchange="this.form.submit()" style="min-width:220px;">
                        <?php foreach ($calMedicos as $cm): ?>
                            <option value="<?= (int)$cm['id_usuario'] ?>" <?= $calMedicoSel === (int)$cm['id_usuario'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cm['nome'] . (!empty($cm['especialidade']) ? ' — ' . $cm['especialidade'] : '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <a class="menu-btn" style="text-decoration:none;" href="secretaria.php?aba=calendario&medico=<?= $calMedicoSel ?>&semana=<?= $calSemana - 1 ?>">← Anterior</a>
                    <a class="menu-btn" style="text-decoration:none;" href="secretaria.php?aba=calendario&medico=<?= $calMedicoSel ?>&semana=0">Hoje</a>
                    <a class="menu-btn" style="text-decoration:none;" href="secretaria.php?aba=calendario&medico=<?= $calMedicoSel ?>&semana=<?= $calSemana + 1 ?>">Próxima →</a>
                </form>
            </div>

            <p style="color:#888;margin:8px 0 15px;">
                <?= $calInicio->format('d/m/Y') ?> a <?= $calFim->format('d/m/Y') ?>
            </p>

            <?php if (empty($calMedicos)): ?>
                <p style="color:#888;">Nenhum médico ativo nesta clínica.</p>
            <?php else: ?>
                <?php $diasSemana = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom']; ?>
                <div style="display:grid;grid-template-columns:repeat(7,minmax(120px,1fr));gap:10px;overflow-x:auto;">
                    <?php for ($i = 0; $i < 7; $i++):
                        $dia = (clone $calInicio)->modify("+$i day");
                        $chave = $dia->format('Y-m-d');
                        $ehHoje = ($chave === date('Y-m-d'));
                    ?>
                        <div style="border:1px solid <?= $ehHoje ? 'var(--gold)' : '#ddd' ?>;border-radius:10px;padding:8px;min-height:140px;">
                            <strong><?= $diasSemana[$i] ?> <?= $dia->format('d/m') ?></strong>
                            <?php foreach ($calDisponiveis[$chave] ?? [] as $bl):
                                $temLivres = ((int)$bl['livres'] > 0);
                            ?>
                                <div style="margin-top:6px;padding:5px 7px;border-radius:6px;font-size:.78rem;background:<?= $temLivres ? 'rgba(39,174,96,.12)' : 'rgba(239,68,68,.12)' ?>;border:1px dashed <?= $temLivres ? '#27ae60' : '#ef4444' ?>;">
                                    <?= $temLivres ? '🟢 Disponível' : '🔴 Ocupado' ?><br>
                                    <strong><?= $bl['inicio'] ?>–<?= $bl['fim'] ?></strong>
                                    <div style="color:<?= $temLivres ? '#555' : '#b91c1c' ?>;"><?= (int)$bl['livres'] ?> horário(s) livre(s)</div>
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($calConsultas[$chave]) && empty($calDisponiveis[$chave])): ?>
                                <div style="color:#aaa;font-size:.8rem;margin-top:8px;">Sem agenda</div>
                            <?php else: foreach ($calConsultas[$chave] ?? [] as $cc): ?>
                                <div style="margin-top:6px;padding:5px 7px;border-radius:6px;font-size:.8rem;background:<?= $cc['status'] === 'finalizada' ? 'rgba(39,174,96,.15)' : 'rgba(41,128,185,.15)' ?>;">
                                    <strong><?= substr($cc['hora_consulta'], 0, 5) ?></strong>
                                    <?= htmlspecialchars($cc['paciente']) ?>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>

        </div>
    </div>
</section>



<!-- ==============================
     MÉDICOS
============================== -->

<section
    id="medicos"
    class="secretaria-tab <?= $abaInicial === 'medicos' ? 'active' : '' ?>"
    style="<?= $abaInicial === 'medicos' ? '' : 'display:none;' ?>"
>

    <div class="container">


        <!-- CADASTRAR MÉDICO (Oculto por padrão; abre ao clicar em "Novo Cadastro") -->

        <div class="card" id="cardNovoMedico" style="display:none;">

            <div class="top-bar">
                <h3>Novo Médico</h3>
                <button type="button" class="btn btn-secondary" onclick="alternarCadastroSecretaria('medicos', false)">
                    ← Minimizar / Ver Lista
                </button>
            </div>

            <form
                action="api/secretaria_cadastro_medico.php"
                method="POST"
                autocomplete="off"
            >

                <h4 style="margin-bottom:15px;color:var(--gold);">Dados de Acesso</h4>
                <div class="grid">

                    <div class="field-group">
                        <label>Nome Completo *</label>
                        <input type="text" name="nome" class="field" required>
                    </div>

                    <div class="field-group">
                        <label>E-mail *</label>
                        <input type="email" name="email" class="field" autocomplete="off" required>
                    </div>

                    <div class="field-group">
                        <label>Senha *</label>
                        <input type="password" name="senha" class="field" autocomplete="new-password" required>
                    </div>

                </div>

                <br>
                <h4 style="margin-bottom:15px;color:var(--gold);">Dados Pessoais</h4>
                <div class="grid">

                    <div class="field-group">
                        <label>CPF</label>
                        <input type="text" name="cpf" class="field mask-cpf" maxlength="14" placeholder="XXX.XXX.XXX-XX">
                    </div>

                    <div class="field-group">
                        <label>RG</label>
                        <input type="text" name="rg" class="field" maxlength="20">
                    </div>

                    <div class="field-group">
                        <label>Data de Nascimento</label>
                        <input type="date" name="data_nascimento" class="field">
                    </div>

                    <div class="field-group">
                        <label>Sexo</label>
                        <select name="sexo" class="field">
                            <option value="">Selecione</option>
                            <option value="M">Masculino</option>
                            <option value="F">Feminino</option>
                        </select>
                    </div>

                    <div class="field-group">
                        <label>Telefone</label>
                        <input type="text" name="telefone" class="field mask-telefone" placeholder="(XX) XXXXX-XXXX">
                    </div>

                    <div class="field-group">
                        <label>Celular</label>
                        <input type="text" name="celular" class="field mask-telefone" placeholder="(XX) XXXXX-XXXX">
                    </div>

                </div>

                <br>
                <h4 style="margin-bottom:15px;color:var(--gold);">Dados Profissionais (Médico)</h4>
                <div class="grid">

                    <div class="field-group">
                        <label>CRM *</label>
                        <input type="text" name="crm" class="field" maxlength="20" required>
                    </div>

                    <div class="field-group">
                        <label>UF do CRM</label>
                        <select name="uf_crm" class="field">
                            <option value="">Selecione</option>
                            <?php foreach ($estados as $uf): ?>
                                <option value="<?= $uf ?>"><?= $uf ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field-group">
                        <label>Especialidade *</label>
                        <select name="especialidade" class="field" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($listaEspecialidades as $esp): ?>
                                <option value="<?= htmlspecialchars($esp['nome']) ?>">
                                    <?= htmlspecialchars($esp['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field-group">
                        <label>Horário Início</label>
                        <input type="time" name="horario_inicio" class="field">
                    </div>

                    <div class="field-group">
                        <label>Horário Fim</label>
                        <input type="time" name="horario_fim" class="field">
                    </div>

                    <div class="field-group">
                        <label>Duração Consulta (min)</label>
                        <input type="number" name="duracao_consulta" class="field" value="30" min="5" max="120">
                    </div>

                </div>

                <br>

                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                    <button type="submit" class="confirm-btn">
                        Cadastrar Médico
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="alternarCadastroSecretaria('medicos', false)">
                        Cancelar
                    </button>
                </div>

            </form>

        </div>


        <!-- LISTA -->

        <div class="card" id="cardListaMedicos">

            <div class="top-bar">
                <h3>Médicos da Clínica <span style="font-size:0.88rem;font-weight:500;color:var(--text-muted);">(<?= count($listaMedicos) ?>)</span></h3>
                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                    <button type="button" class="confirm-btn" id="btnNovoCadastroMedicos" onclick="alternarCadastroSecretaria('medicos', true)">
                        + Novo Cadastro
                    </button>
                    <button type="button" class="btn btn-secondary" id="btnExpandirListaMedicos" style="display:none;" onclick="alternarCadastroSecretaria('medicos', false)">
                        📋 Maximizar Lista
                    </button>
                </div>
            </div>

            <div id="conteudoListaMedicos">
                <div style="margin-bottom:15px;max-width:420px;">
                    <input type="text" id="buscaMedico" class="field" autocomplete="off"
                        placeholder="🔍 Pesquisar médico por nome, CRM ou especialidade..."
                        onkeyup="filtrarTabelaSecretaria('buscaMedico', 'tbodyMedicos')">
                </div>

                <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>CRM</th>
                            <th>Especialidade</th>
                            <th>Telefone</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>

                    <tbody id="tbodyMedicos">

                    <?php foreach ($listaMedicos as $m): ?>
                        <tr>
                            <td>
                                <a href="javascript:void(0)" class="user-name-link" onclick="visualizarDadosUsuario(<?= (int)$m['id_usuario'] ?>, 'secretaria')" title="Clique para visualizar os dados do médico">
                                    <?= htmlspecialchars($m['nome']) ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($m['crm'] ?? '') ?><?= !empty($m['uf_crm']) ? '-' . htmlspecialchars($m['uf_crm']) : '' ?></td>
                            <td><?= htmlspecialchars($m['especialidade'] ?? '') ?></td>
                            <td><?= htmlspecialchars($m['telefone'] ?? $m['celular'] ?? '') ?></td>
                            <td>
                                <?php if ($m['status'] === 'ativo'): ?>
                                    <span class="status confirmed">Ativo</span>
                                <?php else: ?>
                                    <span class="status">Inativo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="menu-btn" type="button"
                                    onclick="window.location.href='editar_usuario.php?aba=medicos&id=<?= (int)$m['id_usuario'] ?>'">Editar</button>
                                <button class="menu-btn" type="button"
                                    style="background:#c0392b;margin-left:5px;"
                                    onclick="confirmarExclusaoUsuarioSecretaria(<?= (int)$m['id_usuario'] ?>, <?= htmlspecialchars(json_encode($m['nome'], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>, 'medicos')">Excluir</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    </tbody>
                </table>
                </div>
            </div>

        </div>

    </div>

</section>



<!-- ==============================
     PACIENTES
============================== -->

<section
    id="pacientes"
    class="secretaria-tab <?= $abaInicial === 'pacientes' ? 'active' : '' ?>"
    style="<?= $abaInicial === 'pacientes' ? '' : 'display:none;' ?>"
>

    <div class="container">


        <!-- CADASTRAR PACIENTE (Oculto por padrão; abre ao clicar em "Novo Cadastro") -->

        <div class="card" id="cardNovoPaciente" style="display:none;">

            <div class="top-bar">
                <h3>Novo Paciente</h3>
                <button type="button" class="btn btn-secondary" onclick="alternarCadastroSecretaria('pacientes', false)">
                    ← Minimizar / Ver Lista
                </button>
            </div>

            <form
                action="api/secretaria_cadastro_paciente.php"
                method="POST"
                autocomplete="off"
            >

                <h4 style="margin-bottom:15px;color:var(--gold);">Dados de Acesso</h4>
                <div class="grid">

                    <div class="field-group">
                        <label>Nome Completo *</label>
                        <input type="text" name="nome" class="field" required>
                    </div>

                    <div class="field-group">
                        <label>E-mail *</label>
                        <input type="email" name="email" class="field" autocomplete="off" required>
                    </div>

                    <div class="field-group">
                        <label>Senha *</label>
                        <input type="password" name="senha" class="field" autocomplete="new-password" required>
                    </div>

                </div>

                <br>
                <h4 style="margin-bottom:15px;color:var(--gold);">Dados Pessoais</h4>
                <div class="grid">

                    <div class="field-group">
                        <label>CPF</label>
                        <input type="text" name="cpf" class="field mask-cpf" maxlength="14" placeholder="XXX.XXX.XXX-XX">
                    </div>

                    <div class="field-group">
                        <label>RG</label>
                        <input type="text" name="rg" class="field" maxlength="20">
                    </div>

                    <div class="field-group">
                        <label>Data de Nascimento</label>
                        <input type="date" name="data_nascimento" class="field">
                    </div>

                    <div class="field-group">
                        <label>Sexo</label>
                        <select name="sexo" class="field">
                            <option value="">Selecione</option>
                            <option value="M">Masculino</option>
                            <option value="F">Feminino</option>
                        </select>
                    </div>

                    <div class="field-group">
                        <label>Telefone</label>
                        <input type="text" name="telefone" class="field mask-telefone" placeholder="(XX) XXXXX-XXXX">
                    </div>

                    <div class="field-group">
                        <label>Celular</label>
                        <input type="text" name="celular" class="field mask-telefone" placeholder="(XX) XXXXX-XXXX">
                    </div>

                </div>

                <br>
                <h4 style="margin-bottom:15px;color:var(--gold);">Endereço do Paciente</h4>
                <div class="grid">

                    <div class="field-group">
                        <label>CEP</label>
                        <input type="text" name="cep" class="field mask-cep" maxlength="9" placeholder="XXXXX-XXX">
                    </div>

                    <div class="field-group">
                        <label>Endereço</label>
                        <input type="text" name="endereco" class="field">
                    </div>

                    <div class="field-group">
                        <label>Número</label>
                        <input type="text" name="numero" class="field">
                    </div>

                    <div class="field-group">
                        <label>Complemento</label>
                        <input type="text" name="complemento" class="field">
                    </div>

                    <div class="field-group">
                        <label>Bairro</label>
                        <input type="text" name="bairro" class="field">
                    </div>

                    <div class="field-group">
                        <label>Cidade</label>
                        <input type="text" name="cidade" class="field">
                    </div>

                    <div class="field-group">
                        <label>Estado</label>
                        <select name="estado" class="field">
                            <option value="">Selecione</option>
                            <?php foreach ($estados as $uf): ?>
                                <option value="<?= $uf ?>"><?= $uf ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                </div>

                <br>
                <h4 style="margin-bottom:15px;color:var(--gold);">Convênio</h4>
                <div class="grid">

                    <div class="field-group">
                        <label>Convênio</label>
                        <input type="text" name="convenio" class="field" placeholder="Ex: Unimed">
                    </div>

                    <div class="field-group">
                        <label>Nº Carteirinha</label>
                        <input type="text" name="numero_carteirinha" class="field">
                    </div>

                </div>

                <br>
                <h4 style="margin-bottom:15px;color:var(--gold);">Contato de Emergência</h4>
                <div class="grid">

                    <div class="field-group">
                        <label>Nome do Contato</label>
                        <input type="text" name="contato_emergencia_nome" class="field">
                    </div>

                    <div class="field-group">
                        <label>Telefone do Contato</label>
                        <input type="text" name="contato_emergencia_telefone" class="field mask-telefone" placeholder="(XX) XXXXX-XXXX">
                    </div>

                </div>

                <br>

                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                    <button type="submit" class="confirm-btn">
                        Cadastrar Paciente
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="alternarCadastroSecretaria('pacientes', false)">
                        Cancelar
                    </button>
                </div>

            </form>

        </div>


        <!-- LISTA -->

        <div class="card" id="cardListaPacientes">

            <div class="top-bar">
                <h3>Pacientes da Clínica <span style="font-size:0.88rem;font-weight:500;color:var(--text-muted);">(<?= count($listaPacientes) ?>)</span></h3>
                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                    <button type="button" class="confirm-btn" id="btnNovoCadastroPacientes" onclick="alternarCadastroSecretaria('pacientes', true)">
                        + Novo Cadastro
                    </button>
                    <button type="button" class="btn btn-secondary" id="btnExpandirListaPacientes" style="display:none;" onclick="alternarCadastroSecretaria('pacientes', false)">
                        📋 Maximizar Lista
                    </button>
                </div>
            </div>

            <div id="conteudoListaPacientes">
                <div class="busca-paciente-wrap" style="position:relative;margin-bottom:15px;max-width:420px;">
                    <input type="text" id="buscaPaciente" class="field" autocomplete="off"
                        placeholder="🔍 Pesquisar paciente por nome ou CPF..."
                        oninput="buscarPacientePopup(this)" onfocus="buscarPacientePopup(this)"
                        onkeydown="if(event.key==='Escape')fecharPopupPacientes()">
                    <div id="popupPacientes" style="display:none;position:absolute;top:100%;left:0;right:0;z-index:50;margin-top:4px;background:#fff;color:#222;border:1px solid #ddd;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.2);max-height:260px;overflow-y:auto;"></div>
                </div>

                <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>CPF</th>
                            <th>Telefone</th>
                            <th>Convênio</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php foreach ($listaPacientes as $p): ?>
                        <tr>
                            <td>
                                <a href="javascript:void(0)" class="user-name-link" onclick="visualizarDadosUsuario(<?= (int)$p['id_usuario'] ?>, 'secretaria')" title="Clique para visualizar os dados do paciente">
                                    <?= htmlspecialchars($p['nome']) ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($p['cpf'] ?? '') ?></td>
                            <td><?= htmlspecialchars($p['telefone'] ?? $p['celular'] ?? '') ?></td>
                            <td><?= htmlspecialchars($p['convenio'] ?? '') ?></td>
                            <td>
                                <?php if ($p['status'] === 'ativo'): ?>
                                    <span class="status confirmed">Ativo</span>
                                <?php else: ?>
                                    <span class="status">Inativo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="menu-btn" type="button"
                                    onclick="window.location.href='editar_usuario.php?aba=pacientes&id=<?= (int)$p['id_usuario'] ?>'">Editar</button>
                                <button class="menu-btn" type="button"
                                    style="background:#c0392b;margin-left:5px;"
                                    onclick="confirmarExclusaoUsuarioSecretaria(<?= (int)$p['id_usuario'] ?>, <?= htmlspecialchars(json_encode($p['nome'], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>, 'pacientes')">Excluir</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    </tbody>
                </table>
                </div>
            </div>

        </div>

    </div>

</section>



<!-- ==============================
     AGENDAMENTOS
============================== -->

<section
    id="agendamentos"
    class="secretaria-tab <?= $abaInicial === 'agendamentos' ? 'active' : '' ?>"
    style="<?= $abaInicial === 'agendamentos' ? '' : 'display:none;' ?>"
>

    <div class="container">


        <!-- AGENDAR CONSULTA (ESTILO PACIENTE + ESCOLHA DE PACIENTE) -->

        <div class="card">

            <div class="top-bar">
                <h3>📅 Agendar Consulta</h3>
            </div>

            <form
                id="formNovaConsulta"
                action="api/secretaria_agendar_consulta.php"
                method="POST"
            >

                <div class="grid">

                    <!-- PACIENTE (A única diferença: secretária escolhe o paciente) -->
                    <div class="field-group" style="grid-column: 1 / -1;">
                        <label>Paciente *</label>
                        <input type="text" id="filtroPaciente" placeholder="🔍 Filtrar paciente por nome ou CPF..."
                            onkeyup="filtrarSelectPacientes(this)" class="field"
                            style="margin-bottom:6px;min-height:36px;font-size:0.85rem;">
                        <select name="id_paciente" id="selectPacienteAgendamento" class="field" required>
                            <option value="">Selecione o paciente...</option>
                            <?php foreach ($listaPacientesSelect as $p): ?>
                                <option value="<?= $p['id_usuario'] ?>">
                                    <?= htmlspecialchars($p['nome']) ?><?= !empty($p['cpf']) ? ' — CPF: ' . htmlspecialchars($p['cpf']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- ESPECIALIDADE -->
                    <div class="field-group">
                        <label>Especialidade</label>
                        <select id="agEspecialidade" class="field" onchange="secretariaCarregarMedicos()">
                            <option value="">Todas as especialidades</option>
                            <?php foreach ($listaEspecialidades as $esp): ?>
                                <option value="<?= htmlspecialchars($esp['nome']) ?>">
                                    <?= htmlspecialchars($esp['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- MÉDICO -->
                    <div class="field-group">
                        <label>Médico *</label>
                        <select name="id_medico" id="agMedico" class="field" onchange="secretariaCarregarDatas()" required>
                            <option value="">Selecione o médico...</option>
                            <?php foreach ($listaMedicosSelect as $m): ?>
                                <option value="<?= $m['id_usuario'] ?>">
                                    <?= htmlspecialchars($m['nome']) ?><?= !empty($m['especialidade']) ? ' — ' . htmlspecialchars($m['especialidade']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- DATA (Datas reais disponíveis da agenda do médico) -->
                    <div class="field-group">
                        <label>Data Disponível *</label>
                        <select name="data_consulta" id="agData" class="field" onchange="secretariaCarregarHorarios()" disabled required>
                            <option value="">Selecione o médico primeiro...</option>
                        </select>
                    </div>

                    <!-- HORÁRIO -->
                    <div class="field-group">
                        <label>Horário *</label>
                        <select name="hora_consulta" id="agHorario" class="field" disabled required>
                            <option value="">Selecione a data primeiro...</option>
                        </select>
                    </div>

                    <!-- TIPO DE ATENDIMENTO -->
                    <div class="field-group">
                        <label>Tipo de Atendimento</label>
                        <select name="tipo_consulta" class="field">
                            <option value="presencial">Presencial</option>
                            <option value="telemedicina">Telemedicina</option>
                        </select>
                    </div>

                    <!-- OBSERVAÇÕES -->
                    <div class="field-group">
                        <label>Observações</label>
                        <input type="text" name="observacoes" class="field" placeholder="Opcional (sala, orientações, etc.)">
                    </div>

                </div>

                <!-- CHIPS VISUAIS DE HORÁRIOS -->
                <div id="slotsChipsContainer" style="margin-top:10px;"></div>

                <!-- MOTIVO DA CONSULTA -->
                <div class="textarea-group" style="margin-top:15px;">
                    <label>Motivo da Consulta *</label>
                    <textarea name="motivo_consulta" id="agMotivo" placeholder="Descreva brevemente o motivo da consulta..." required
                        style="width:100%;min-height:75px;padding:10px 12px;border:2px solid #e0e0e0;border-radius:10px;font-family:inherit;font-size:.95rem;resize:vertical;box-sizing:border-box;"></textarea>
                </div>

                <br>

                <button type="submit" class="confirm-btn" id="btnAgendarSecretaria" disabled>
                    ✓ Agendar Consulta
                </button>

            </form>

        </div>



        <!-- LISTA -->

        <div class="card">

            <div class="top-bar">
                <h3>Todas as Consultas</h3>
                <input type="text" id="buscaTodas" placeholder="🔍 Filtrar agendamentos..." onkeyup="filtrarTabelaSecretaria('buscaTodas', 'tbodyTodas')" class="field" style="width:260px;min-height:36px;padding:6px 12px;font-size:0.86rem;">
            </div>

            <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Horário</th>
                        <th>Paciente</th>
                        <th>Médico</th>
                        <th>Especialidade</th>
                        <th>Motivo</th>
                        <th>Tipo</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>

                <tbody id="tbodyTodas">

                <?php foreach ($listaAgendamentos as $a): ?>
                    <tr>
                        <td><?= date('d/m/Y', strtotime($a['data_consulta'])) ?></td>
                        <td><?= substr($a['hora_consulta'], 0, 5) ?></td>
                        <td>
                            <a href="javascript:void(0)" class="user-name-link" onclick="visualizarDadosUsuario(<?= (int)$a['id_paciente'] ?>, 'secretaria')" title="Clique para visualizar os dados do paciente">
                                <?= htmlspecialchars($a['nome_paciente']) ?>
                            </a>
                        </td>
                        <td>
                            <a href="javascript:void(0)" class="user-name-link" onclick="visualizarDadosUsuario(<?= (int)$a['id_medico'] ?>, 'secretaria')" title="Clique para visualizar os dados do médico">
                                <?= htmlspecialchars($a['nome_medico']) ?>
                            </a>
                        </td>
                        <td><?= htmlspecialchars($a['especialidade_medico'] ?? '') ?></td>
                        <td><?= htmlspecialchars($a['motivo_consulta'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($a['tipo_consulta'] ?? 'presencial') ?></td>
                        <td>
                            <?php if ($a['status'] === 'aguardando_atendimento' || $a['status'] === 'aguardando_atendimen' || $a['status'] === 'aguardando'): ?>
                                <span class="status status-aguardando">Aguardando Atendimento</span>
                            <?php elseif ($a['status'] === 'em_atendimento'): ?>
                                <span class="status status-em-atendimento">Em Atendimento</span>
                            <?php elseif ($a['status'] === 'finalizada'): ?>
                                <span class="status confirmed">Finalizada</span>
                            <?php elseif ($a['status'] === 'agendada'): ?>
                                <span class="status pending">Agendada</span>
                            <?php elseif ($a['status'] === 'cancelada'): ?>
                                <span class="status status-cancelada">Cancelada</span>
                            <?php else: ?>
                                <span class="status"><?= htmlspecialchars($a['status']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($a['status'] === 'agendada'): ?>
                                <button type="button" class="menu-btn" style="background:#10b981;" onclick="alterarStatusConsulta(<?= $a['id_consulta'] ?>, 'aguardando_atendimento', this, 'agendamentos')">
                                    ✓ Confirmar Presença
                                </button>
                                <button type="button" class="menu-btn" style="background:#ef4444;margin-left:5px;" onclick="alterarStatusConsulta(<?= $a['id_consulta'] ?>, 'cancelada', this, 'agendamentos')">
                                    ✗ Cancelar
                                </button>
                            <?php else: ?>
                                <span style="color:#888;font-size:0.85rem;">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>

                </tbody>
            </table>
            </div>

        </div>

    </div>

</section>

<?php
$usuariosMapSecretaria = [];
foreach ($listaMedicos as $mItem) {
    $mItem['nome_clinica'] = $nomeClinica;
    $usuariosMapSecretaria[(int)$mItem['id_usuario']] = $mItem;
}
foreach ($listaPacientes as $pItem) {
    $pItem['nome_clinica'] = $nomeClinica;
    $usuariosMapSecretaria[(int)$pItem['id_usuario']] = $pItem;
}
?>
<script>
window.usuariosDadosMap = <?= json_encode($usuariosMapSecretaria, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
</script>
<script src="assets/js/main.js?v=<?= filemtime(__DIR__ . '/assets/js/main.js') ?>"></script>
<script src="assets/js/secretaria.js?v=<?= filemtime(__DIR__ . '/assets/js/secretaria.js') ?>"></script>

</body>
</html>
