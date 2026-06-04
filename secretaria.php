<?php

require_once('config/auth.php');

verificarPerfil('secretaria');

$nomeSecretaria = $_SESSION['nome'];

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Painel Secretaria - TechNyes</title>

<link rel="stylesheet" href="assets/css/style.css">

</head>

<body>

<header>

    <div class="logo">
        <a href="secretaria.php">
            Tech<span>Nyes</span>
        </a>
    </div>

    <nav>

        <button onclick="showSecretariaTab('secretariaInicio',event)">
            Dashboard
        </button>

        <button onclick="showSecretariaTab('secretariaMedicos',event)">
            Médicos
        </button>

        <button onclick="showSecretariaTab('secretariaPacientes',event)">
            Pacientes
        </button>

        <button onclick="showSecretariaTab('secretariaAgendamentos',event)">
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

                <?php echo htmlspecialchars($nomeSecretaria); ?>

            </span>

        </h2>

    </div>

</div>

<section id="secretariaDashboard"
class="screen active">

    <!-- DASHBOARD -->

    <div id="secretariaInicio"
    class="prontuario-content active">

        <div class="stats-row">

            <div class="card">

                <h3>Consultas Hoje</h3>

                <div style="font-size:2rem;font-weight:bold;color:var(--gold);">
                    12
                </div>

            </div>

            <div class="card">

                <h3>Pendentes</h3>

                <div style="font-size:2rem;font-weight:bold;color:orange;">
                    3
                </div>

            </div>

            <div class="card">

                <h3>Pacientes Ativos</h3>

                <div style="font-size:2rem;font-weight:bold;color:green;">
                    26
                </div>

            </div>

        </div>

        <div class="card">

            <div class="top-bar"
            style="margin-bottom:20px;">

                <h3>
                    Confirmação de Consultas
                </h3>

                <button class="menu-btn"
                onclick="alert('Lembretes enviados')">

                    Enviar Lembretes

                </button>

            </div>

            <table>

                <thead>

                    <tr>
                        <th>Data</th>
                        <th>Horário</th>
                        <th>Paciente</th>
                        <th>Médico</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>

                </thead>

                <tbody>

                    <tr>

                        <td>17/05/2026</td>
                        <td>09:32</td>
                        <td>Marcos Ferreira</td>
                        <td>Dr. Alfredo Gomes</td>

                        <td>

                            <span class="status confirmed">
                                Confirmada
                            </span>

                        </td>

                        <td>

                            <button class="menu-btn">
                                Alterar
                            </button>

                        </td>

                    </tr>

                    <tr>

                        <td>17/05/2026</td>
                        <td>10:30</td>
                        <td>Antonio Cesar</td>
                        <td>Dr. Alfredo Gomes</td>

                        <td>

                            <span class="status pending">
                                Pendente
                            </span>

                        </td>

                        <td>

                            <button class="menu-btn">
                                Alterar
                            </button>

                        </td>

                    </tr>

                </tbody>

            </table>

        </div>

    </div>

    <!-- MÉDICOS -->

<div id="secretariaMedicos"
class="prontuario-content">

    <div class="card">

        <div class="top-bar">

            <h3>Médicos</h3>

          <button
    class="btn btn-primary"
    onclick="showSecretariaTab('secretariaNovoMedico',event)">
    Novo Médico
</button>

        </div>

        <table>

            <thead>

                <tr>
                    <th>Nome</th>
                    <th>CRM</th>
                    <th>Especialidade</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>

            </thead>

            <tbody>

                <tr>

                    <td>Dr. Alfredo Gomes</td>
                    <td>123456-SP</td>
                    <td>Cardiologia</td>

                    <td>
                        <span class="status confirmed">
                            Ativo
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

    <!-- PACIENTES -->

<div id="secretariaPacientes"
class="prontuario-content">

    <div class="card">

        <div class="top-bar">

            <h3>Pacientes</h3>

          <button
    class="btn btn-primary"
    onclick="showSecretariaTab('secretariaNovoPaciente',event)">
    Novo Paciente
</button>

        </div>

        <table>

            <thead>

                <tr>
                    <th>Nome</th>
                    <th>CPF</th>
                    <th>Telefone</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>

            </thead>

            <tbody>

                <tr>

                    <td>Marcos Ferreira</td>
                    <td>123.456.789-00</td>
                    <td>(19)99999-9999</td>

                    <td>

                        <span class="status confirmed">
                            Ativo
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

    <!-- AGENDAMENTOS -->

<div id="secretariaAgendamentos"
class="prontuario-content">

    <div class="card">

        <div class="top-bar">

            <h3>Agendamentos</h3>

           <button
    class="btn btn-primary"
    onclick="showSecretariaTab('secretariaNovaConsulta',event)">
    Nova Consulta
