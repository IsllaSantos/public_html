
<?php

require_once('config/auth.php');

verificarPerfil('admin');

$nomeAdmin = $_SESSION['nome'];

/* ==============================
   ABA INICIAL
============================== */

$abaInicial = $_GET['aba'] ?? 'dashboard';


/* ==============================
   MENSAGEM DE SUCESSO
============================== */

$mensagemSucesso = $_SESSION['mensagem_sucesso'] ?? '';

unset($_SESSION['mensagem_sucesso']);


/* ==============================
   BANCO DE DADOS
============================== */

require_once('config/database.php');


/* ==============================
   DASHBOARD — DADOS REAIS
============================== */

$totalClinicas = 0;
$totalMedicos = 0;
$totalPacientes = 0;
$totalUsuarios = 0;

$res = $conn->query("
    SELECT COUNT(*) AS total
    FROM clinica
    WHERE status = 'ativa'
");
if ($row = $res->fetch_assoc()) {
    $totalClinicas = $row['total'];
}

$res = $conn->query("
    SELECT COUNT(*) AS total
    FROM usuarios
    WHERE perfil = 'medico'
    AND status = 'ativo'
");
if ($row = $res->fetch_assoc()) {
    $totalMedicos = $row['total'];
}

$res = $conn->query("
    SELECT COUNT(*) AS total
    FROM usuarios
    WHERE perfil = 'paciente'
    AND status = 'ativo'
");
if ($row = $res->fetch_assoc()) {
    $totalPacientes = $row['total'];
}

$res = $conn->query("
    SELECT COUNT(*) AS total
    FROM usuarios
    WHERE status = 'ativo'
");
if ($row = $res->fetch_assoc()) {
    $totalUsuarios = $row['total'];
}


/* ==============================
   BUSCA USUÁRIOS
============================== */

$usuarios = $conn->query("
    SELECT
        u.id_usuario,
        u.nome,
        u.email,
        u.perfil,
        u.status,
        c.nome AS nome_clinica
    FROM usuarios u
    LEFT JOIN clinica c
        ON u.id_clinica = c.id_clinica
    ORDER BY u.nome
");


/* ==============================
   BUSCA CLÍNICAS
============================== */

$clinicas = $conn->query("
    SELECT
        id_clinica,
        nome,
        cnpj,
        telefone,
        email,
        cidade,
        estado,
        status,
        data_cadastro
    FROM clinica
    ORDER BY nome
");


/* ==============================
   CLÍNICAS PARA SELECT
   (usado no form de usuários)
============================== */

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


/* ==============================
   LISTA DE ESTADOS
============================== */

$estados = [
    'AC', 'AL', 'AP', 'AM', 'BA',
    'CE', 'DF', 'ES', 'GO', 'MA',
    'MT', 'MS', 'MG', 'PA', 'PB',
    'PR', 'PE', 'PI', 'RJ', 'RN',
    'RS', 'RO', 'RR', 'SC', 'SP',
    'SE', 'TO'
];


/* ==============================
   BUSCA PLANOS
============================== */

$planos = $conn->query("
    SELECT
        p.*,
        (SELECT COUNT(*) FROM assinaturas a
         WHERE a.id_plano = p.id_plano
         AND a.status = 'ativa') AS total_assinaturas
    FROM planos p
    ORDER BY p.valor ASC
");

$listaPlanos = [];
while ($pl = $planos->fetch_assoc()) {
    $listaPlanos[] = $pl;
}


/* ==============================
   PLANOS PARA SELECT
============================== */

$planosSelect = [];
$resPlanos = $conn->query("
    SELECT id_plano, nome, valor
    FROM planos
    WHERE status = 'ativo'
    ORDER BY valor
");
while ($ps = $resPlanos->fetch_assoc()) {
    $planosSelect[] = $ps;
}


/* ==============================
   BUSCA ASSINATURAS
============================== */

$assinaturas = $conn->query("
    SELECT
        a.*,
        c.nome AS nome_clinica,
        p.nome AS nome_plano
    FROM assinaturas a
    INNER JOIN clinica c ON a.id_clinica = c.id_clinica
    INNER JOIN planos p ON a.id_plano = p.id_plano
    ORDER BY a.data_vencimento ASC
");


/* ==============================
   MÉTRICAS FINANCEIRAS
============================== */

$faturamentoMensal = 0;
$totalAssinaturasAtivas = 0;
$totalInadimplentes = 0;
$totalProxVencimentos = 0;

$res = $conn->query("
    SELECT
        COALESCE(SUM(valor), 0) AS total
    FROM assinaturas
    WHERE status = 'ativa'
");
if ($row = $res->fetch_assoc()) {
    $faturamentoMensal = $row['total'];
}

$res = $conn->query("
    SELECT COUNT(*) AS total
    FROM assinaturas
    WHERE status = 'ativa'
");
if ($row = $res->fetch_assoc()) {
    $totalAssinaturasAtivas = $row['total'];
}

$res = $conn->query("
    SELECT COUNT(*) AS total
    FROM assinaturas
    WHERE status = 'ativa'
    AND data_vencimento < CURDATE()
");
if ($row = $res->fetch_assoc()) {
    $totalInadimplentes = $row['total'];
}

$res = $conn->query("
    SELECT COUNT(*) AS total
    FROM assinaturas
    WHERE status = 'ativa'
    AND data_vencimento >= CURDATE()
    AND data_vencimento <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
");
if ($row = $res->fetch_assoc()) {
    $totalProxVencimentos = $row['total'];
}


/* ==============================
   BUSCA ESPECIALIDADES
============================== */

$especialidades = $conn->query("
    SELECT id_especialidade, nome
    FROM especialidades
    WHERE status = 'ativa'
    ORDER BY nome
");

$listaEspecialidades = [];
if ($especialidades) {
    while ($esp = $especialidades->fetch_assoc()) {
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

<title>Administrador - TechNyes</title>

<link
    rel="stylesheet"
    href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>"
>

</head>


<body>


<!-- ==============================
     HEADER
============================== -->

<header>

    <div class="logo">
        Tech<span>Nyes</span>
    </div>


    <nav>

        <button onclick="showAdminTab('dashboard',event)" class="<?= $abaInicial === 'dashboard' ? 'active' : '' ?>">
            Dashboard
        </button>

        <button onclick="showAdminTab('clinicas',event)" class="<?= $abaInicial === 'clinicas' ? 'active' : '' ?>">
            Clínicas
        </button>

        <button onclick="showAdminTab('usuarios',event)" class="<?= $abaInicial === 'usuarios' ? 'active' : '' ?>">
            Usuários
        </button>

        <button onclick="showAdminTab('planos',event)" class="<?= $abaInicial === 'planos' ? 'active' : '' ?>">
            Planos
        </button>

        <button onclick="showAdminTab('financeiro',event)" class="<?= $abaInicial === 'financeiro' ? 'active' : '' ?>">
            Financeiro
        </button>

        <button onclick="showAdminTab('especialidades',event)" class="<?= $abaInicial === 'especialidades' ? 'active' : '' ?>">
            Especialidades
        </button>

        <button onclick="showAdminTab('logs',event)" class="<?= $abaInicial === 'logs' ? 'active' : '' ?>">
            Logs
        </button>

        <button onclick="window.location.href='logout.php'">
            Sair
        </button>

    </nav>

</header>



<!-- ==============================
     TÍTULO
============================== -->

<div class="container">

    <div class="top-bar">

        <h2>

            Painel Administrativo

            <span style="color:var(--gold);">

                <?= htmlspecialchars($nomeAdmin) ?>

            </span>

        </h2>

    </div>

</div>



<!-- ==============================
     DASHBOARD
============================== -->

<section
    id="dashboard"
    class="admin-tab <?= $abaInicial === 'dashboard' ? 'active' : '' ?>"
>

    <div class="container">

        <div class="grid">


            <div class="card">

                <h3>
                    Clínicas Ativas
                </h3>

                <h1>
                    <?= $totalClinicas ?>
                </h1>

            </div>


            <div class="card">

                <h3>
                    Médicos
                </h3>

                <h1>
                    <?= $totalMedicos ?>
                </h1>

            </div>


            <div class="card">

                <h3>
                    Pacientes
                </h3>

                <h1>
                    <?= $totalPacientes ?>
                </h1>

            </div>


            <div class="card">

                <h3>
                    Total de Usuários
                </h3>

                <h1>
                    <?= $totalUsuarios ?>
                </h1>

            </div>


        </div>

    </div>

</section>



<!-- ==============================
     CLÍNICAS
============================== -->

<section
    id="clinicas"
    class="admin-tab <?= $abaInicial === 'clinicas' ? 'active' : '' ?>"
>


    <div class="container">


        <!-- MENSAGEM -->

        <?php if (!empty($mensagemSucesso)): ?>

            <div class="card">

                <p
                    style="color:green;font-weight:bold;"
                >

                    <?= htmlspecialchars($mensagemSucesso) ?>

                </p>

            </div>

        <?php endif; ?>


        <!-- NOVA CLÍNICA -->

        <div class="card">


            <div class="top-bar">

                <h3>
                    Nova Clínica
                </h3>

            </div>


            <form
                action="api/cadastro_clinica.php"
                method="POST"
            >


                <div class="grid">


                    <!-- NOME -->

                    <div class="field-group">

                        <label>
                            Nome da Clínica *
                        </label>

                        <input
                            type="text"
                            name="nome"
                            class="field"
                            required
                        >

                    </div>


                    <!-- CNPJ -->

                    <div class="field-group">

                        <label>
                            CNPJ *
                        </label>

                        <input
                            type="text"
                            name="cnpj"
                            class="field mask-cnpj"
                            maxlength="18"
                            placeholder="XX.XXX.XXX/XXXX-XX"
                            required
                        >

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
                        >

                    </div>


                    <!-- EMAIL -->

                    <div class="field-group">

                        <label>
                            E-mail
                        </label>

                        <input
                            type="email"
                            name="email"
                            class="field"
                        >

                    </div>


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

                                <option value="<?= $uf ?>">
                                    <?= $uf ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                </div>


                <br>


                <button
                    type="submit"
                    class="confirm-btn"
                >

                    Cadastrar Clínica

                </button>


            </form>

        </div>



        <!-- LISTA DE CLÍNICAS -->

        <div class="card">


            <div class="top-bar">

                <h3>
                    Clínicas Cadastradas
                </h3>

            </div>


            <table>


                <thead>

                    <tr>

                        <th>ID</th>

                        <th>Nome</th>

                        <th>CNPJ</th>

                        <th>Telefone</th>

                        <th>E-mail</th>

                        <th>Cidade</th>

                        <th>Estado</th>

                        <th>Status</th>

                        <th>Ações</th>

                    </tr>

                </thead>


                <tbody>


                <?php while($c = $clinicas->fetch_assoc()): ?>


                    <tr>


                        <td>

                            <?= $c['id_clinica'] ?>

                        </td>


                        <td>

                            <?= htmlspecialchars($c['nome']) ?>

                        </td>


                        <td>

                            <?= htmlspecialchars($c['cnpj'] ?? '') ?>

                        </td>


                        <td>

                            <?= htmlspecialchars($c['telefone'] ?? '') ?>

                        </td>


                        <td>

                            <?= htmlspecialchars($c['email'] ?? '') ?>

                        </td>


                        <td>

                            <?= htmlspecialchars($c['cidade'] ?? '') ?>

                        </td>


                        <td>

                            <?= htmlspecialchars($c['estado'] ?? '') ?>

                        </td>


                        <td>


                            <?php if($c['status'] === 'ativa'): ?>

                                <span class="status confirmed">

                                    Ativa

                                </span>

                            <?php else: ?>

                                <span class="status">

                                    Inativa

                                </span>

                            <?php endif; ?>


                        </td>


                        <td>


                            <button
                                class="menu-btn"
                                type="button"
                                onclick="window.location.href='editar_clinica.php?id=<?= $c['id_clinica'] ?>'"
                            >

                                Editar

                            </button>


                            <button
                                class="menu-btn"
                                type="button"
                                style="background:#c0392b;margin-left:5px;"
                                onclick="confirmarExclusao(<?= $c['id_clinica'] ?>, '<?= addslashes(htmlspecialchars($c['nome'])) ?>')"
                            >

                                Excluir

                            </button>


                        </td>


                    </tr>


                <?php endwhile; ?>


                </tbody>


            </table>


        </div>


    </div>


</section>



<!-- ==============================
     USUÁRIOS
============================== -->

<section
    id="usuarios"
    class="admin-tab <?= $abaInicial === 'usuarios' ? 'active' : '' ?>"
>


    <div class="container">


        <!-- MENSAGEM -->

        <?php if ($abaInicial === 'usuarios' && !empty($mensagemSucesso)): ?>

            <div class="card">

                <p
                    style="color:green;font-weight:bold;"
                >

                    <?= htmlspecialchars($mensagemSucesso) ?>

                </p>

            </div>

        <?php endif; ?>


        <!-- NOVO USUÁRIO -->

        <div class="card">


            <h3>
                Novo Usuário
            </h3>


            <form
                action="api/cadastro_usuario.php"
                method="POST"
            >


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
                            required
                        >

                    </div>


                    <!-- SENHA -->

                    <div class="field-group">

                        <label>
                            Senha *
                        </label>

                        <input
                            type="password"
                            name="senha"
                            class="field"
                            required
                        >

                    </div>


                    <!-- PERFIL -->

                    <div class="field-group">

                        <label>
                            Perfil *
                        </label>

                        <select
                            name="perfil"
                            id="select-perfil"
                            class="field"
                            onchange="alternarCamposPerfil()"
                            required
                        >

                            <option value="">
                                Selecione
                            </option>

                            <option value="admin">
                                Administrador
                            </option>

                            <option value="secretaria">
                                Secretária
                            </option>

                            <option value="medico">
                                Médico
                            </option>

                            <option value="paciente">
                                Paciente
                            </option>

                        </select>

                    </div>


                    <!-- CLÍNICA
                         Aparece para: secretaria, medico, paciente -->

                    <div
                        class="field-group campos-clinica"
                        style="display:none;"
                    >

                        <label>
                            Clínica *
                        </label>

                        <select
                            name="id_clinica"
                            class="field"
                        >

                            <option value="">
                                Selecione a Clínica
                            </option>

                            <?php foreach ($listaClinicas as $cl): ?>

                                <option
                                    value="<?= $cl['id_clinica'] ?>"
                                >
                                    <?= htmlspecialchars($cl['nome']) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                </div>


                <!-- ==============================
                     DADOS PESSOAIS
                     Aparece para: secretaria, medico, paciente
                ============================== -->

                <div
                    id="campos-pessoais"
                    style="display:none;"
                >

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

                                <option value="M">
                                    Masculino
                                </option>

                                <option value="F">
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
                            >

                        </div>


                    </div>

                </div>


                <!-- ==============================
                     DADOS DO MÉDICO
                     Aparece para: medico
                ============================== -->

                <div
                    id="campos-medico"
                    style="display:none;"
                >

                    <br>

                    <h4 style="margin-bottom:15px;color:var(--gold);">
                        Dados Profissionais (Médico)
                    </h4>

                    <div class="grid">


                        <!-- CRM -->

                        <div class="field-group">

                            <label>
                                CRM *
                            </label>

                            <input
                                type="text"
                                name="crm"
                                class="field"
                                maxlength="20"
                                placeholder="Ex: 123456"
                            >

                        </div>


                        <!-- UF CRM -->

                        <div class="field-group">

                            <label>
                                UF do CRM *
                            </label>

                            <select
                                name="uf_crm"
                                class="field"
                            >

                                <option value="">
                                    Selecione
                                </option>

                                <?php foreach ($estados as $uf): ?>

                                    <option value="<?= $uf ?>">
                                        <?= $uf ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <!-- ESPECIALIDADE -->

                        <div class="field-group">

                            <label>
                                Especialidade *
                            </label>

                            <select
                                name="especialidade"
                                id="select-especialidade"
                                class="field"
                            >
                                <option value="">Selecione...</option>
                                <?php foreach ($listaEspecialidades as $esp): ?>
                                    <option value="<?= htmlspecialchars($esp['nome']) ?>">
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
                                value="30"
                                min="5"
                                max="120"
                            >

                        </div>


                    </div>

                </div>


                <!-- ==============================
                     DADOS DO PACIENTE
                     Aparece para: paciente
                ============================== -->

                <div
                    id="campos-paciente"
                    style="display:none;"
                >

                    <br>

                    <h4 style="margin-bottom:15px;color:var(--gold);">
                        Endereço do Paciente
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

                                    <option value="<?= $uf ?>">
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
                                placeholder="Ex: Unimed"
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
                            >

                        </div>


                    </div>

                </div>


                <br>


                <button
                    type="submit"
                    class="confirm-btn"
                >

                    Cadastrar Usuário

                </button>


            </form>


        </div>



        <!-- LISTA DE USUÁRIOS -->

        <div class="card">


            <h3>
                Usuários Cadastrados
            </h3>


            <table>


                <thead>

                    <tr>

                        <th>ID</th>

                        <th>Nome</th>

                        <th>E-mail</th>

                        <th>Perfil</th>

                        <th>Clínica</th>

                        <th>Status</th>

                        <th>Ações</th>

                    </tr>

                </thead>


                <tbody>


                <?php while($u = $usuarios->fetch_assoc()): ?>


                    <tr>


                        <td>

                            <?= $u['id_usuario'] ?>

                        </td>


                        <td>

                            <?= htmlspecialchars($u['nome']) ?>

                        </td>


                        <td>

                            <?= htmlspecialchars($u['email']) ?>

                        </td>


                        <td>

                            <?= htmlspecialchars($u['perfil']) ?>

                        </td>


                        <td>

                            <?= $u['nome_clinica']
                                ? htmlspecialchars($u['nome_clinica'])
                                : 'Admin Master'
                            ?>

                        </td>


                        <td>

                            <?php if($u['status'] === 'ativo'): ?>

                                <span class="status confirmed">

                                    Ativo

                                </span>

                            <?php else: ?>

                                <span class="status">

                                    Inativo

                                </span>

                            <?php endif; ?>

                        </td>


                        <td>

                            <button
                                class="menu-btn"
                                type="button"
                                onclick="window.location.href='editar_usuario.php?id=<?= $u['id_usuario'] ?>'"
                            >

                                Editar

                            </button>


                            <button
                                class="menu-btn"
                                type="button"
                                style="background:#c0392b;margin-left:5px;"
                                onclick="confirmarExclusaoUsuario(<?= $u['id_usuario'] ?>, '<?= addslashes(htmlspecialchars($u['nome'])) ?>')"
                            >

                                Excluir

                            </button>

                        </td>


                    </tr>


                <?php endwhile; ?>


                </tbody>


            </table>


        </div>


    </div>


</section>



<!-- ==============================
     PLANOS
============================== -->

<section
    id="planos"
    class="admin-tab <?= $abaInicial === 'planos' ? 'active' : '' ?>"
>

    <div class="container">


        <!-- MENSAGEM -->

        <?php if ($abaInicial === 'planos' && !empty($mensagemSucesso)): ?>

            <div class="card">

                <p
                    style="color:green;font-weight:bold;"
                >

                    <?= htmlspecialchars($mensagemSucesso) ?>

                </p>

            </div>

        <?php endif; ?>


        <!-- NOVO PLANO -->

        <div class="card">

            <div class="top-bar">

                <h3>
                    Novo Plano
                </h3>

            </div>

            <form
                action="api/cadastro_plano.php"
                method="POST"
            >

                <div class="grid">

                    <div class="field-group">

                        <label>
                            Nome do Plano *
                        </label>

                        <input
                            type="text"
                            name="nome"
                            class="field"
                            placeholder="Ex: Premium"
                            required
                        >

                    </div>


                    <div class="field-group">

                        <label>
                            Valor Mensal (R$) *
                        </label>

                        <input
                            type="number"
                            name="valor"
                            class="field"
                            step="0.01"
                            min="0"
                            placeholder="299.90"
                            required
                        >

                    </div>


                    <div class="field-group">

                        <label>
                            Máx. Médicos
                            <span class="tooltip-icon">*
                                <span class="tooltip-text">Deixe vazio para ilimitado</span>
                            </span>
                        </label>

                        <input
                            type="number"
                            name="max_medicos"
                            class="field"
                            min="1"
                            placeholder="Ilimitado"
                        >

                    </div>


                    <div class="field-group">

                        <label>
                            Armazenamento (GB)
                            <span class="tooltip-icon">*
                                <span class="tooltip-text">Deixe vazio para ilimitado</span>
                            </span>
                        </label>

                        <input
                            type="number"
                            name="max_armazenamento_gb"
                            class="field"
                            min="1"
                            placeholder="Ilimitado"
                        >

                    </div>


                    <div class="field-group">

                        <label>
                            Descrição
                        </label>

                        <input
                            type="text"
                            name="descricao"
                            class="field"
                            placeholder="Ideal para clínicas pequenas"
                        >

                    </div>


                    <div class="field-group">

                        <label>
                            <input
                                type="checkbox"
                                name="destaque"
                            >
                            Plano Recomendado (destaque)
                        </label>

                    </div>

                </div>


                <br>


                <div class="textarea-group">

                    <label>
                        Recursos (um por linha)
                    </label>

                    <textarea
                        name="recursos"
                        placeholder="Agendamento online&#10;Prontuário eletrônico&#10;Suporte por e-mail"
                    ></textarea>

                </div>


                <br>


                <button
                    type="submit"
                    class="confirm-btn"
                >
                    Cadastrar Plano
                </button>

            </form>

        </div>


        <!-- LISTA DE PLANOS (CARDS) -->

        <div class="top-bar">

            <h3>
                Planos Cadastrados
            </h3>

        </div>

        <div class="plan-grid">

            <?php foreach ($listaPlanos as $pl): ?>

                <div class="plan-card <?= $pl['destaque'] ? 'plan-featured' : '' ?>">

                    <?php if ($pl['destaque']): ?>
                        <div class="plan-badge">
                            Recomendado
                        </div>
                    <?php endif; ?>

                    <h3>
                        <?= htmlspecialchars($pl['nome']) ?>
                    </h3>

                    <p class="plan-desc">
                        <?= htmlspecialchars($pl['descricao'] ?? '') ?>
                    </p>

                    <div class="plan-price">
                        R$ <?= number_format($pl['valor'], 2, ',', '.') ?>
                        <small>/mês</small>
                    </div>

                    <div class="plan-details">

                        <p>
                            <strong>Médicos:</strong>
                            <?= $pl['max_medicos'] ? $pl['max_medicos'] : 'Ilimitado' ?>
                        </p>

                        <p>
                            <strong>Armazenamento:</strong>
                            <?= $pl['max_armazenamento_gb'] ? $pl['max_armazenamento_gb'] . ' GB' : 'Ilimitado' ?>
                        </p>

                        <p>
                            <strong>Assinaturas ativas:</strong>
                            <?= $pl['total_assinaturas'] ?>
                        </p>

                    </div>


                    <?php if (!empty($pl['recursos'])): ?>

                        <ul class="plan-recursos">

                            <?php foreach (explode('|', $pl['recursos']) as $recurso): ?>

                                <li>
                                    ✓ <?= htmlspecialchars(trim($recurso)) ?>
                                </li>

                            <?php endforeach; ?>

                        </ul>

                    <?php endif; ?>


                    <div class="plan-status">

                        <?php if ($pl['status'] === 'ativo'): ?>

                            <span class="status confirmed">Ativo</span>

                        <?php else: ?>

                            <span class="status">Inativo</span>

                        <?php endif; ?>

                    </div>


                    <div class="plan-actions">

                        <button
                            class="menu-btn"
                            type="button"
                            onclick="window.location.href='editar_plano.php?id=<?= $pl['id_plano'] ?>'"
                        >
                            Editar
                        </button>

                        <button
                            class="menu-btn"
                            type="button"
                            style="background:#c0392b;"
                            onclick="confirmarExclusaoPlano(<?= $pl['id_plano'] ?>, '<?= addslashes(htmlspecialchars($pl['nome'])) ?>')"
                        >
                            Excluir
                        </button>

                    </div>

                </div>

            <?php endforeach; ?>


            <?php if (empty($listaPlanos)): ?>

                <div class="card">
                    <p>Nenhum plano cadastrado ainda.</p>
                </div>

            <?php endif; ?>

        </div>


    </div>

</section>



<!-- ==============================
     FINANCEIRO
============================== -->

<section
    id="financeiro"
    class="admin-tab <?= $abaInicial === 'financeiro' ? 'active' : '' ?>"
>

    <div class="container">


        <!-- MENSAGEM -->

        <?php if ($abaInicial === 'financeiro' && !empty($mensagemSucesso)): ?>

            <div class="card">

                <p
                    style="color:green;font-weight:bold;"
                >
                    <?= htmlspecialchars($mensagemSucesso) ?>
                </p>

            </div>

        <?php endif; ?>


        <!-- CARDS DE RESUMO -->

        <div class="grid">

            <div class="card" style="text-align:center;">

                <h3>Faturamento Mensal</h3>

                <h1 style="color:var(--gold);">
                    R$ <?= number_format($faturamentoMensal, 2, ',', '.') ?>
                </h1>

            </div>

            <div class="card" style="text-align:center;">

                <h3>Assinaturas Ativas</h3>

                <h1 style="color:var(--gold);">
                    <?= $totalAssinaturasAtivas ?>
                </h1>

            </div>

            <div class="card" style="text-align:center;">

                <h3>Inadimplentes</h3>

                <h1 style="color:<?= $totalInadimplentes > 0 ? '#c0392b' : 'var(--gold)' ?>;">
                    <?= $totalInadimplentes ?>
                </h1>

            </div>

            <div class="card" style="text-align:center;">

                <h3>Vencem em 30 dias</h3>

                <h1 style="color:<?= $totalProxVencimentos > 0 ? '#e67e22' : 'var(--gold)' ?>;">
                    <?= $totalProxVencimentos ?>
                </h1>

            </div>

        </div>


        <!-- NOVA ASSINATURA -->

        <div class="card">

            <div class="top-bar">

                <h3>
                    Nova Assinatura
                </h3>

            </div>

            <form
                action="api/cadastro_assinatura.php"
                method="POST"
            >

                <div class="grid">

                    <div class="field-group">

                        <label>
                            Clínica *
                        </label>

                        <select
                            name="id_clinica"
                            class="field"
                            required
                        >

                            <option value="">
                                Selecione
                            </option>

                            <?php foreach ($listaClinicas as $cl): ?>

                                <option value="<?= $cl['id_clinica'] ?>">
                                    <?= htmlspecialchars($cl['nome']) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <div class="field-group">

                        <label>
                            Plano *
                        </label>

                        <select
                            name="id_plano"
                            id="select-plano-assinatura"
                            class="field"
                            onchange="preencherValorPlano()"
                            required
                        >

                            <option value="">
                                Selecione
                            </option>

                            <?php foreach ($planosSelect as $ps): ?>

                                <option
                                    value="<?= $ps['id_plano'] ?>"
                                    data-valor="<?= $ps['valor'] ?>"
                                >
                                    <?= htmlspecialchars($ps['nome']) ?>
                                    — R$ <?= number_format($ps['valor'], 2, ',', '.') ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <div class="field-group">

                        <label>
                            Valor (R$) *
                        </label>

                        <input
                            type="number"
                            name="valor"
                            id="valor-assinatura"
                            class="field"
                            step="0.01"
                            min="0"
                            required
                        >

                    </div>


                    <div class="field-group">

                        <label>
                            Data Início *
                        </label>

                        <input
                            type="date"
                            name="data_inicio"
                            class="field"
                            required
                        >

                    </div>


                    <div class="field-group">

                        <label>
                            Data Vencimento *
                        </label>

                        <input
                            type="date"
                            name="data_vencimento"
                            class="field"
                            required
                        >

                    </div>

                </div>


                <br>


                <button
                    type="submit"
                    class="confirm-btn"
                >
                    Cadastrar Assinatura
                </button>

            </form>

        </div>


        <!-- LISTA DE ASSINATURAS -->

        <div class="card">

            <div class="top-bar">

                <h3>
                    Assinaturas
                </h3>

            </div>

            <table>

                <thead>

                    <tr>

                        <th>Clínica</th>

                        <th>Plano</th>

                        <th>Valor</th>

                        <th>Início</th>

                        <th>Vencimento</th>

                        <th>Status</th>

                    </tr>

                </thead>


                <tbody>

                <?php while($a = $assinaturas->fetch_assoc()):

                    $vencida = (
                        $a['status'] === 'ativa' &&
                        strtotime($a['data_vencimento']) < time()
                    );

                ?>

                    <tr>

                        <td>
                            <?= htmlspecialchars($a['nome_clinica']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($a['nome_plano']) ?>
                        </td>

                        <td>
                            R$ <?= number_format($a['valor'], 2, ',', '.') ?>
                        </td>

                        <td>
                            <?= date('d/m/Y', strtotime($a['data_inicio'])) ?>
                        </td>

                        <td>
                            <?= date('d/m/Y', strtotime($a['data_vencimento'])) ?>
                        </td>

                        <td>

                            <?php if ($vencida): ?>

                                <span class="status status-vencida">
                                    Vencida
                                </span>

                            <?php elseif ($a['status'] === 'ativa'): ?>

                                <span class="status confirmed">
                                    Ativa
                                </span>

                            <?php elseif ($a['status'] === 'cancelada'): ?>

                                <span class="status status-cancelada">
                                    Cancelada
                                </span>

                            <?php else: ?>

                                <span class="status">
                                    <?= htmlspecialchars($a['status']) ?>
                                </span>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endwhile; ?>

                </tbody>

            </table>

        </div>


    </div>

</section>



<!-- ==============================
     LOGS
============================== -->

<section
    id="logs"
    class="admin-tab <?= $abaInicial === 'logs' ? 'active' : '' ?>"
>

    <div class="container">

        <div class="card">

            <h3>
                Logs do Sistema
            </h3>


            <table>

                <thead>

                    <tr>

                        <th>Data</th>

                        <th>Usuário</th>

                        <th>Ação</th>

                    </tr>

                </thead>


                <tbody>


                    <tr>

                        <td>
                            31/05/2026 08:10
                        </td>

                        <td>
                            Administrador
                        </td>

                        <td>
                            Cadastro de clínica
                        </td>

                    </tr>


                    <tr>

                        <td>
                            31/05/2026 09:25
                        </td>

                        <td>
                            Secretária
                        </td>

                        <td>
                            Agendamento de consulta
                        </td>

                    </tr>


                </tbody>

            </table>

        </div>

    </div>

</section>



<!-- ==============================
     ABA: ESPECIALIDADES
============================== -->

<section id="especialidades"
    class="admin-tab"
    style="display:none;">

    <div class="container">

        <!-- CADASTRAR -->
        <div class="card">

            <h3>Cadastrar Especialidade</h3>

            <form action="api/cadastrar_especialidade.php" method="POST"
                style="display:flex;gap:15px;align-items:flex-end;flex-wrap:wrap;">

                <div class="field-group" style="flex:1;min-width:250px;">
                    <label>Nome da Especialidade *</label>
                    <input type="text" name="nome" class="field"
                        placeholder="Ex: Cardiologia" required>
                </div>

                <button type="submit" class="confirm-btn">
                    + Cadastrar
                </button>

            </form>

        </div>

        <!-- LISTA -->
        <div class="card">

            <h3>Especialidades Cadastradas</h3>

            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Ações</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (empty($listaEspecialidades)): ?>
                    <tr>
                        <td colspan="3" style="text-align:center;color:#888;">
                            Nenhuma especialidade cadastrada.
                        </td>
                    </tr>
                <?php else: ?>
                <?php foreach ($listaEspecialidades as $esp): ?>
                    <tr>
                        <td><?= $esp['id_especialidade'] ?></td>
                        <td><?= htmlspecialchars($esp['nome']) ?></td>
                        <td>
                            <button class="menu-btn" style="background:var(--danger,#e74c3c);color:#fff;"
                                onclick="confirmarExclusaoEspecialidade(<?= $esp['id_especialidade'] ?>, '<?= htmlspecialchars($esp['nome'], ENT_QUOTES) ?>')">
                                Excluir
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php endif; ?>

                </tbody>
            </table>

        </div>

    </div>

</section>


<!-- ==============================
     JAVASCRIPT
============================== -->

<script src="assets/js/main.js"></script>
<script src="assets/js/admin.js"></script>

</body>

</html>
