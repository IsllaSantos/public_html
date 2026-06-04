<?php

require_once('config/auth.php');

verificarPerfil('admin');

$nomeAdmin = $_SESSION['nome'];

?>
<?php
require_once('config/database.php');

$usuarios =
$conn->query("
SELECT
id_usuario,
nome,
email,
perfil,
status
FROM usuarios
ORDER BY nome
");
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Administrador - TechNyes</title>

<link rel="stylesheet" href="assets/css/style.css">

</head>

<body>

<header>

    <div class="logo">
        Tech<span>Nyes</span>
    </div>

    <nav>

        <button onclick="showAdminTab('dashboard',event)">
            Dashboard
        </button>

        <button onclick="showAdminTab('clinicas',event)">
            Clínicas
        </button>

        <button onclick="showAdminTab('usuarios',event)">
            Usuários
        </button>

        <button onclick="showAdminTab('planos',event)">
            Planos
        </button>

        <button onclick="showAdminTab('financeiro',event)">
            Financeiro
        </button>

        <button onclick="showAdminTab('logs',event)">
            Logs
        </button>
<button onclick="window.location.href='logout.php'">
    Sair
</button>
    </nav>

</header>

<div class="container">

    <div class="top-bar">

        <h2>

            Painel Administrativo

            <span style="color:var(--gold);">

                <?php echo htmlspecialchars($nomeAdmin); ?>

            </span>

        </h2>

    </div>

</div>

<!-- DASHBOARD -->

<section id="dashboard" class="admin-tab active">

    <div class="container">

        <div class="grid">

            <div class="card">

                <h3>Clínicas Ativas</h3>

                <h1>12</h1>

            </div>

            <div class="card">

                <h3>Médicos</h3>

                <h1>85</h1>

            </div>

            <div class="card">

                <h3>Pacientes</h3>

                <h1>2.540</h1>

            </div>

            <div class="card">

                <h3>Faturamento Mensal</h3>

                <h1>R$ 18.450</h1>

            </div>

            <div class="card">

                <h3>Consultas Hoje</h3>

                <h1>154</h1>

            </div>

            <div class="card">

                <h3>Assinaturas Ativas</h3>

                <h1>12</h1>

            </div>

        </div>

    </div>

</section>

<!-- CLINICAS -->

<section id="clinicas" class="admin-tab">

    <div class="container">

        <div class="card">

            <div class="top-bar">

                <h3>Clínicas Cadastradas</h3>

                <button class="btn btn-primary">
                    Nova Clínica
                </button>

            </div>

            <table>

                <thead>

                    <tr>
                        <th>Nome</th>
                        <th>Cidade</th>
                        <th>Plano</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>

                </thead>

                <tbody>

                    <tr>

                        <td>Clínica Saúde+</td>
                        <td>Campinas</td>
                        <td>Premium</td>

                        <td>
                            <span class="status confirmed">
                                Ativa
                            </span>
                        </td>

                        <td>
                            <button class="menu-btn">
                                Editar
                            </button>
                        </td>

                    </tr>

                </tbody>

            </table>

        </div>

    </div>

</section>

<!-- USUARIOS -->

<!-- USUARIOS -->

<section id="usuarios" class="admin-tab">

    <div class="container">

        <div class="card">

        <?php if(isset($_GET['sucesso'])): ?>

<div class="card">

    <p style="color:green;font-weight:bold;">

        Usuário cadastrado com sucesso!

    </p>

</div>

<?php endif; ?>
            <h3>
                Novo Usuário
            </h3>

            <form
            action="api/cadastro_usuario.php"
            method="POST">

                <div class="grid">

                    <div class="field-group">

                        <label>
                            Nome
                        </label>

                        <input
                        type="text"
                        name="nome"
                        class="field"
                        required>

                    </div>

                    <div class="field-group">

                        <label>
                            E-mail
                        </label>

                        <input
                        type="email"
                        name="email"
                        class="field"
                        required>

                    </div>

                    <div class="field-group">

                        <label>
                            Senha
                        </label>

                        <input
                        type="password"
                        name="senha"
                        class="field"
                        required>

                    </div>

                    <div class="field-group">

                        <label>
                            Perfil
                        </label>

                        <select
                        name="perfil"
                        class="field"
                        required>

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
                class="confirm-btn">

                    Cadastrar Usuário

                </button>

            </form>

        </div>

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
                            <?= htmlspecialchars($u['status']) ?>
                        </td>

                    </tr>

                <?php endwhile; ?>

                </tbody>

            </table>

        </div>

    </div>

</section>
        

<!-- PLANOS -->

<section id="planos" class="admin-tab">

    <div class="container">

        <div class="card">

            <h3>Planos da Plataforma</h3>

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

                        <td>Básico</td>
                        <td>R$ 99,90</td>
                        <td>2</td>
                        <td>10 GB</td>

                    </tr>

                    <tr>

                        <td>Premium</td>
                        <td>R$ 299,90</td>
                        <td>Ilimitado</td>
                        <td>100 GB</td>

                    </tr>

                </tbody>

            </table>

        </div>

    </div>

</section>

<!-- FINANCEIRO -->

<section id="financeiro" class="admin-tab">

    <div class="container">

        <div class="card">

            <h3>Financeiro</h3>

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

                        <td>Clínica Saúde+</td>
                        <td>Premium</td>
                        <td>R$ 299,90</td>
                        <td>10/06/2026</td>

                    </tr>

                </tbody>

            </table>

        </div>

    </div>

</section>

<!-- LOGS -->

<section id="logs" class="admin-tab">

    <div class="container">

        <div class="card">

            <h3>Logs do Sistema</h3>

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

                        <td>31/05/2026 08:10</td>
                        <td>Administrador</td>
                        <td>Cadastro de clínica</td>

                    </tr>

                    <tr>

                        <td>31/05/2026 09:25</td>
                        <td>Secretária</td>
                        <td>Agendamento de consulta</td>

                    </tr>

                </tbody>

            </table>

        </div>

    </div>

</section>

<script src="assets/js/admin.js"></script>

</body>
</html>