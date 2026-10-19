<?php

require_once('config/auth.php');
require_once('config/database.php');

verificarLogin();

$ehSecretaria = ($_SESSION['perfil'] ?? '') === 'secretaria';

if (!$ehSecretaria) {
    verificarPerfil('admin');
}

$urlVoltar = $ehSecretaria
    ? 'secretaria.php?aba=' . (($_GET['aba'] ?? '') === 'pacientes' ? 'pacientes' : 'medicos')
    : 'admin.php?aba=usuarios';

$id_usuario = intval($_GET['id'] ?? 0);

if ($id_usuario <= 0) {
    header("Location: " . $urlVoltar);
    exit;
}


/* BUSCA O USUÁRIO */

$sql = "
SELECT *
FROM usuarios
WHERE id_usuario = ?
";

if ($ehSecretaria) {
    $sql .= " AND id_clinica = ? AND perfil IN ('medico','paciente')";
}

$stmt = $conn->prepare($sql);

if ($ehSecretaria) {
    $stmt->bind_param("ii", $id_usuario, $_SESSION['id_clinica']);
} else {
    $stmt->bind_param(
        "i",
        $id_usuario
    );
}

$stmt->execute();

$resultado = $stmt->get_result();

if ($resultado->num_rows === 0) {
    die("Usuário não encontrado.");
}

$user = $resultado->fetch_assoc();


/* BUSCA CLÍNICAS ATIVAS */

