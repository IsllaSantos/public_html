<?php

require_once('config/auth.php');
require_once('config/database.php');

verificarPerfil('medico');

$nomeMedico = $_SESSION['nome'];
$id_medico  = $_SESSION['id_usuario'];


/* ==============================
   CONSULTAS AGUARDANDO ATENDIMENTO (HOJE)
============================== */

$stmtAguardando = $conn->prepare("
    SELECT
        c.id_consulta,
        c.data_consulta,
        c.hora_consulta,
        c.motivo_consulta,
        c.status,
        c.chegada_em,
        p.nome AS nome_paciente,
        p.id_usuario AS id_paciente,
        p.convenio
    FROM consulta c
    INNER JOIN usuarios p ON c.id_paciente = p.id_usuario
    WHERE c.id_medico = ?
    AND c.status IN ('aguardando_atendimento', 'aguardando_atendimen', 'aguardando')
    ORDER BY c.chegada_em ASC, c.hora_consulta ASC
");
$stmtAguardando->bind_param("i", $id_medico);
$stmtAguardando->execute();
$listaAguardando = $stmtAguardando->get_result();
$totalAguardando = $listaAguardando->num_rows;


/* ==============================
   PRÓXIMAS CONSULTAS (AGENDA GERAL)
============================== */

$stmtConsultas = $conn->prepare("
    SELECT
        c.id_consulta,
        c.data_consulta,
        c.hora_consulta,
        c.motivo_consulta,
        c.status,
        p.nome AS nome_paciente,
        p.id_usuario AS id_paciente
    FROM consulta c
    INNER JOIN usuarios p ON c.id_paciente = p.id_usuario
    WHERE c.id_medico = ?
    AND c.data_consulta >= CURDATE()
    AND c.status NOT IN ('cancelada', 'finalizada')
    ORDER BY c.data_consulta ASC, c.hora_consulta ASC
    LIMIT 30
");
$stmtConsultas->bind_param("i", $id_medico);
$stmtConsultas->execute();
$listaConsultas = $stmtConsultas->get_result();


/* ==============================
   PACIENTES DO MÉDICO
============================== */

$stmtPacientes = $conn->prepare("
    SELECT
        p.id_usuario,
        p.nome,
        p.cpf,
        p.status,
        MAX(c.data_consulta) AS ultima_consulta
    FROM consulta c
    INNER JOIN usuarios p ON c.id_paciente = p.id_usuario
    WHERE c.id_medico = ?
    GROUP BY p.id_usuario, p.nome, p.cpf, p.status
    ORDER BY p.nome
");
$stmtPacientes->bind_param("i", $id_medico);
$stmtPacientes->execute();
$listaPacientes = $stmtPacientes->get_result();


/* ==============================
   MÉTRICAS DO DIA
============================== */

$stmtHoje = $conn->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'finalizada' THEN 1 ELSE 0 END) AS finalizadas,
        SUM(CASE WHEN status = 'em_atendimento' THEN 1 ELSE 0 END) AS em_atendimento
    FROM consulta
    WHERE id_medico = ? AND data_consulta = CURDATE()
");
$stmtHoje->bind_param("i", $id_medico);
$stmtHoje->execute();
$metricas = $stmtHoje->get_result()->fetch_assoc();

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Painel Médico - TechNyes</title>

<link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>">

</head>

<body>

<header>

  <div class="logo">
    <a href="medico.php">
        Tech<span>Nyes</span>
    </a>
  </div>

    <nav>

        <button onclick="showScreen('aguardando',event)" class="active">
            Aguardando
            <?php if ($totalAguardando > 0): ?>
                <span class="nav-badge"><?= $totalAguardando ?></span>
            <?php endif; ?>
        </button>

        <button onclick="showScreen('agenda',event)">
            Agenda
        </button>

        <button onclick="showScreen('minhaAgenda',event)">
            Minha Agenda
        </button>

        <button onclick="showScreen('listaPacientesMedico',event)">
            Pacientes
        </button>

        <button onclick="window.location.href='logout.php'">
            Sair
        </button>

    </nav>

</header>

<main class="container">

  <div class="container">

    <div class="top-bar">

        <h2>

            Bem-vindo,

            <span style="color:var(--gold);">

                Dr(a). <?php echo htmlspecialchars($nomeMedico); ?>

            </span>

        </h2>

    </div>

  </div>


<!-- ==============================
     MÉTRICAS DO DIA
============================== -->

<div class="stats-row">

    <div class="card">
        <h3>Aguardando Atendimento</h3>
        <div style="font-size:2rem;font-weight:bold;color:#2980b9;">
            <?= $totalAguardando ?>
        </div>
    </div>

    <div class="card">
        <h3>Consultas Hoje</h3>
        <div style="font-size:2rem;font-weight:bold;color:var(--gold);">
            <?= $metricas['total'] ?? 0 ?>
        </div>
    </div>

    <div class="card">
        <h3>Finalizadas Hoje</h3>
        <div style="font-size:2rem;font-weight:bold;color:#27ae60;">
            <?= $metricas['finalizadas'] ?? 0 ?>
        </div>
    </div>

</div>


<!-- ==============================
     AGUARDANDO ATENDIMENTO
============================== -->

<section id="aguardando"
    class="screen active">

    <div class="card">

        <div class="top-bar" style="margin-bottom:20px;">
            <h3>🕐 Pacientes Aguardando Atendimento</h3>
        </div>

        <table>

            <thead>
                <tr>
                    <th>Ordem</th>
                    <th>Horário</th>
                    <th>Chegada</th>
                    <th>Paciente</th>
                    <th>Convênio</th>
                    <th>Motivo</th>
                    <th>Ações</th>
                </tr>
            </thead>

            <tbody>

            <?php if ($totalAguardando === 0): ?>

                <tr>
                    <td colspan="7" style="text-align:center;color:#888;padding:30px;">
                        Nenhum paciente aguardando atendimento no momento.
                    </td>
                </tr>

            <?php else: ?>
            <?php $ordem = 1; ?>
            <?php while ($a = $listaAguardando->fetch_assoc()): ?>

                <tr>
                    <td>
                        <span style="
                            background:#2980b9;
                            color:white;
                            padding:4px 12px;
                            border-radius:20px;
                            font-weight:bold;
                        "><?= $ordem++ ?></span>
                    </td>
                    <td><?= substr($a['hora_consulta'], 0, 5) ?></td>
                    <td>
                        <?= $a['chegada_em']
                            ? date('H:i', strtotime($a['chegada_em']))
                            : '—' ?>
                    </td>
                    <td><strong><?= htmlspecialchars($a['nome_paciente']) ?></strong></td>
                    <td><?= htmlspecialchars($a['convenio'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($a['motivo_consulta'] ?? '—') ?></td>
                    <td>
                        <button class="menu-btn" style="background:#27ae60;"
                            onclick="iniciarAtendimento(<?= $a['id_consulta'] ?>, <?= $a['id_paciente'] ?>)">
                            ▶ Iniciar Atendimento
                        </button>
                        <button class="menu-btn" style="background:#2980b9; margin-left:5px;"
                            onclick="verDetalhes(<?= $a['id_consulta'] ?>, <?= $a['id_paciente'] ?>)">
                            Ver Detalhes
                        </button>
                    </td>
                </tr>

            <?php endwhile; ?>
            <?php endif; ?>

            </tbody>

        </table>

    </div>

</section>


<!-- ==============================
     AGENDA (PRÓXIMAS CONSULTAS)
============================== -->

 <section id="agenda"
    class="screen">

        <div class="card">

            <h3>
                Próximas Consultas
            </h3>

            <table>

                <thead>

                    <tr>
                        <th>Data</th>
                        <th>Horário</th>
                        <th>Paciente</th>
                        <th>Motivo</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>

                </thead>

                <tbody>

                <?php if ($listaConsultas->num_rows === 0): ?>

                    <tr>
                        <td colspan="6" style="text-align:center;color:#888;">
                            Nenhuma consulta agendada.
                        </td>
                    </tr>

                <?php else: ?>
                <?php while ($c = $listaConsultas->fetch_assoc()): ?>

                    <tr>
                        <td><?= date('d/m/Y', strtotime($c['data_consulta'])) ?></td>
                        <td><?= substr($c['hora_consulta'], 0, 5) ?></td>
                        <td><?= htmlspecialchars($c['nome_paciente']) ?></td>
                        <td><?= htmlspecialchars($c['motivo_consulta'] ?? '—') ?></td>

                        <td>
                            <?php if ($c['status'] === 'aguardando_atendimento' || $c['status'] === 'aguardando_atendimen' || $c['status'] === 'aguardando'): ?>
                                <span class="status status-aguardando">Aguardando</span>
                            <?php elseif ($c['status'] === 'em_atendimento'): ?>
                                <span class="status status-em-atendimento">Em Atendimento</span>
                            <?php elseif ($c['status'] === 'agendada'): ?>
                                <span class="status pending">Agendada</span>
                            <?php else: ?>
                                <span class="status"><?= htmlspecialchars($c['status']) ?></span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?php if ($c['status'] === 'aguardando_atendimento' || $c['status'] === 'aguardando_atendimen' || $c['status'] === 'aguardando' || $c['status'] === 'em_atendimento'): ?>
                                <button class="menu-btn" style="background:#27ae60;"
                                    onclick="iniciarAtendimento(<?= $c['id_consulta'] ?>, <?= $c['id_paciente'] ?>)">
                                    ▶ Iniciar Atendimento
                                </button>
                                <button class="menu-btn" style="background:#2980b9; margin-left:5px;"
                                    onclick="verDetalhes(<?= $c['id_consulta'] ?>, <?= $c['id_paciente'] ?>)">
                                    Ver Detalhes
                                </button>
                            <?php else: ?>
                                <button class="menu-btn" style="background:#2980b9;"
                                    onclick="verDetalhes(<?= $c['id_consulta'] ?>, <?= $c['id_paciente'] ?>)">
                                    Ver Detalhes
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>

                <?php endwhile; ?>
                <?php endif; ?>

                </tbody>

            </table>

        </div>

 </section>


<!-- ==============================
     LISTA DE PACIENTES
============================== -->

 <section id="listaPacientesMedico"
 class="screen">

    <div class="card">

        <div class="top-bar"
        style="margin-bottom:20px;">

            <h3>
                Pacientes
            </h3>

            <input type="text"
            id="buscaPaciente"
            placeholder="Buscar paciente..."
            onkeyup="filtrarPacientes()"
            style="
            padding:12px;
            border-radius:10px;
            border:2px solid var(--gold);
            width:250px;">

        </div>

        <table>

            <thead>

                <tr>
                    <th>Paciente</th>
                    <th>CPF</th>
                    <th>Última Consulta</th>
                    <th>Status</th>
                    <th></th>
                </tr>

            </thead>

            <tbody id="tbodyPacientes">

            <?php if ($listaPacientes->num_rows === 0): ?>

                <tr>
                    <td colspan="5" style="text-align:center;color:#888;">
                        Nenhum paciente encontrado.
                    </td>
                </tr>

            <?php else: ?>
            <?php while ($p = $listaPacientes->fetch_assoc()): ?>

                <tr>
                    <td><?= htmlspecialchars($p['nome']) ?></td>
                    <td><?= htmlspecialchars($p['cpf'] ?? '—') ?></td>
                    <td>
                        <?= $p['ultima_consulta']
                            ? date('d/m/Y', strtotime($p['ultima_consulta']))
                            : '—' ?>
                    </td>

                    <td>
                        <?php if ($p['status'] === 'ativo'): ?>
                            <span class="status confirmed">Ativo</span>
                        <?php else: ?>
                            <span class="status">Inativo</span>
                        <?php endif; ?>
                    </td>

                    <td>
                        <button class="menu-btn"
                            onclick="verDetalhes(0, <?= $p['id_usuario'] ?>)">
                            Ver Detalhes / Prontuário
                        </button>
                    </td>
                </tr>

            <?php endwhile; ?>
            <?php endif; ?>

            </tbody>

        </table>

    </div>

  </section>


<!-- ==============================
     TELA DE ATENDIMENTO / PRONTUÁRIO
============================== -->

  <section id="atendimento"
    class="screen">

    <!-- LOADING -->
    <div id="atendimentoLoading" class="card" style="text-align:center;padding:40px;">
        <p style="color:#888;font-size:1.1rem;">Carregando dados do paciente...</p>
    </div>

    <!-- CONTEÚDO (preenchido via JS) -->
    <div id="atendimentoConteudo" style="display:none;">

        <!-- CABEÇALHO DO PACIENTE -->
        <div class="card">

            <div class="top-bar">
                <h3 id="atdNomePaciente">Paciente</h3>
                <div>
                    <span id="atdStatusConsulta" class="status"></span>
                </div>
            </div>

            <div class="grid" id="atdDadosPaciente">
                <!-- Preenchido via JS -->
            </div>

        </div>

        <!-- INFORMAÇÕES DA CONSULTA ATUAL -->
        <div id="atdConsultaAtualCard" class="card" style="display:none;">

            <h3>📋 Consulta Atual</h3>

            <div class="grid" id="atdConsultaAtualDados">
                <!-- Preenchido via JS -->
            </div>

        </div>


        <!-- ==============================
             ETAPAS DO PRONTUÁRIO
        ============================== -->

        <div class="prontuario-tabs" id="prontuarioTabs">
            <button type="button" class="prontuario-btn active" onclick="showProntuario('abaAnamnese',event)" data-tab="abaAnamnese">
                <span class="step-num">1</span> 📝 Anamnese
            </button>
            <button type="button" class="prontuario-btn" onclick="showProntuario('abaExameFisico',event)" data-tab="abaExameFisico">
                <span class="step-num">2</span> 🩺 Exame Físico
            </button>
            <button type="button" class="prontuario-btn" onclick="showProntuario('abaDiagnostico',event)" data-tab="abaDiagnostico">
                <span class="step-num">3</span> 🔬 Diagnóstico
            </button>
            <button type="button" class="prontuario-btn" onclick="showProntuario('abaReceita',event)" data-tab="abaReceita">
                <span class="step-num">4</span> 💊 Prescrição / Exames
            </button>
            <button type="button" class="prontuario-btn" onclick="showProntuario('abaPosAtendimento',event)" data-tab="abaPosAtendimento">
                <span class="step-num">5</span> 📋 Pós-atendimento
            </button>
            <button type="button" class="prontuario-btn" onclick="showProntuario('abaHistorico',event)" data-tab="abaHistorico">
                <span class="step-num">6</span> 📂 Histórico
            </button>
        </div>


        <!-- FORMULÁRIO PRINCIPAL (compartilhado entre abas 1-3 e 5) -->
        <form id="formProntuario" onsubmit="salvarProntuario(event)">

            <input type="hidden" id="prontIdConsulta" name="id_consulta">


            <!-- ========================
                 ABA 1 — ANAMNESE (Etapa 5)
            ======================== -->

            <div id="abaAnamnese" class="prontuario-content active">

                <div class="card">
                    <h3>📝 Anamnese</h3>

                    <div class="textarea-group">
                        <label>Queixa Principal *</label>
                        <textarea id="prontQueixa" name="queixa_principal"
                            placeholder="Qual o motivo da consulta? O que o paciente relata?"></textarea>
                    </div>

                    <div class="textarea-group">
                        <label>Histórico da Doença Atual</label>
                        <textarea id="prontHistDoenca" name="historico_doenca" class="large"
                            placeholder="Quando começaram os sintomas? Evolução, fatores de piora/melhora..."></textarea>
                    </div>

                    <div class="two-columns">
                        <div class="textarea-group">
                            <label>Antecedentes Pessoais</label>
                            <textarea id="prontAntecedentes" name="antecedentes"
                                placeholder="Doenças prévias, cirurgias, internações..."></textarea>
                        </div>

                        <div class="textarea-group">
                            <label>Alergias</label>
                            <textarea id="prontAlergias" name="alergias"
                                placeholder="Medicamentos, alimentos, substâncias..."></textarea>
                        </div>
                    </div>

                    <div class="two-columns">
                        <div class="textarea-group">
                            <label>Medicamentos em Uso</label>
                            <textarea id="prontMedicamentos" name="medicamentos"
                                placeholder="Medicamentos atuais, dosagens e frequência..."></textarea>
                        </div>

                        <div class="textarea-group">
                            <label>Histórico Familiar</label>
                            <textarea id="prontHistFamiliar" name="historico_familiar"
                                placeholder="Doenças na família (diabetes, hipertensão, câncer...)"></textarea>
                        </div>
                    </div>

                    <div class="textarea-group">
                        <label>Hábitos de Vida</label>
                        <textarea id="prontHabitos" name="habitos_vida"
                            placeholder="Tabagismo, etilismo, atividade física, alimentação, sono..."></textarea>
                    </div>

                </div>

                <div class="prontuario-actions">
                    <button type="submit" class="confirm-btn">💾 Salvar</button>
                    <button type="button" class="menu-btn" style="background:#2980b9;"
                        onclick="showProntuario('abaExameFisico')">Próximo: Exame Físico →</button>
                </div>

            </div>


            <!-- ========================
                 ABA 2 — EXAME FÍSICO (Etapa 6)
            ======================== -->

            <div id="abaExameFisico" class="prontuario-content">

                <div class="card">
                    <h3>🩺 Exame Físico</h3>

                    <p style="color:#888;margin-bottom:15px;">Sinais Vitais</p>

                    <div class="sinais-vitais-grid">

                        <div class="field-group">
                            <label>Pressão Arterial</label>
                            <input type="text" id="prontPA" name="pressao_arterial"
                                class="field" placeholder="Ex: 120/80 mmHg">
                            <div id="vitalTagPA"></div>
                        </div>

                        <div class="field-group">
                            <label>Freq. Cardíaca (bpm)</label>
                            <input type="text" id="prontFC" name="frequencia_cardiaca"
                                class="field" placeholder="Ex: 72">
                        </div>

                        <div class="field-group">
                            <label>Freq. Respiratória (irpm)</label>
                            <input type="text" id="prontFR" name="frequencia_respiratoria"
                                class="field" placeholder="Ex: 16">
                        </div>

                        <div class="field-group">
                            <label>Temperatura (°C)</label>
                            <input type="text" id="prontTemp" name="temperatura"
                                class="field" placeholder="Ex: 36.5">
                            <div id="vitalTagTemp"></div>
                        </div>

                        <div class="field-group">
                            <label>Peso (kg)</label>
                            <input type="text" id="prontPeso" name="peso"
                                class="field" placeholder="Ex: 72.5">
                        </div>

                        <div class="field-group">
                            <label>Altura (cm)</label>
                            <input type="text" id="prontAltura" name="altura"
                                class="field" placeholder="Ex: 175">
                        </div>

                        <div class="field-group">
                            <label>IMC</label>
                            <input type="text" id="prontIMC" class="field" readonly
                                placeholder="Calculado automaticamente"
                                style="background:#f8fafc;">
                            <div id="vitalTagIMC"></div>
                        </div>

                        <div class="field-group">
                            <label>Saturação O₂ (%)</label>
                            <input type="text" id="prontSat" name="saturacao"
                                class="field" placeholder="Ex: 98">
                            <div id="vitalTagSat"></div>
                        </div>

                    </div>

                    <div class="textarea-group" style="margin-top:20px;">
                        <label>Observações do Exame Físico</label>
                        <textarea id="prontObsExame" name="observacoes_exame" class="large"
                            placeholder="Inspeção geral, ausculta, palpação, percussão, outros achados relevantes..."></textarea>
                    </div>

                </div>

                <div class="prontuario-actions">
                    <button type="button" class="menu-btn"
                        onclick="showProntuario('abaAnamnese')">← Anamnese</button>
                    <button type="submit" class="confirm-btn">💾 Salvar</button>
                    <button type="button" class="menu-btn" style="background:#2980b9;"
                        onclick="showProntuario('abaDiagnostico')">Próximo: Diagnóstico →</button>
                </div>

            </div>


            <!-- ========================
                 ABA 3 — DIAGNÓSTICO E CONDUTA (Etapa 7)
            ======================== -->

            <div id="abaDiagnostico" class="prontuario-content">

                <div class="card">
                    <h3>🔬 Diagnóstico e Conduta</h3>

                    <div class="two-columns">
                        <div class="textarea-group">
                            <label>Hipótese Diagnóstica</label>
                            <textarea id="prontHipotese" name="hipotese_diagnostica"
                                placeholder="Hipótese ou diagnóstico definitivo..."></textarea>
                        </div>

                        <div class="field-group">
                            <label>CID-10</label>
                            <input type="text" id="prontCID" name="cid10"
                                class="field" placeholder="Ex: J06.9"
                                style="margin-bottom:auto;">
                        </div>
                    </div>

                    <div class="textarea-group">
                        <label>Conduta</label>
                        <textarea id="prontConduta" name="conduta" class="large"
                            placeholder="Tratamento indicado, procedimentos, encaminhamentos..."></textarea>
                    </div>

                    <div class="textarea-group">
                        <label>Orientações ao Paciente</label>
                        <textarea id="prontOrientacoes" name="orientacoes"
                            placeholder="Recomendações, cuidados, restrições, sinais de alerta..."></textarea>
                    </div>

                </div>

                <div class="prontuario-actions">
                    <button type="button" class="menu-btn"
                        onclick="showProntuario('abaExameFisico')">← Exame Físico</button>
                    <button type="submit" class="confirm-btn">💾 Salvar</button>
                    <button type="button" class="menu-btn" style="background:#2980b9;"
                        onclick="showProntuario('abaReceita')">Próximo: Receita →</button>
                </div>

            </div>

        </form>


        <!-- ========================
             ABA 4 — RECEITA E EXAMES (Etapa 8)
        ======================== -->

        <div id="abaReceita" class="prontuario-content">

            <div class="card">
                <h3>💊 Receita Médica</h3>

                <form id="formReceita" onsubmit="salvarReceita(event)">

                    <input type="hidden" id="receitaIdConsulta" name="id_consulta">

                    <div class="textarea-group">
                        <label>Medicamentos Prescritos</label>
                        <textarea id="prontReceitaMed" name="medicamentos_receita" class="large"
                            placeholder="1) Medicamento — dose — posologia — duração&#10;2) Medicamento — dose — posologia — duração&#10;3) ..."></textarea>
                    </div>

                    <hr style="border:none;border-top:2px solid #ececec;margin:25px 0;">

                    <h3 style="margin-bottom:15px;">🔎 Solicitação de Exames</h3>

                    <div class="textarea-group">
                        <label>Exames Solicitados</label>
                        <textarea id="prontExamesSol" name="exames_solicitados" class="large"
                            placeholder="1) Hemograma completo&#10;2) Glicemia em jejum&#10;3) Colesterol total e frações&#10;4) ..."></textarea>
                    </div>

                    <div class="textarea-group">
                        <label>Observações / Justificativa Clínica</label>
                        <textarea id="prontExamesObs" name="observacoes_exames"
                            placeholder="Indicação clínica, orientações para realização..."></textarea>
                    </div>

                    <div class="textarea-group">
                        <label>Anexos (exames enviados pelo paciente)</label>
                        <div id="prontAnexos" class="field" style="min-height:44px;">
                            <span style="color:#888;">Nenhum anexo compartilhado pelo paciente.</span>
                        </div>
                    </div>

                    <div class="prontuario-actions">
                        <button type="button" class="menu-btn"
                            onclick="showProntuario('abaDiagnostico')">← Diagnóstico</button>
                        <button type="submit" class="confirm-btn">💾 Salvar Receita / Exames</button>
                        <button type="button" class="btn btn-secondary" onclick="abrirModalImpressaoReceita()">
                            🖨️ Imprimir Receita / Documentos
                        </button>
                        <button type="button" class="menu-btn" style="background:#2980b9;"
                            onclick="showProntuario('abaPosAtendimento')">Próximo: Pós-atendimento →</button>
                    </div>

                </form>

            </div>

        </div>


        <!-- ========================
             ABA 5 — PÓS-ATENDIMENTO (Etapa 9)
        ======================== -->

        <div id="abaPosAtendimento" class="prontuario-content">

            <form id="formPosAtendimento" onsubmit="salvarProntuario(event)">

                <input type="hidden" id="posIdConsulta" name="id_consulta">

                <div class="card">
                    <h3>📋 Pós-Atendimento</h3>

                    <div class="textarea-group">
                        <label>Orientações Finais</label>
                        <textarea id="prontOrientacoesPos" name="orientacoes_pos" class="large"
                            placeholder="Orientações gerais para o paciente após a consulta..."></textarea>
                    </div>

                    <div class="grid">
                        <div class="field-group">
                            <label>Necessita Retorno?</label>
                            <select id="prontRetorno" name="retorno_necessario" class="field">
                                <option value="0">Não</option>
                                <option value="1">Sim</option>
                            </select>
                        </div>

                        <div class="field-group">
                            <label>Prazo para Retorno</label>
                            <input type="text" id="prontPrazoRetorno" name="prazo_retorno"
                                class="field" placeholder="Ex: 30 dias, 2 semanas...">
                        </div>
                    </div>

                    <div class="textarea-group">
                        <label>Observações Finais</label>
                        <textarea id="prontObsFinais" name="observacoes_finais"
                            placeholder="Anotações adicionais, acompanhamento necessário..."></textarea>
                    </div>

                </div>

                <div class="prontuario-actions">
                    <button type="button" class="menu-btn"
                        onclick="showProntuario('abaReceita')">← Receita</button>
                    <button type="submit" class="confirm-btn">💾 Salvar</button>
                    <button type="button" class="menu-btn" style="background:#27ae60;"
                        id="btnFinalizarConsulta" onclick="finalizarConsulta()">
                        ✓ Finalizar Consulta
                    </button>
                </div>

            </form>

        </div>


        <!-- ========================
             ABA 6 — HISTÓRICO (Etapa 11)
        ======================== -->

        <div id="abaHistorico" class="prontuario-content">

            <div id="atdHistoricoConteudo">
                <!-- Preenchido via JS -->
            </div>

        </div>


        <!-- BARRA FIXA DE AÇÕES -->
        <div class="prontuario-barra-fixa" id="barraAcoes">
            <button type="button" class="menu-btn"
                onclick="showScreen('aguardando')">
                ← Voltar à Fila
            </button>
            <button type="button" class="btn btn-secondary" onclick="abrirModalImpressaoReceita()">
                🖨️ Imprimir Documentos
            </button>
            <button type="button" class="confirm-btn"
                onclick="salvarProntuarioGeral()">
                💾 Salvar Tudo
            </button>
            <button type="button" class="menu-btn" style="background:#27ae60;"
                id="btnFinalizarConsulta2" onclick="finalizarConsulta()">
                ✓ Finalizar Consulta
            </button>
        </div>

    </div>

  </section>


  <!-- MINHA AGENDA -->

  <section id="minhaAgenda" class="screen">

    <div class="card">

      <div class="top-bar" style="margin-bottom:20px;">
        <h3>Minha Disponibilidade</h3>
      </div>

      <!-- FORMULÁRIO -->

      <div class="agenda-form">

        <div class="field-group">
          <label>Data</label>
          <input type="date" id="agDataInicio"
            min="<?php echo date('Y-m-d'); ?>"
            max="<?php echo date('Y-m-d', strtotime('+2 months')); ?>">
        </div>

        <div class="field-group">
          <label>Hora Início</label>
          <input type="time" id="agHoraInicio">
        </div>

        <div class="field-group">
          <label>Hora Fim</label>
          <input type="time" id="agHoraFim">
        </div>

        <div class="field-group">
          <label>
            <input type="checkbox" id="agRecorrente"
              onchange="toggleRecorrencia()">
            Repetir semanalmente
          </label>
        </div>

        <div class="field-group" id="grupoSemanas" style="display:none;">
          <label>Quantas semanas?</label>
          <select id="agNumSemanas">
            <option value="2">2 semanas</option>
            <option value="3">3 semanas</option>
            <option value="4" selected>4 semanas</option>
            <option value="6">6 semanas</option>
            <option value="8">8 semanas (~2 meses)</option>
          </select>
        </div>

        <button class="confirm-btn" onclick="salvarBlocoAgenda()">
          + Adicionar
        </button>

      </div>

      <!-- TABELA DE BLOCOS -->

      <table style="margin-top:20px;">
        <thead>
          <tr>
            <th>Data</th>
            <th>Início</th>
            <th>Fim</th>
            <th></th>
          </tr>
        </thead>
        <tbody id="tabelaAgenda">
          <tr>
            <td colspan="4" style="text-align:center;color:var(--text-muted)">Carregando...</td>
          </tr>
        </tbody>
      </table>

    </div>

  </section>

</main>

<script src="assets/js/main.js"></script>
<script src="assets/js/medico.js?v=<?= filemtime(__DIR__ . '/assets/js/medico.js') ?>"></script>

</body>
</html>