</button>

        </div>

        <table>

            <thead>

                <tr>
                    <th>Data</th>
                    <th>Horário</th>
                    <th>Paciente</th>
                    <th>Médico</th>
                    <th>Status</th>
                </tr>

            </thead>

            <tbody>

                <tr>

                    <td>20/06/2026</td>
                    <td>14:00</td>
                    <td>Marcos Ferreira</td>
                    <td>Dr. Alfredo Gomes</td>

                    <td>

                        <span class="status confirmed">
                            Confirmada
                        </span>

                    </td>

                </tr>

                <tr>

                    <td>21/06/2026</td>
                    <td>09:00</td>
                    <td>Ana Paula</td>
                    <td>Dr. Ricardo Silva</td>

                    <td>

                        <span class="status pending">
                            Pendente
                        </span>

                    </td>

                </tr>

            </tbody>

        </table>

    </div>

</div>

<!-- NOVO MÉDICO -->

<div id="secretariaNovoMedico"
class="prontuario-content">

    <div class="card">

        <h2 style="margin-bottom:25px;">
            Cadastro de Médico
        </h2>

        <div class="grid">

            <div class="field-group">
                <label>Nome Completo</label>
                <input type="text" class="field">
            </div>

            <div class="field-group">
                <label>E-mail</label>
                <input type="email" class="field">
            </div>

            <div class="field-group">
                <label>CRM</label>
                <input type="text" class="field">
            </div>

            <div class="field-group">
                <label>Especialidade</label>

                <select class="field">
                    <option>Cardiologia</option>
                    <option>Dermatologia</option>
                    <option>Ortopedia</option>
                    <option>Pediatria</option>
                    <option>Clínico Geral</option>
                </select>
            </div>

            <div class="field-group">
                <label>Telefone</label>
                <input type="text" class="field">
            </div>

            <div class="field-group">
                <label>Data de Nascimento</label>
                <input type="date" class="field">
            </div>

        </div>

        <br>

        <div class="card">

            <h3 style="margin-bottom:15px;">
                Endereço
            </h3>

            <div class="grid">

                <div class="field-group">
                    <label>CEP</label>
                    <input type="text" class="field">
                </div>

                <div class="field-group">
                    <label>Cidade</label>
                    <input type="text" class="field">
                </div>

                <div class="field-group">
                    <label>Estado</label>
                    <input type="text" class="field">
                </div>

                <div class="field-group">
                    <label>Rua</label>
                    <input type="text" class="field">
                </div>

            </div>

        </div>

        <div class="card">

            <h3 style="margin-bottom:15px;">
                Informações Profissionais
            </h3>

            <div class="grid">

                <div class="field-group">
                    <label>Universidade</label>
                    <input type="text" class="field">
                </div>

                <div class="field-group">
                    <label>Ano de Formação</label>
                    <input type="number" class="field">
                </div>

                <div class="field-group">
                    <label>Horário Inicial</label>
                    <input type="time" class="field">
                </div>

                <div class="field-group">
                    <label>Horário Final</label>
                    <input type="time" class="field">
                </div>

            </div>

        </div>

        <button
        class="confirm-btn"
        onclick="alert('Médico cadastrado com sucesso!')">

            Salvar Médico

        </button>

    </div>

</div>
<!-- NOVO PACIENTE -->

<div id="secretariaNovoPaciente"
class="prontuario-content">

    <div class="card">

        <h2 style="margin-bottom:25px;">
            Cadastro de Paciente
        </h2>

        <div class="grid">

            <div class="field-group">
                <label>Nome Completo</label>
                <input type="text" class="field">
            </div>

            <div class="field-group">
                <label>CPF</label>
                <input type="text" class="field">
            </div>

            <div class="field-group">
                <label>RG</label>
                <input type="text" class="field">
            </div>

            <div class="field-group">
                <label>Data de Nascimento</label>
                <input type="date" class="field">
            </div>

            <div class="field-group">
                <label>Sexo</label>

                <select class="field">
                    <option>Masculino</option>
                    <option>Feminino</option>
                    <option>Outro</option>
                </select>
            </div>

            <div class="field-group">
                <label>Estado Civil</label>

                <select class="field">
                    <option>Solteiro(a)</option>
                    <option>Casado(a)</option>
                    <option>Divorciado(a)</option>
                    <option>Viúvo(a)</option>
                </select>
            </div>

        </div>

    </div>

    <!-- CONTATO -->

    <div class="card">

        <h3 style="margin-bottom:15px;">
            Informações de Contato
        </h3>

        <div class="grid">

            <div class="field-group">
                <label>Telefone</label>
                <input type="text" class="field">
            </div>

            <div class="field-group">
                <label>Celular</label>
                <input type="text" class="field">
            </div>

            <div class="field-group">
                <label>E-mail</label>
                <input type="email" class="field">
            </div>

            <div class="field-group">
                <label>Contato de Emergência</label>
                <input type="text" class="field">
            </div>

        </div>

    </div>

    <!-- ENDEREÇO -->

    <div class="card">

        <h3 style="margin-bottom:15px;">
            Endereço
        </h3>

        <div class="grid">

            <div class="field-group">
                <label>CEP</label>
                <input type="text" class="field">
            </div>

            <div class="field-group">
                <label>Cidade</label>
                <input type="text" class="field">
            </div>

            <div class="field-group">
                <label>Estado</label>
                <input type="text" class="field">
            </div>

            <div class="field-group">
                <label>Rua</label>
                <input type="text" class="field">
            </div>

            <div class="field-group">
                <label>Número</label>
                <input type="text" class="field">
            </div>

            <div class="field-group">
                <label>Bairro</label>
                <input type="text" class="field">
            </div>

        </div>

    </div>

    <!-- INFORMAÇÕES MÉDICAS -->

    <div class="card">

        <h3 style="margin-bottom:15px;">
            Informações Médicas
        </h3>

        <div class="grid">

            <div class="field-group">
                <label>Tipo Sanguíneo</label>

                <select class="field">
                    <option>A+</option>
                    <option>A-</option>
                    <option>B+</option>
                    <option>B-</option>
                    <option>AB+</option>
                    <option>AB-</option>
                    <option>O+</option>
                    <option>O-</option>
                </select>
            </div>

            <div class="field-group">
                <label>Convênio</label>
                <input type="text" class="field">
            </div>

            <div class="field-group">
                <label>Número da Carteirinha</label>
                <input type="text" class="field">
            </div>

            <div class="field-group">
                <label>Alergias</label>
                <input type="text" class="field">
            </div>

        </div>

    </div>

    <!-- OBSERVAÇÕES -->

    <div class="card">

        <h3 style="margin-bottom:15px;">
            Observações
        </h3>

        <textarea
            class="field"
            style="width:100%;min-height:150px;">
        </textarea>

    </div>

    <button
    class="confirm-btn"
    onclick="alert('Paciente cadastrado com sucesso!')">

        Salvar Paciente

    </button>

