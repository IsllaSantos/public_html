<?php

require_once('../config/auth.php');
require_once('../config/database.php');

verificarPerfil('secretaria');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../secretaria.php");
    exit;
}

$id_clinica = $_SESSION['id_clinica'];

if (empty($id_clinica)) {
    $_SESSION['mensagem_sucesso'] = "Erro: secretária sem clínica vinculada.";
    header("Location: ../secretaria.php?aba=medicos");
    exit;
}


/* CAMPOS */

$nome = trim($_POST['nome'] ?? '');
$email = trim($_POST['email'] ?? '');
$senha = $_POST['senha'] ?? '';

/* Dados Pessoais */
$cpf = !empty($_POST['cpf'])
    ? preg_replace('/\D/', '', trim($_POST['cpf']))
    : null;
$rg = !empty($_POST['rg'])
    ? trim($_POST['rg'])
    : null;
$data_nascimento = !empty($_POST['data_nascimento'])
    ? trim($_POST['data_nascimento'])
    : null;
$sexo = !empty($_POST['sexo'])
    ? trim($_POST['sexo'])
    : null;
$telefone = !empty($_POST['telefone'])
    ? preg_replace('/\D/', '', trim($_POST['telefone']))
    : null;
$celular = !empty($_POST['celular'])
    ? preg_replace('/\D/', '', trim($_POST['celular']))
    : null;

/* Dados Profissionais */
$crm = trim($_POST['crm'] ?? '');
$uf_crm = trim($_POST['uf_crm'] ?? '');
$especialidade = trim($_POST['especialidade'] ?? '');
$horario_inicio = !empty($_POST['horario_inicio'])
    ? trim($_POST['horario_inicio'])
    : null;
$horario_fim = !empty($_POST['horario_fim'])
    ? trim($_POST['horario_fim'])
    : null;
$duracao_consulta = !empty($_POST['duracao_consulta'])
    ? intval($_POST['duracao_consulta'])
    : 30;

if (
    empty($nome) ||
    empty($email) ||
    empty($senha) ||
    empty($crm) ||
    empty($especialidade)
) {
    $_SESSION['mensagem_sucesso'] = "Preencha todos os campos obrigatórios do médico (Nome, E-mail, Senha, CRM e Especialidade).";
    header("Location: ../secretaria.php?aba=medicos");
    exit;
}


try {
    /* VERIFICA E-MAIL DUPLICADO */
    $stmt = $conn->prepare("SELECT id_usuario FROM usuarios WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();

    if ($stmt->get_result()->num_rows > 0) {
        $stmt->close();
        $_SESSION['mensagem_sucesso'] = "Este e-mail já está cadastrado no sistema.";
        header("Location: ../secretaria.php?aba=medicos");
        exit;
    }
    $stmt->close();


    /* VERIFICA CPF DUPLICADO */
    if (!empty($cpf)) {
        $stmtCpf = $conn->prepare("SELECT id_usuario FROM usuarios WHERE cpf = ?");
        $stmtCpf->bind_param("s", $cpf);
        $stmtCpf->execute();

        if ($stmtCpf->get_result()->num_rows > 0) {
            $stmtCpf->close();
            $_SESSION['mensagem_sucesso'] = "Este CPF já está cadastrado no sistema.";
            header("Location: ../secretaria.php?aba=medicos");
            exit;
        }
        $stmtCpf->close();
    }


    /* INSERE */
    $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

    $sql = "
    INSERT INTO usuarios
    (
        nome, email, senha, perfil, id_clinica,
        cpf, rg, data_nascimento, sexo, telefone, celular,
        crm, uf_crm, especialidade,
        horario_inicio, horario_fim, duracao_consulta,
        status
    )
    VALUES
    (
        ?, ?, ?, 'medico', ?,
        ?, ?, ?, ?, ?, ?,
        ?, ?, ?,
        ?, ?, ?,
        'ativo'
    )
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "sssisssssssssssi",
        $nome,
        $email,
        $senhaHash,
        $id_clinica,
        $cpf,
        $rg,
        $data_nascimento,
        $sexo,
        $telefone,
        $celular,
        $crm,
        $uf_crm,
        $especialidade,
        $horario_inicio,
        $horario_fim,
        $duracao_consulta
    );

    if ($stmt->execute()) {
        $_SESSION['mensagem_sucesso'] = "Médico \"" . $nome . "\" cadastrado com sucesso!";
    } else {
        $_SESSION['mensagem_sucesso'] = "Erro ao cadastrar médico: " . $stmt->error;
    }
    $stmt->close();

} catch (Throwable $e) {
    $_SESSION['mensagem_sucesso'] = "Erro ao cadastrar médico: " . $e->getMessage();
}

header("Location: ../secretaria.php?aba=medicos");
exit;
