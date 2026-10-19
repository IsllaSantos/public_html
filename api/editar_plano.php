<?php

require_once('../config/auth.php');
require_once('../config/database.php');

verificarPerfil('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../admin.php?aba=planos");
    exit;
}

$id_plano = intval($_POST['id_plano'] ?? 0);
if ($id_plano <= 0) die("Plano inválido.");

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
    die("Preencha o nome e um valor válido.");
}

if ($status !== 'ativo' && $status !== 'inativo') {
    die("Status inválido.");
}

$recursos = implode('|', array_filter(
    array_map('trim', explode("\n", $recursos))
));

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

    $_SESSION['mensagem_sucesso'] = "Plano atualizado com sucesso!";
    header("Location: ../admin.php?aba=planos");
    exit;

} else {

    die("Erro ao editar plano: " . $conn->error);
}
