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
    $_SESSION['mensagem_sucesso'] = "Nome da especialidade é obrigatório.";
    header("Location: ../admin.php?aba=especialidades");
    exit;
}

try {
    /* Verifica duplicata */
    $stmt = $conn->prepare("SELECT id_especialidade FROM especialidades WHERE LOWER(nome) = LOWER(?)");
    $stmt->bind_param("s", $nome);
    $stmt->execute();

    if ($stmt->get_result()->num_rows > 0) {
        $stmt->close();
        $_SESSION['mensagem_sucesso'] = "Esta especialidade já existe.";
        header("Location: ../admin.php?aba=especialidades");
        exit;
    }
    $stmt->close();

    /* Insere */
    $stmt = $conn->prepare("INSERT INTO especialidades (nome, status) VALUES (?, 'ativa')");
    $stmt->bind_param("s", $nome);
    $stmt->execute();
    $stmt->close();

    $_SESSION['mensagem_sucesso'] = "Especialidade cadastrada com sucesso!";
} catch (Throwable $e) {
    $_SESSION['mensagem_sucesso'] = "Erro ao cadastrar especialidade: " . $e->getMessage();
}

header("Location: ../admin.php?aba=especialidades");
exit;
