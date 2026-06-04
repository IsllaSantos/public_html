<?php

require_once('../config/auth.php');
require_once('../config/database.php');

verificarPerfil('admin');

if ($_SERVER['REQUEST_METHOD'] != 'POST') {

    header("Location: ../admin.php");
    exit;
}

$nome = trim($_POST['nome']);
$email = trim($_POST['email']);
$senha = $_POST['senha'];
$perfil = $_POST['perfil'];

if (
    empty($nome) ||
    empty($email) ||
    empty($senha) ||
    empty($perfil)
) {

    die("Preencha todos os campos.");
}

/* VERIFICA E-MAIL */

$sql = "
SELECT id_usuario
FROM usuarios
WHERE email = ?
";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "s",
    $email
);

$stmt->execute();

$resultado = $stmt->get_result();

if ($resultado->num_rows > 0) {

    die("E-mail já cadastrado.");
}

/* CRIPTOGRAFA SENHA */

$senhaHash =
password_hash(
    $senha,
    PASSWORD_DEFAULT
);

/* INSERE */

$sql = "
INSERT INTO usuarios
(
    nome,
    email,
    senha,
    perfil,
    status
)
VALUES
(
    ?,
    ?,
    ?,
    ?,
    'ativo'
)
";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "ssss",
    $nome,
    $email,
    $senhaHash,
    $perfil
);

if ($stmt->execute()) {

    header(
        "Location: ../admin.php?sucesso=1"
    );

    exit;

} else {

    die(
        "Erro ao cadastrar usuário: " .
        $conn->error
    );
}