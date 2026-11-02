<?php

require_once('../config/auth.php');
require_once('../config/database.php');

verificarPerfil('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../admin.php?aba=planos");
    exit;
}

$id_plano = intval($_POST['id_plano'] ?? 0);
if ($id_plano <= 0) {
    $_SESSION['mensagem_sucesso'] = "Plano inválido.";
    header("Location: ../admin.php?aba=planos");
    exit;
}

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
$status = trim($_POST['status'] ?? 'ativo');

if (empty($nome) || $valor <= 0) {
    $_SESSION['mensagem_sucesso'] = "Preencha o nome do plano e um valor válido.";
    header("Location: ../editar_plano.php?id=" . $id_plano);
    exit;
}

if ($status !== 'ativo' && $status !== 'inativo') {
    $_SESSION['mensagem_sucesso'] = "Status do plano inválido.";
    header("Location: ../editar_plano.php?id=" . $id_plano);
    exit;
}

$recursos = implode('|', array_filter(
    array_map('trim', explode("\n", $recursos))
));

try {
    $sql = "
    UPDATE planos
    SET
        nome = ?,
        descricao = ?,
        valor = ?,
        max_medicos = ?,
        max_armazenamento_gb = ?,
        recursos = ?,
        destaque = ?,
        status = ?
    WHERE id_plano = ?
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "ssdiisisi",
        $nome,
        $descricao,
        $valor,
        $max_medicos,
        $max_armazenamento_gb,
        $recursos,
        $destaque,
        $status,
        $id_plano
    );

    if ($stmt->execute()) {
        $_SESSION['mensagem_sucesso'] = "Plano \"" . $nome . "\" atualizado com sucesso!";
        header("Location: ../admin.php?aba=planos");
        exit;
    } else {
        $_SESSION['mensagem_sucesso'] = "Erro ao editar plano: " . $stmt->error;
        header("Location: ../editar_plano.php?id=" . $id_plano);
        exit;
    }

} catch (Throwable $e) {
    $_SESSION['mensagem_sucesso'] = "Erro ao editar plano: " . $e->getMessage();
    header("Location: ../editar_plano.php?id=" . $id_plano);
    exit;
}
