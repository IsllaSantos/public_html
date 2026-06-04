<?php

require_once('config/auth.php');

verificarPerfil('medico');

$nomeMedico =
$_SESSION['nome'];

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Painel Médico - TechNyes</title>

<link rel="stylesheet" href="assets/css/style.css">

</head>

<body>

<header>

  <div class="logo">
    <a href="medico.php">
        Tech<span>Nyes</span>
    </a>
  </div>

    <nav>

        <button onclick="showScreen('agenda',event)">
            Agenda
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


 <section id="agenda"
    class="screen active">

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
                        <th>Status</th>
                        <th></th>
                    </tr>

                </thead>

                <tbody>

                    <tr>

                        <td>25/07/2026</td>
                        <td>08:00</td>
                        <td>João Bezerra</td>

                        <td>

                            <span class="status confirmed">
                                Confirmada
                            </span>

                        </td>

                        <td>

                            <button class="circle-btn">
                                ➜
                            </button>

                        </td>

                    </tr>

                    <tr>

                        <td>26/07/2026</td>
                        <td>08:00</td>
                        <td>Maria Dolores</td>

                        <td>

                            <span class="status pending">
                                Pendente
                            </span>

                        </td>

                        <td>

                            <button class="circle-btn">
                                ➜
                            </button>

                        </td>

                    </tr>

                </tbody>

            </table>

        </div>

 </section>

 <section id="listaPacientesMedico"
 class="screen">

    <div class="card">

        <div class="top-bar"
        style="margin-bottom:20px;">

            <h3>
                Pacientes
            </h3>

            <input type="text"
            placeholder="Buscar paciente..."
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

            <tbody>

                <tr>

                    <td>João Bezerra</td>
                    <td>123.456.789-00</td>
                    <td>02/03/2026</td>

                    <td>
                        <span class="status confirmed">
                            Ativo
                        </span>
                    </td>

                    <td>

                        <button class="menu-btn"
                        onclick="abrirProntuarioPaciente('João Bezerra')">

                            Ver Prontuário

                        </button>

                    </td>

                </tr>

                <tr>

                    <td>Maria Dolores</td>
                    <td>987.654.321-00</td>
                    <td>10/04/2026</td>

                    <td>
                        <span class="status pending">
                            Retorno
                        </span>
                    </td>

                    <td>

                        <button class="menu-btn"
                        onclick="abrirProntuarioPaciente('Maria Dolores')">

                            Ver Prontuário

                        </button>

                    </td>

                </tr>

            </tbody>

        </table>

    </div>

  </section>

  <section id="prontuario"
    class="screen">

        <div class="prontuario-tabs">

            <button class="prontuario-btn active"
            onclick="showProntuario('eletronico',event)">
                Prontuário Eletrônico
            </button>

            <button class="prontuario-btn"
            onclick="showProntuario('historico',event)">
                Histórico
            </button>

        </div>

        <!-- ELETRONICO -->

        <div id="eletronico"
        class="prontuario-content active">

            <div class="card">
   <h3 id="nomePacienteProntuario">
    João Bezerra
   </h3>

                <div class="grid">
<!-- DADOS VINDOS DO BANCO -->
                    <div class="field-group">
                        <label>Nome</label>
                        <div class="field">
                            João Bezerra
                        </div>
                    </div>

                    <div class="field-group">
                        <label>Data Nascimento</label>
                        <div class="field">
                            15/05/1975
                        </div>
                    </div>

                    <div class="field-group">
                        <label>Sexo</label>
                        <div class="field">
                            Masculino
                        </div>
                    </div>

                    <div class="field-group">
                        <label>Naturalidade</label>
                        <div class="field">
                            Brasil
                        </div>
                    </div>

                    <div class="field-group full">

                        <label>Endereço</label>

                        <div class="field">
                            Av. Euclides, 850
                        </div>

                    </div>

                </div>

            </div>

            <!-- ANAMNESE -->

            <div class="card">

                <h3>
                    Anamnese
                </h3>

                <div class="textarea-group">

                    <label>
                        Queixa Principal
                    </label>

                    <textarea></textarea>

                </div>

                <div class="textarea-group">

                    <label>
                        Histórico da Doença Atual
                    </label>

                    <textarea class="large"></textarea>

                </div>

                <div class="two-columns">

                    <div class="textarea-group">

                        <label>
                            Antecedentes
                        </label>

                        <textarea></textarea>

                    </div>

                    <div class="textarea-group">

                        <label>
                            Medicamentos em Uso
                        </label>

                        <textarea></textarea>

                    </div>

                </div>

                <button class="confirm-btn">
                    Confirmar
                </button>

            </div>

        </div>

        <!-- HISTORICO -->

        <div id="historico"
        class="prontuario-content">

            <div class="card">

                <h3>
                    Consulta 02/03/2026
                </h3>

                <button class="btn btn-primary">
                    Visualizar
                </button>

            </div>

        </div>

 </section>

</main>

<script src="assets/js/medico.js"></script>

</body>
</html>