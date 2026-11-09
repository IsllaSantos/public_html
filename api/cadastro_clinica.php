<?php

require_once('../config/auth.php');
require_once('../config/database.php');
require_once('../config/logs.php');

verificarPerfil('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../admin.php");
    exit;
}

/* RECEBE OS DADOS */

$nome = trim($_POST['nome'] ?? '');
$cnpj = preg_replace('/\D/', '', trim($_POST['cnpj'] ?? ''));
$telefone = preg_replace('/\D/', '', trim($_POST['telefone'] ?? ''));
$email = trim($_POST['email'] ?? '');
$cep = preg_replace('/\D/', '', trim($_POST['cep'] ?? ''));
$endereco = trim($_POST['endereco'] ?? '');
$numero = trim($_POST['numero'] ?? '');
$complemento = trim($_POST['complemento'] ?? '');
$bairro = trim($_POST['bairro'] ?? '');
$cidade = trim($_POST['cidade'] ?? '');
$estado = trim($_POST['estado'] ?? '');

/* VALIDAÇÃO */

if (
    empty($nome) ||
    empty($cnpj)
) {
    $_SESSION['mensagem_sucesso'] = "Preencha todos os campos obrigatórios da clínica.";
    header("Location: ../admin.php?aba=clinicas");
    exit;
}

try {
    /* VERIFICA SE O CNPJ JÁ EXISTE */
    $sql = "
    SELECT id_clinica
    FROM clinica
    WHERE cnpj = ?
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $cnpj);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows > 0) {
        $stmt->close();
        $_SESSION['mensagem_sucesso'] = "Este CNPJ já está cadastrado no sistema.";
        header("Location: ../admin.php?aba=clinicas");
        exit;
    }
    $stmt->close();

    /* CADASTRA A CLÍNICA */
    $sql = "
    INSERT INTO clinica
    (
        nome,
        cnpj,
        telefone,
        email,
        cep,
        endereco,
        numero,
        complemento,
        bairro,
        cidade,
        estado,
        status
    )
    VALUES
    (
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
        'ativa'
    )
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "sssssssssss",
        $nome,
        $cnpj,
        $telefone,
        $email,
        $cep,
        $endereco,
        $numero,
        $complemento,
        $bairro,
        $cidade,
        $estado
    );

    $stmt->execute();
    $stmt->close();

    $_SESSION['mensagem_sucesso'] = "Clínica \"" . $nome . "\" cadastrada com sucesso!";
    registrarLog($conn, "Cadastro de clínica: " . $nome . "");

} catch (Throwable $e) {
    $_SESSION['mensagem_sucesso'] = "Erro ao cadastrar clínica: " . $e->getMessage();
}

header("Location: ../admin.php?aba=clinicas");
exit;