</div>
<!-- NOVA CONSULTA -->

<div id="secretariaNovaConsulta"
class="prontuario-content">

    <div class="card">

        <h2 style="margin-bottom:25px;">
            Agendamento de Consulta
        </h2>

        <div class="grid">

            <div class="field-group">

                <label>
                    Paciente
                </label>

                <select class="field">

                    <option>
                        Marcos Ferreira
                    </option>

                    <option>
                        Ana Paula
                    </option>

                    <option>
                        João Carlos
                    </option>

                </select>

            </div>

            <div class="field-group">

                <label>
                    Médico
                </label>

                <select class="field">

                    <option>
                        Dr. Alfredo Gomes
                    </option>

                    <option>
                        Dra. Juliana Martins
                    </option>

                    <option>
                        Dr. Ricardo Silva
                    </option>

                </select>

            </div>

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
                        Ortopedia
                    </option>

                    <option>
                        Clínica Geral
                    </option>

                </select>

            </div>

            <div class="field-group">

                <label>
                    Tipo de Consulta
                </label>

                <select class="field">

                    <option>
                        Primeira Consulta
                    </option>

                    <option>
                        Retorno
                    </option>

                    <option>
                        Teleconsulta
                    </option>

                </select>

            </div>

        </div>

    </div>

    <!-- DATA E HORÁRIO -->

    <div class="card">

        <h3 style="margin-bottom:20px;">
            Data e Horário
        </h3>

        <div class="grid">

            <div class="field-group">

                <label>
                    Data da Consulta
                </label>

                <input
                type="date"
                class="field">

            </div>

            <div class="field-group">

                <label>
                    Horário
                </label>

                <input
                type="time"
                class="field">

            </div>

            <div class="field-group">

                <label>
                    Sala
                </label>

                <select class="field">

                    <option>
                        Consultório 01
                    </option>

                    <option>
                        Consultório 02
                    </option>

                    <option>
                        Consultório 03
                    </option>

                </select>

            </div>

            <div class="field-group">

                <label>
                    Convênio
                </label>

                <select class="field">

                    <option>
                        Particular
                    </option>

                    <option>
                        Unimed
                    </option>

                    <option>
                        Amil
                    </option>

                    <option>
                        Bradesco Saúde
                    </option>

                </select>

            </div>

        </div>

    </div>

    <!-- OBSERVAÇÕES -->

    <div class="card">

        <h3 style="margin-bottom:15px;">
            Observações
        </h3>

        <textarea
            class="field"
            style="width:100%;min-height:150px;"
            placeholder="Informações adicionais da consulta...">
        </textarea>

    </div>

    <!-- RESUMO -->

    <div class="card">

        <h3 style="margin-bottom:15px;">
            Resumo do Agendamento
        </h3>

        <p>
            <strong>Paciente:</strong> Marcos Ferreira
        </p>

        <br>

        <p>
            <strong>Médico:</strong> Dr. Alfredo Gomes
        </p>

        <br>

        <p>
            <strong>Especialidade:</strong> Cardiologia
        </p>

        <br>

        <p>
            <strong>Status:</strong>

            <span class="status pending">
                Aguardando Confirmação
            </span>
        </p>

    </div>

    <button
        class="confirm-btn"
        onclick="alert('Consulta agendada com sucesso!')">

        Confirmar Agendamento

    </button>

</div>

</section>

<script src="assets/js/secretaria.js"></script>

</body>
</html>