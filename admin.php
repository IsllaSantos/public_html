
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
    href="assets/css/style.css"
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

        <button
            onclick="showAdminTab('dashboard',event)"
        >
            Dashboard
        </button>


        <button
            onclick="showAdminTab('clinicas',event)"
        >
            Clínicas
        </button>


        <button
            onclick="showAdminTab('usuarios',event)"
        >
            Usuários
        </button>


        <button
            onclick="showAdminTab('planos',event)"
        >
            Planos
        </button>


        <button
            onclick="showAdminTab('financeiro',event)"
        >
            Financeiro
        </button>


        <button
            onclick="showAdminTab('logs',event)"
        >
            Logs
        </button>


        <button
            onclick="window.location.href='logout.php'"
        >
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
                    12
                </h1>

            </div>


            <div class="card">

                <h3>
                    Médicos
                </h3>

                <h1>
                    85
                </h1>

            </div>


            <div class="card">

                <h3>
                    Pacientes
                </h3>

                <h1>
                    2.540
                </h1>

            </div>


            <div class="card">

                <h3>
                    Faturamento Mensal
                </h3>

                <h1>
                    R$ 18.450
                </h1>

            </div>


            <div class="card">

                <h3>
                    Consultas Hoje
                </h3>

                <h1>
                    154
                </h1>

            </div>


            <div class="card">

                <h3>
                    Assinaturas Ativas
                </h3>

                <h1>
                    12
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
                            class="field"
                            maxlength="14"
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
                            class="field"
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
                            class="field"
                            maxlength="8"
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

                            <option value="SP">
                                SP
                            </option>

                            <option value="RJ">
                                RJ
                            </option>

                            <option value="MG">
                                MG
                            </option>

                            <option value="PR">
                                PR
                            </option>

                            <option value="SC">
                                SC
                            </option>

                            <option value="RS">
                                RS
                            </option>

                            <option value="BA">
                                BA
                            </option>

                            <option value="PE">
                                PE
                            </option>

                            <option value="GO">
                                GO
                            </option>

                            <option value="DF">
                                DF
                            </option>

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

                            <?= htmlspecialchars($c['cidade']) ?>

                        </td>


                        <td>

                            <?= htmlspecialchars($c['estado']) ?>

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


        <!-- NOVO USUÁRIO -->

        <div class="card">


            <?php if(isset($_GET['sucesso'])): ?>

                <div class="card">

                    <p
                        style="color:green;font-weight:bold;"
                    >

                        Usuário cadastrado com sucesso!

                    </p>

                </div>

            <?php endif; ?>


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
                            Nome
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
                            E-mail
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
                            Senha
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
                            Perfil
                        </label>

                        <select
                            name="perfil"
                            class="field"
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
                                : 'Administrador Master'
                            ?>

                        </td>


                        <td>

                            <?= htmlspecialchars($u['status']) ?>

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

        <div class="card">

            <h3>
                Planos da Plataforma
            </h3>


            <table>

                <thead>

                    <tr>

                        <th>Plano</th>

                        <th>Valor</th>

                        <th>Médicos</th>

                        <th>Armazenamento</th>

                    </tr>

                </thead>


                <tbody>


                    <tr>

                        <td>
                            Básico
                        </td>

                        <td>
                            R$ 99,90
                        </td>

                        <td>
                            2
                        </td>

                        <td>
                            10 GB
                        </td>

                    </tr>


                    <tr>

                        <td>
                            Premium
                        </td>

                        <td>
                            R$ 299,90
                        </td>

                        <td>
                            Ilimitado
                        </td>

                        <td>
                            100 GB
                        </td>

                    </tr>


                </tbody>

            </table>

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

        <div class="card">

            <h3>
                Financeiro
            </h3>


            <table>

                <thead>

                    <tr>

                        <th>Clínica</th>

                        <th>Plano</th>

                        <th>Valor</th>

                        <th>Vencimento</th>

                    </tr>

                </thead>


                <tbody>


                    <tr>

                        <td>
                            Clínica TechNyes
                        </td>

                        <td>
                            Premium
                        </td>

                        <td>
                            R$ 299,90
                        </td>

                        <td>
                            10/06/2026
                        </td>

                    </tr>


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
     JAVASCRIPT
============================== -->

<script src="assets/js/admin.js"></script>


</body>

</html>
