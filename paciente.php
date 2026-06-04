<?php

require_once('config/auth.php');

verificarPerfil('paciente');

$nomePaciente = $_SESSION['nome'];

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Painel do Paciente - TechNyes</title>

<link rel="stylesheet" href="assets/css/style.css">

</head>

<body>

<header>

    <div class="logo">
        <a href="paciente.php">
            Tech<span>Nyes</span>
        </a>
    </div>

    <nav>

        <button onclick="showPacienteTab('meusdados',event)">
            Meus Dados
        </button>

        <button onclick="showPacienteTab('agendamentos',event)">
            Agendamentos
        </button>

        <button onclick="showPacienteTab('meusarquivos',event)">
            Arquivos
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

    <!-- PRINCIPAL -->

<section id="meusdados"
    class="paciente-tab active">
      <div class="card">
            <h3>
                Meus Dados
            </h3>
            <div class="grid">
                <div class="field-group">
                    <label>
                        Nome
                    </label>

                    <div class="field">
                        João Bezerra
                    </div>
                </div>

                <div class="field-group">
                    <label>
                        CPF
                    </label>

                    <div class="field">
                        123.456.789-00
                    </div>
                </div>

                <div class="field-group">

                    <label>
                        Sexo
                    </label>

                    <div class="field">
                        Masculino
                    </div>
                </div>
</div>
</div>
</section>

    <!-- AGENDAR -->

<section id="agendamentos" class="paciente-tab">
 <div class="card">
            <h3>
                Agendar Consulta
            </h3>

            <div class="grid">

                <div class="field-group">

                    <label>
                        Especialidade
                    </label>

                    <select class="field">

                        <option>
                            Cardiologia
                        </option>

                        <option>
                            Dermatologia
                        </option>

                        <option>
                            Clínica Geral
                        </option>

                    </select>

                </div>

                <div class="field-group">

                    <label>
                        Data
                    </label>

                    <input type="date"
                    class="field">

                </div>

                <div class="field-group">

                    <label>
                        Horário
                    </label>

                    <input type="time"
                    class="field">

                </div>

            </div>

            <br>

            <button class="confirm-btn">
                Agendar
            </button>
     </div>
</section>

    <!-- ARQUIVOS -->

<section id="meusarquivos"
    class="paciente-tab">

        <!-- TABELA -->

        <div class="card">

            <h3>
                Meus Arquivos
            </h3>

            <table>

                <thead>

                    <tr>
                        <th>Data</th>
                        <th>Arquivo</th>
                        <th>Consulta</th>
                        <th>Ações</th>
                    </tr>

                </thead>

                <tbody id="arquivosTabela">

                    <tr>

                        <td>
                            17/05/2026
                        </td>

                        <td>
                            Exame de Sangue
                        </td>

                        <td>
                            Consulta Clínica
                        </td>

                        <td>

                            <button class="menu-btn">
                                Compartilhar
                            </button>

                        </td>

                    </tr>

                </tbody>

            </table>

        </div>

        <!-- UPLOAD -->

        <div class="card">

            <h3>
                Enviar Novo Arquivo
            </h3>

            <div class="grid">

                <div class="field-group">

                    <label>
                        Nome do Arquivo
                    </label>

                    <input type="text"
                    id="nomeArquivo"
                    class="field">

                </div>

                <div class="field-group">

                    <label>
                        Consulta
                    </label>

                    <select id="consultaArquivo"
                    class="field">

                        <option>
                            Consulta Clínica
                        </option>

                        <option>
                            Retorno Cardiologista
                        </option>

                    </select>

                </div>

                <div class="field-group">

                    <label>
                        Arquivo
                    </label>

                    <input type="file"
                    class="field">

                </div>

            </div>

            <br>

            <button class="confirm-btn"
            onclick="enviarArquivo()">

                Anexar Documento

            </button>

        </div>

    </div>

</section>

<script src="assets/js/paciente.js"></script>
</body>
</html>