$clinicasSelect = $conn->query("
    SELECT
        id_clinica,
        nome
    FROM clinica
    WHERE status = 'ativa'
    ORDER BY nome
");

$listaClinicas = [];
while ($cl = $clinicasSelect->fetch_assoc()) {
    $listaClinicas[] = $cl;
}


/* LISTA DE ESTADOS */

$estados = [
    'AC', 'AL', 'AP', 'AM', 'BA',
    'CE', 'DF', 'ES', 'GO', 'MA',
    'MT', 'MS', 'MG', 'PA', 'PB',
    'PR', 'PE', 'PI', 'RJ', 'RN',
    'RS', 'RO', 'RR', 'SC', 'SP',
    'SE', 'TO'
];


/* BUSCA ESPECIALIDADES */

$resEspec = $conn->query("
    SELECT id_especialidade, nome
    FROM especialidades
    WHERE status = 'ativa'
    ORDER BY nome
");
$listaEspecialidades = [];
if ($resEspec) {
    while ($esp = $resEspec->fetch_assoc()) {
        $listaEspecialidades[] = $esp;
    }
}

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Editar Usuário - TechNyes</title>

<link
    rel="stylesheet"
    href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>"
>

</head>

<body>


<header>

    <div class="logo">
        Tech<span>Nyes</span>
    </div>

    <nav>

        <button
            onclick="window.location.href='<?= $urlVoltar ?>'"
        >
            Voltar
        </button>

        <button
            onclick="window.location.href='logout.php'"
        >
            Sair
        </button>

    </nav>

</header>


<div class="container">

    <div class="card">

        <h2>
            Editar Usuário —

            <span style="color:var(--gold);">
                <?= htmlspecialchars($user['nome']) ?>
            </span>
        </h2>

        <p style="margin-top:5px;color:#888;">
            Perfil: <strong><?= htmlspecialchars($user['perfil']) ?></strong>
            | Cadastrado em: <?= date('d/m/Y', strtotime($user['data_cadastro'])) ?>
        </p>

        <br>


        <form
            action="api/editar_usuario.php"
            method="POST"
        >

            <input
                type="hidden"
                name="id_usuario"
                value="<?= $user['id_usuario'] ?>"
            >

            <?php if ($ehSecretaria): ?>
            <input type="hidden" name="perfil" value="<?= htmlspecialchars($user['perfil']) ?>">
            <input type="hidden" name="voltar" value="<?= htmlspecialchars($urlVoltar) ?>">
            <?php endif; ?>


            <!-- ==============================
                 DADOS OBRIGATÓRIOS
            ============================== -->

            <h4 style="margin-bottom:15px;color:var(--gold);">
                Dados de Acesso
            </h4>

            <div class="grid">


                <!-- NOME -->

                <div class="field-group">

                    <label>
                        Nome *
                    </label>

                    <input
                        type="text"
                        name="nome"
                        class="field"
                        value="<?= htmlspecialchars($user['nome']) ?>"
                        required
                    >

                </div>


                <!-- EMAIL -->

                <div class="field-group">

                    <label>
                        E-mail *
                    </label>

                    <input
                        type="email"
                        name="email"
                        class="field"
                        value="<?= htmlspecialchars($user['email']) ?>"
                        required
                    >

                </div>


                <!-- SENHA (opcional) -->

                <div class="field-group">

                    <label>
                        Nova Senha
                        <span class="tooltip-icon">*
                            <span class="tooltip-text">Deixe em branco para manter a senha atual</span>
                        </span>
                    </label>

                    <input
                        type="password"
                        name="senha"
                        class="field"
                        placeholder="••••••••"
                    >

                </div>


                <!-- PERFIL -->

                <?php if (!$ehSecretaria): ?>
                <div class="field-group">

                    <label>
                        Perfil *
                    </label>

                    <select
                        name="perfil"
                        class="field"
                        required
                    >

                        <option
                            value="admin"
                            <?= $user['perfil'] === 'admin' ? 'selected' : '' ?>
                        >
                            Administrador
                        </option>

                        <option
                            value="secretaria"
                            <?= $user['perfil'] === 'secretaria' ? 'selected' : '' ?>
                        >
                            Secretária
                        </option>

                        <option
                            value="medico"
                            <?= $user['perfil'] === 'medico' ? 'selected' : '' ?>
                        >
                            Médico
                        </option>

                        <option
                            value="paciente"
                            <?= $user['perfil'] === 'paciente' ? 'selected' : '' ?>
                        >
                            Paciente
                        </option>

                    </select>

                </div>
                <?php endif; ?>


                <!-- STATUS -->

                <div class="field-group">

                    <label>
                        Status
                    </label>

                    <select
                        name="status"
                        class="field"
                    >

                        <option
                            value="ativo"
                            <?= $user['status'] === 'ativo' ? 'selected' : '' ?>
                        >
                            Ativo
                        </option>

                        <option
                            value="inativo"
                            <?= $user['status'] === 'inativo' ? 'selected' : '' ?>
                        >
                            Inativo
                        </option>

                    </select>

                </div>


                <!-- CLÍNICA -->

                <?php if (!$ehSecretaria): ?>
                <div class="field-group">

                    <label>
                        Clínica
                    </label>

                    <select
                        name="id_clinica"
                        class="field"
                    >

                        <option value="">
                            Sem clínica (Admin Master)
                        </option>

                        <?php foreach ($listaClinicas as $cl): ?>

                            <option
                                value="<?= $cl['id_clinica'] ?>"
                                <?= ($user['id_clinica'] ?? '') == $cl['id_clinica'] ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($cl['nome']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>
                <?php endif; ?>


            </div>


            <!-- ==============================
                 DADOS PESSOAIS
            ============================== -->

            <?php if (
                $user['perfil'] === 'secretaria' ||
                $user['perfil'] === 'medico' ||
                $user['perfil'] === 'paciente'
            ): ?>

            <br>

            <h4 style="margin-bottom:15px;color:var(--gold);">
                Dados Pessoais
            </h4>

            <div class="grid">


                <!-- CPF -->

                <div class="field-group">

                    <label>
                        CPF
                    </label>

                    <input
                        type="text"
                        name="cpf"
                        class="field mask-cpf"
                        maxlength="14"
                        placeholder="XXX.XXX.XXX-XX"
                        value="<?= htmlspecialchars($user['cpf'] ?? '') ?>"
                    >

                </div>


                <!-- RG -->

                <div class="field-group">

                    <label>
                        RG
                    </label>

                    <input
                        type="text"
                        name="rg"
                        class="field"
                        maxlength="20"
                        value="<?= htmlspecialchars($user['rg'] ?? '') ?>"
                    >

                </div>


                <!-- DATA DE NASCIMENTO -->

                <div class="field-group">

                    <label>
                        Data de Nascimento
                    </label>

                    <input
                        type="date"
                        name="data_nascimento"
                        class="field"
                        value="<?= htmlspecialchars($user['data_nascimento'] ?? '') ?>"
                    >

                </div>


                <!-- SEXO -->

                <div class="field-group">

                    <label>
                        Sexo
                    </label>

                    <select
                        name="sexo"
                        class="field"
                    >

                        <option value="">
                            Selecione
                        </option>

                        <option
                            value="M"
                            <?= ($user['sexo'] ?? '') === 'M' ? 'selected' : '' ?>
                        >
                            Masculino
                        </option>

                        <option
                            value="F"
                            <?= ($user['sexo'] ?? '') === 'F' ? 'selected' : '' ?>
                        >
                            Feminino
                        </option>

                    </select>

                </div>


                <!-- TELEFONE -->

                <div class="field-group">

                    <label>
                        Telefone
                    </label>

                    <input
                        type="text"
                        name="telefone"
                        class="field mask-telefone"
                        placeholder="(XX) XXXXX-XXXX"
                        value="<?= htmlspecialchars($user['telefone'] ?? '') ?>"
                    >

                </div>


                <!-- CELULAR -->

                <div class="field-group">

                    <label>
                        Celular
                    </label>

                    <input
                        type="text"
                        name="celular"
                        class="field mask-telefone"
                        placeholder="(XX) XXXXX-XXXX"
                        value="<?= htmlspecialchars($user['celular'] ?? '') ?>"
                    >

                </div>


            </div>

            <?php endif; ?>


            <!-- ==============================
                 DADOS DO MÉDICO
            ============================== -->

            <?php if ($user['perfil'] === 'medico'): ?>

            <br>

            <h4 style="margin-bottom:15px;color:var(--gold);">
                Dados Profissionais (Médico)
            </h4>

            <div class="grid">


                <!-- CRM -->

                <div class="field-group">

                    <label>
                        CRM
                    </label>

                    <input
                        type="text"
                        name="crm"
                        class="field"
                        maxlength="20"
                        value="<?= htmlspecialchars($user['crm'] ?? '') ?>"
                    >

                </div>


                <!-- UF CRM -->

                <div class="field-group">

                    <label>
                        UF do CRM
                    </label>

                    <select
                        name="uf_crm"
                        class="field"
                    >

                        <option value="">
                            Selecione
                        </option>

                        <?php foreach ($estados as $uf): ?>

                            <option
                                value="<?= $uf ?>"
                                <?= ($user['uf_crm'] ?? '') === $uf ? 'selected' : '' ?>
                            >
                                <?= $uf ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- ESPECIALIDADE -->

                <div class="field-group">

                    <label>
                        Especialidade
                    </label>

                    <select
                        name="especialidade"
                        class="field"
                    >
                        <option value="">Selecione...</option>
                        <?php foreach ($listaEspecialidades as $esp): ?>
                            <option value="<?= htmlspecialchars($esp['nome']) ?>"
                                <?= ($user['especialidade'] ?? '') === $esp['nome'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($esp['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                </div>


                <!-- HORÁRIO INÍCIO -->

                <div class="field-group">

                    <label>
                        Horário Início
                    </label>

                    <input
                        type="time"
                        name="horario_inicio"
                        class="field"
                        value="<?= htmlspecialchars($user['horario_inicio'] ?? '') ?>"
                    >

                </div>


                <!-- HORÁRIO FIM -->

                <div class="field-group">

                    <label>
                        Horário Fim
                    </label>

                    <input
                        type="time"
                        name="horario_fim"
                        class="field"
                        value="<?= htmlspecialchars($user['horario_fim'] ?? '') ?>"
                    >

                </div>


                <!-- DURAÇÃO CONSULTA -->

                <div class="field-group">

                    <label>
                        Duração da Consulta (min)
                    </label>

                    <input
                        type="number"
                        name="duracao_consulta"
                        class="field"
                        value="<?= htmlspecialchars($user['duracao_consulta'] ?? '30') ?>"
                        min="5"
                        max="120"
                    >

                </div>


            </div>

            <?php endif; ?>


            <!-- ==============================
                 DADOS DO PACIENTE
            ============================== -->

            <?php if ($user['perfil'] === 'paciente'): ?>

            <br>

            <h4 style="margin-bottom:15px;color:var(--gold);">
                Endereço
            </h4>

            <div class="grid">


                <!-- CEP -->

                <div class="field-group">

                    <label>
                        CEP
                    </label>

                    <input
                        type="text"
                        name="cep"
                        class="field mask-cep"
                        maxlength="9"
                        placeholder="XXXXX-XXX"
                        value="<?= htmlspecialchars($user['cep'] ?? '') ?>"
                    >

                </div>


                <!-- ENDEREÇO -->

                <div class="field-group">

                    <label>
                        Endereço
                    </label>

                    <input
                        type="text"
                        name="endereco"
                        class="field"
                        value="<?= htmlspecialchars($user['endereco'] ?? '') ?>"
                    >

                </div>


                <!-- NÚMERO -->

                <div class="field-group">

                    <label>
                        Número
                    </label>

                    <input
                        type="text"
                        name="numero"
                        class="field"
                        value="<?= htmlspecialchars($user['numero'] ?? '') ?>"
                    >

                </div>


                <!-- COMPLEMENTO -->

                <div class="field-group">

                    <label>
                        Complemento
                    </label>

                    <input
                        type="text"
                        name="complemento"
                        class="field"
                        value="<?= htmlspecialchars($user['complemento'] ?? '') ?>"
                    >

                </div>


                <!-- BAIRRO -->

                <div class="field-group">

                    <label>
                        Bairro
                    </label>

                    <input
                        type="text"
                        name="bairro"
                        class="field"
                        value="<?= htmlspecialchars($user['bairro'] ?? '') ?>"
                    >

                </div>


                <!-- CIDADE -->

                <div class="field-group">

                    <label>
                        Cidade
                    </label>

                    <input
                        type="text"
                        name="cidade"
                        class="field"
                        value="<?= htmlspecialchars($user['cidade'] ?? '') ?>"
                    >

                </div>


                <!-- ESTADO -->

                <div class="field-group">

                    <label>
                        Estado
                    </label>

                    <select
                        name="estado"
                        class="field"
                    >

                        <option value="">
                            Selecione
                        </option>

                        <?php foreach ($estados as $uf): ?>

                            <option
                                value="<?= $uf ?>"
                                <?= ($user['estado'] ?? '') === $uf ? 'selected' : '' ?>
                            >
                                <?= $uf ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


            </div>


            <br>

            <h4 style="margin-bottom:15px;color:var(--gold);">
                Convênio
            </h4>

            <div class="grid">


                <!-- CONVÊNIO -->

                <div class="field-group">

                    <label>
                        Convênio
                    </label>

                    <input
                        type="text"
                        name="convenio"
                        class="field"
                        value="<?= htmlspecialchars($user['convenio'] ?? '') ?>"
                    >

                </div>


                <!-- NÚMERO CARTEIRINHA -->

                <div class="field-group">

                    <label>
                        Nº Carteirinha
                    </label>

                    <input
                        type="text"
                        name="numero_carteirinha"
                        class="field"
                        value="<?= htmlspecialchars($user['numero_carteirinha'] ?? '') ?>"
                    >

                </div>


            </div>


            <br>

            <h4 style="margin-bottom:15px;color:var(--gold);">
                Contato de Emergência
            </h4>

            <div class="grid">


                <!-- CONTATO EMERGÊNCIA NOME -->

                <div class="field-group">

                    <label>
                        Nome do Contato
                    </label>

                    <input
                        type="text"
                        name="contato_emergencia_nome"
                        class="field"
                        value="<?= htmlspecialchars($user['contato_emergencia_nome'] ?? '') ?>"
                    >

                </div>


                <!-- CONTATO EMERGÊNCIA TELEFONE -->

                <div class="field-group">

                    <label>
                        Telefone do Contato
                    </label>

                    <input
                        type="text"
                        name="contato_emergencia_telefone"
                        class="field mask-telefone"
                        placeholder="(XX) XXXXX-XXXX"
                        value="<?= htmlspecialchars($user['contato_emergencia_telefone'] ?? '') ?>"
                    >

                </div>


            </div>

            <?php endif; ?>


            <!-- ==============================
                 BOTÕES
            ============================== -->

            <br>

            <button
                type="submit"
                class="confirm-btn"
            >
                Salvar Alterações
            </button>


            <button
                type="button"
                class="menu-btn"
                onclick="window.location.href='<?= $urlVoltar ?>'"
            >
                Cancelar
            </button>

        </form>

    </div>

</div>


<!-- JS para máscaras e utilitários -->
<script src="assets/js/main.js"></script>
<script src="assets/js/admin.js"></script>

</body>

</html>
