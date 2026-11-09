<?php

require_once('../config/auth.php');
require_once('../config/database.php');
require_once('../config/logs.php');

verificarPerfil('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../admin.php?aba=planos");
    exit;
}

/* RECEBE OS DADOS */

$nome = trim($_POST['nome'] ?? '');
$descricao = trim($_POST['descricao'] ?? '');
$valor = floatval($_POST['valor'] ?? 0);
$max_medicos = !empty($_POST['max_medicos'])
    ? intval($_POST['max_medicos'])
    : null;
$max_armazenamento_gb = !empty($_POST['max_armazenamento_gb'])
    ? intval($_POST['max_armazenamento_gb'])
    : null;
$recursos = trim($_POST['recursos'] ?? '');
$destaque = isset($_POST['destaque']) ? 1 : 0;

if (empty($nome) || $valor <= 0) {
    $_SESSION['mensagem_sucesso'] = "Preencha o nome do plano e um valor válido.";
    header("Location: ../admin.php?aba=planos");
    exit;
}

/* Converte recursos (um por linha → separado por |) */
$recursos = implode('|', array_filter(
    array_map('trim', explode("\n", $recursos))
));

try {
    /* INSERE */
    $sql = "
    INSERT INTO planos
    (
        nome,
        descricao,
        valor,
        max_medicos,
        max_armazenamento_gb,
        recursos,
        destaque
    )
    VALUES (?, ?, ?, ?, ?, ?, ?)
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "ssdiisi",
        $nome,
        $descricao,
        $valor,
        $max_medicos,
        $max_armazenamento_gb,
        $recursos,
        $destaque
    );

    if ($stmt->execute()) {
        $_SESSION['mensagem_sucesso'] = "Plano \"" . $nome . "\" cadastrado com sucesso!";
        registrarLog($conn, "Cadastro de plano");
    } else {
        $_SESSION['mensagem_sucesso'] = "Erro ao cadastrar plano: " . $stmt->error;
    }
    $stmt->close();

} catch (Throwable $e) {
    $_SESSION['mensagem_sucesso'] = "Erro ao cadastrar plano: " . $e->getMessage();
}

header("Location: ../admin.php?aba=planos");
exit;
