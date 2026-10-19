<?php

require_once('config/auth.php');
require_once('config/database.php');

verificarPerfil('secretaria');

$nomeSecretaria = $_SESSION['nome'];
$id_clinica = $_SESSION['id_clinica'];

/* ABA ATIVA */

$abaInicial = $_GET['aba'] ?? 'dashboard';
if (!in_array($abaInicial, ['dashboard', 'medicos', 'pacientes', 'agendamentos'], true)) {
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
        COALESCE(SUM(c.data_consulta = CURDATE() OR DATE(c.chegada_em) = CURDATE()), 0) AS hoje,
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
   MÉDICOS DA CLÍNICA
============================== */

if ($abaInicial === 'medicos' || $abaInicial === 'agendamentos') {

$medicos = $conn->prepare("
    SELECT
        id_usuario, nome, crm, uf_crm,
        especialidade, telefone, status, duracao_consulta
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
        id_usuario, nome, cpf, telefone,
        celular, convenio, status
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
if ($abaInicial === 'medicos') {
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

<title>Painel Secretaria - TechNyes</title>

<link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>">

</head>

<body>

<header>

    <div class="logo">
        <a href="secretaria.php">
            Tech<span>Nyes</span>
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


        <!-- MENSAGEM -->

        <?php if ($abaInicial === 'dashboard' && !empty($mensagemSucesso)): ?>

            <div class="card">
                <p style="color:green;font-weight:bold;">
                    <?= htmlspecialchars($mensagemSucesso) ?>
                </p>
            </div>

        <?php endif; ?>


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
                        <td><strong><?= htmlspecialchars($c['nome_paciente']) ?></strong></td>
                        <td><?= htmlspecialchars($c['nome_medico']) ?></td>
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


        <?php if ($abaInicial === 'medicos' && !empty($mensagemSucesso)): ?>
            <div class="card">
                <p style="color:green;font-weight:bold;">
                    <?= htmlspecialchars($mensagemSucesso) ?>
                </p>
            </div>
        <?php endif; ?>


        <!-- CADASTRAR MÉDICO -->

        <div class="card">

            <div class="top-bar">
                <h3>Novo Médico</h3>
            </div>

            <form
                action="api/secretaria_cadastro_medico.php"
                method="POST"
            >

                <h4 style="margin-bottom:15px;color:var(--gold);">Dados de Acesso</h4>
                <div class="grid">

                    <div class="field-group">
                        <label>Nome Completo *</label>
                        <input type="text" name="nome" class="field" required>
                    </div>

                    <div class="field-group">
                        <label>E-mail *</label>
                        <input type="email" name="email" class="field" required>
                    </div>

                    <div class="field-group">
                        <label>Senha *</label>
                        <input type="password" name="senha" class="field" required>
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

                <button type="submit" class="confirm-btn">
                    Cadastrar Médico
                </button>

            </form>

        </div>


        <!-- LISTA -->

        <div class="card">

            <div class="top-bar">
                <h3>Médicos da Clínica</h3>
            </div>

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

                <tbody>

                <?php foreach ($listaMedicos as $m): ?>
                    <tr>
                        <td><?= htmlspecialchars($m['nome']) ?></td>
                        <td><?= htmlspecialchars($m['crm'] ?? '') ?>-<?= htmlspecialchars($m['uf_crm'] ?? '') ?></td>
                        <td><?= htmlspecialchars($m['especialidade'] ?? '') ?></td>
                        <td><?= htmlspecialchars($m['telefone'] ?? '') ?></td>
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
                        </td>
                    </tr>
                <?php endforeach; ?>

                </tbody>
            </table>

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


        <?php if ($abaInicial === 'pacientes' && !empty($mensagemSucesso)): ?>
            <div class="card">
                <p style="color:green;font-weight:bold;">
                    <?= htmlspecialchars($mensagemSucesso) ?>
                </p>
            </div>
        <?php endif; ?>


        <!-- CADASTRAR PACIENTE -->

        <div class="card">

            <div class="top-bar">
                <h3>Novo Paciente</h3>
            </div>

            <form
                action="api/secretaria_cadastro_paciente.php"
                method="POST"
            >

                <h4 style="margin-bottom:15px;color:var(--gold);">Dados de Acesso</h4>
                <div class="grid">

                    <div class="field-group">
                        <label>Nome Completo *</label>
                        <input type="text" name="nome" class="field" required>
                    </div>

                    <div class="field-group">
                        <label>E-mail *</label>
                        <input type="email" name="email" class="field" required>
                    </div>

                    <div class="field-group">
                        <label>Senha *</label>
                        <input type="password" name="senha" class="field" required>
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

                <button type="submit" class="confirm-btn">
                    Cadastrar Paciente
                </button>

            </form>

        </div>


        <!-- LISTA -->

        <div class="card">

            <div class="top-bar">
                <h3>Pacientes da Clínica</h3>
            </div>

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
                        <td><?= htmlspecialchars($p['nome']) ?></td>
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
                        </td>
                    </tr>
                <?php endforeach; ?>

                </tbody>
            </table>

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


        <?php if ($abaInicial === 'agendamentos' && !empty($mensagemSucesso)): ?>
            <div class="card">
                <p style="color:green;font-weight:bold;">
                    <?= htmlspecialchars($mensagemSucesso) ?>
                </p>
            </div>
        <?php endif; ?>


        <!-- NOVA CONSULTA -->

        <div class="card">

            <div class="top-bar">
                <h3>Nova Consulta</h3>
            </div>

            <form
                id="formNovaConsulta"
                action="api/secretaria_agendar_consulta.php"
                method="POST"
            >

                <div class="grid">

                    <div class="field-group">
                        <label>Paciente *</label>
                        <input type="text" placeholder="🔍 Filtrar paciente..." onkeyup="filtrarSelectPacientes(this)" class="field" style="margin-bottom:6px;min-height:36px;font-size:0.85rem;">
                        <select name="id_paciente" id="selectPacienteAgendamento" class="field" required>
                            <option value="">Selecione o paciente</option>
                            <?php foreach ($listaPacientesSelect as $p): ?>
                                <option value="<?= $p['id_usuario'] ?>">
                                    <?= htmlspecialchars($p['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field-group">
                        <label>Médico *</label>
                        <select name="id_medico" id="slotMedico" class="field" required
                            onchange="agendaResetSlots()">
                            <option value="">Selecione</option>
                            <?php foreach ($listaMedicosSelect as $m): ?>
                                <option value="<?= $m['id_usuario'] ?>"
                                    data-duracao="<?= intval($m['duracao_consulta'] ?? 30) ?>">
                                    <?= htmlspecialchars($m['nome']) ?> — <?= htmlspecialchars($m['especialidade'] ?? '') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field-group">
                        <label>Data *</label>
                        <input type="date" id="slotData" class="field" required
                            min="<?= date('Y-m-d') ?>"
                            max="<?= date('Y-m-d', strtotime('+2 months')) ?>"
                            onchange="agendaCarregarSlots()">
                        <input type="hidden" name="data_consulta" id="hdnData">
                    </div>

                    <div class="field-group">
                        <label>Horário disponível *</label>
                        <select name="hora_consulta" id="slotHora" class="field" required disabled>
                            <option value="">Selecione médico e data primeiro</option>
                        </select>
                    </div>

                    <div class="field-group full" id="slotsChipsContainer">
                        <!-- Chips visuais inseridos dinamicamente -->
                    </div>

                    <div class="field-group">
                        <label>Tipo</label>
                        <select name="tipo_consulta" class="field">
                            <option value="presencial">Presencial</option>
                            <option value="telemedicina">Telemedicina</option>
                        </select>
                    </div>

                    <div class="field-group">
                        <label>Observações</label>
                        <input type="text" name="observacoes" class="field" placeholder="Opcional">
                    </div>

                    <div class="field-group full">
                        <label>Motivo da Consulta *</label>
                        <input type="text" name="motivo_consulta" class="field" placeholder="Ex: Dor de cabeça persistente, Exame de rotina..." required>
                    </div>

                </div>

                <br>

                <button type="submit" class="confirm-btn">
                    Agendar Consulta
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
                        <td><strong><?= htmlspecialchars($a['nome_paciente']) ?></strong></td>
                        <td><?= htmlspecialchars($a['nome_medico']) ?></td>
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


<script src="assets/js/main.js?v=<?= filemtime(__DIR__ . '/assets/js/main.js') ?>"></script>
<script src="assets/js/secretaria.js?v=<?= filemtime(__DIR__ . '/assets/js/secretaria.js') ?>"></script>

</body>
</html>