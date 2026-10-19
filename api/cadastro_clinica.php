<?php

require_once('../config/auth.php');
require_once('../config/database.php');

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
    die("Preencha os campos obrigatórios.");
}

/* VERIFICA SE O CNPJ JÁ EXISTE */

$sql = "
SELECT id_clinica
FROM clinica
WHERE cnpj = ?
";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "s",
    $cnpj
);

$stmt->execute();

$resultado = $stmt->get_result();

if ($resultado->num_rows > 0) {
    die("CNPJ já cadastrado.");
}

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

if ($stmt->execute()) {

    $_SESSION['mensagem_sucesso'] = "Clínica cadastrada com sucesso!";

    header("Location: ../admin.php?aba=clinicas");
    exit;


} else {

    die(
        "Erro ao cadastrar clínica: " .
        $conn->error
    );
}