<?php

require_once('../config/auth.php');
require_once('../config/database.php');

verificarPerfil('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../admin.php?aba=especialidades");
    exit;
}

$nome = trim($_POST['nome'] ?? '');

if (empty($nome)) {
    die("Nome da especialidade é obrigatório.");
}

/* Verifica duplicata */
$stmt = $conn->prepare("SELECT id_especialidade FROM especialidades WHERE nome = ?");
$stmt->bind_param("s", $nome);
$stmt->execute();

if ($stmt->get_result()->num_rows > 0) {
    die("Esta especialidade já existe.");
}

/* Insere */
$stmt = $conn->prepare("INSERT INTO especialidades (nome, status) VALUES (?, 'ativa')");
$stmt->bind_param("s", $nome);

if ($stmt->execute()) {
    $_SESSION['mensagem_sucesso'] = "Especialidade cadastrada com sucesso!";
    header("Location: ../admin.php?aba=especialidades");
    exit;
} else {
    die("Erro ao cadastrar: " . $conn->error);
}
