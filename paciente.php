<?php

require_once('config/auth.php');
require_once('config/database.php');

verificarPerfil('paciente');

$nomePaciente = $_SESSION['nome'];
$id_paciente  = $_SESSION['id_usuario'];
$id_clinica   = $_SESSION['id_clinica'];

/* Dados do paciente */
$stmt = $conn->prepare("SELECT * FROM usuarios WHERE id_usuario = ?");
$stmt->bind_param("i", $id_paciente);
$stmt->execute();
$paciente = $stmt->get_result()->fetch_assoc();

/* Especialidades disponíveis */
$resEspec = $conn->query("
    SELECT id_especialidade, nome
    FROM especialidades
    WHERE status = 'ativa'
    ORDER BY nome
");
$listaEspecialidades = [];
while ($esp = $resEspec->fetch_assoc()) {
    $listaEspecialidades[] = $esp;
}

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Painel do Paciente - TechNyes</title>

<link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>">

</head>

<body>

<header>

    <div class="logo">
        <a href="paciente.php">
            Tech<span>Nyes</span>
        </a>
    </div>

    <nav>

        <button onclick="showPacienteTab('meusdados',event)" class="nav-active">
            Meus Dados
        </button>

        <button onclick="showPacienteTab('agendamentos',event)">
            Agendar Consulta
        </button>

        <button onclick="showPacienteTab('minhasConsultas',event)">
            Minhas Consultas
        </button>

        <button onclick="showPacienteTab('minhasReceitas',event)">
            Minhas Prescrições
        </button>

        <button onclick="showPacienteTab('meusArquivos',event)">
            Meus Arquivos
        </button>

        <button onclick="window.location.href='logout.php'">
            Sair
        </button>

    </nav>

</header>

<div class="container">

    <div class="top-bar">

        <h2>

            Bem-vindo,

            <span style="color:var(--gold);">

                <?php echo htmlspecialchars($nomePaciente); ?>

            </span>

        </h2>

    </div>

</div>

    <!-- ==============================
         MEUS DADOS
    ============================== -->

<section id="meusdados"
    class="paciente-tab active">

    <div class="card">
        <h3>Meus Dados</h3>
        <div class="grid">

            <div class="field-group">
                <label>Nome</label>
                <div class="field"><?= htmlspecialchars($paciente['nome'] ?? '—') ?></div>
            </div>

            <div class="field-group">
                <label>CPF</label>
                <div class="field"><?= htmlspecialchars($paciente['cpf'] ?? '—') ?></div>
            </div>

            <div class="field-group">
                <label>Sexo</label>
                <div class="field"><?php
                    $sexoMap = ['M' => 'Masculino', 'F' => 'Feminino'];
                    echo $sexoMap[$paciente['sexo'] ?? ''] ?? ($paciente['sexo'] ?? '—');
                ?></div>
            </div>

            <div class="field-group">
                <label>Data de Nascimento</label>
                <div class="field"><?php
                    if (!empty($paciente['data_nascimento'])) {
                        $p = explode('-', $paciente['data_nascimento']);
                        echo $p[2] . '/' . $p[1] . '/' . $p[0];
                    } else { echo '—'; }
                ?></div>
            </div>

            <div class="field-group">
                <label>Telefone</label>
                <div class="field"><?= htmlspecialchars($paciente['telefone'] ?? $paciente['celular'] ?? '—') ?></div>
            </div>

            <div class="field-group">
                <label>E-mail</label>
                <div class="field"><?= htmlspecialchars($paciente['email'] ?? '—') ?></div>
            </div>

            <div class="field-group">
                <label>Convênio</label>
                <div class="field"><?= htmlspecialchars($paciente['convenio'] ?? '—') ?></div>
            </div>

            <div class="field-group">
                <label>Nº Carteirinha</label>
                <div class="field"><?= htmlspecialchars($paciente['numero_carteirinha'] ?? '—') ?></div>
            </div>

        </div>
    </div>

</section>


    <!-- ==============================
         AGENDAR CONSULTA
    ============================== -->

<section id="agendamentos" class="paciente-tab">

    <div class="card">

        <h3>📅 Agendar Consulta</h3>

        <div class="grid">

            <!-- ESPECIALIDADE -->
            <div class="field-group">
                <label>Especialidade *</label>
                <select id="agEspecialidade" class="field" onchange="carregarMedicosEspecialidade()">
                    <option value="">Selecione a especialidade...</option>
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
                <select id="agMedico" class="field" onchange="carregarDatasDisponiveis()" disabled>
                    <option value="">Selecione a especialidade primeiro...</option>
                </select>
            </div>

            <!-- DATA -->
            <div class="field-group">
                <label>Data *</label>
                <select id="agData" class="field" onchange="carregarHorariosDisponiveis()" disabled>
                    <option value="">Selecione o médico primeiro...</option>
                </select>
            </div>

            <!-- HORÁRIO -->
            <div class="field-group">
                <label>Horário *</label>
                <select id="agHorario" class="field" disabled>
                    <option value="">Selecione a data primeiro...</option>
                </select>
            </div>

        </div>

        <!-- MOTIVO -->
        <div class="textarea-group" style="margin-top:15px;">
            <label>Motivo da Consulta</label>
            <textarea id="agMotivo" placeholder="Descreva brevemente o motivo da consulta..."
                style="width:100%;min-height:80px;padding:10px 12px;border:2px solid #e0e0e0;border-radius:10px;font-family:inherit;font-size:.95rem;resize:vertical;box-sizing:border-box;"></textarea>
        </div>

        <br>

        <button class="confirm-btn" onclick="agendarConsulta()" id="btnAgendar" disabled>
            ✓ Agendar Consulta
        </button>

    </div>

</section>


    <!-- ==============================
         MINHAS CONSULTAS
    ============================== -->

<section id="minhasConsultas" class="paciente-tab">

    <div class="card">

        <div class="top-bar">
            <h3>📋 Minhas Consultas</h3>
        </div>

        <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Horário</th>
                    <th>Médico</th>
                    <th>Especialidade</th>
                    <th>Motivo</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody id="tbodyConsultas">
                <tr>
                    <td colspan="7" style="text-align:center;color:#888;">
                        Carregando...
                    </td>
                </tr>
            </tbody>
        </table>
        </div>

    </div>

</section>


    <!-- ==============================
         MINHAS RECEITAS & DOCUMENTOS
    ============================== -->

<section id="minhasReceitas" class="paciente-tab">

    <div class="card">

        <div class="top-bar">
            <h3>💊 Minhas Prescrições e Exames</h3>
        </div>

        <p style="color:var(--text-muted);font-size:0.9rem;margin-bottom:20px;">
            Abaixo estão as receitas médicas e solicitações de exames disponibilizadas pelos seus médicos após consultas finalizadas.
        </p>

        <div id="containerReceitasPaciente">
            <p style="color:#888;text-align:center;padding:30px;">Carregando suas prescrições...</p>
        </div>

    </div>

</section>


    <!-- ==============================
         MEUS ARQUIVOS
    ============================== -->

<section id="meusArquivos" class="paciente-tab">

    <div class="card">

        <h3 id="arqTituloForm">📎 Enviar Arquivo</h3>

        <input type="hidden" id="arqId" value="">

        <div class="grid">

            <div class="field-group">
                <label for="arqTitulo">Título *</label>
                <input type="text" id="arqTitulo" class="field" maxlength="150"
                    placeholder="Ex: Hemograma completo - Out/2026">
            </div>

            <div class="field-group" id="arqGrupoArquivo">
                <label for="arqArquivo">Arquivo * (PDF, imagem ou DOC — até 10 MB)</label>
                <input type="file" id="arqArquivo" class="field"
                    accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx">
            </div>

            <div class="field-group">
                <label for="arqConsulta">Vincular a uma consulta (opcional)</label>
                <select id="arqConsulta" class="field">
                    <option value="">Não vincular</option>
                </select>
            </div>

        </div>

        <p style="color:#888;font-size:.85rem;margin-top:10px;">
            Ao vincular a uma consulta, o arquivo é compartilhado com o médico responsável
            e aparece em "Anexos" no prontuário.
        </p>

        <br>

        <button class="confirm-btn" id="btnArqSalvar" onclick="salvarArquivo()">⬆ Enviar</button>
        <button class="menu-btn" id="btnArqCancelar" style="display:none;" onclick="cancelarEdicaoArquivo()">Cancelar edição</button>

    </div>

    <div class="card">

        <h3>📁 Meus Arquivos</h3>

        <table>
            <thead>
                <tr>
                    <th>Título</th>
                    <th>Arquivo</th>
                    <th>Consulta vinculada</th>
                    <th>Enviado em</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody id="tbodyArquivos">
                <tr>
                    <td colspan="5" style="text-align:center;color:#888;">Carregando...</td>
                </tr>
            </tbody>
        </table>

    </div>

</section>


<script src="assets/js/main.js"></script>
<script src="assets/js/paciente.js?v=<?= filemtime(__DIR__ . '/assets/js/paciente.js') ?>"></script>
</body>
</html>
