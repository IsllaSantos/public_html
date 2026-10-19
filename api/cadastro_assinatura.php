<?php

require_once('../config/auth.php');
require_once('../config/database.php');

verificarPerfil('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../admin.php?aba=financeiro");
    exit;
}

$id_clinica = intval($_POST['id_clinica'] ?? 0);
$id_plano = intval($_POST['id_plano'] ?? 0);
$data_inicio = trim($_POST['data_inicio'] ?? '');
$data_vencimento = trim($_POST['data_vencimento'] ?? '');
$valor = floatval($_POST['valor'] ?? 0);

if (
    $id_clinica <= 0 ||
    $id_plano <= 0 ||
    empty($data_inicio) ||
    empty($data_vencimento) ||
    $valor <= 0
) {
    die("Preencha todos os campos obrigatórios.");
}

$sql = "
INSERT INTO assinaturas
(
    id_clinica,
    id_plano,
    data_inicio,
    data_vencimento,
    valor,
    status
)
VALUES (?, ?, ?, ?, ?, 'ativa')
";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "iissd",
    $id_clinica,
    $id_plano,
    $data_inicio,
    $data_vencimento,
    $valor
);

if ($stmt->execute()) {

    $_SESSION['mensagem_sucesso'] = "Assinatura cadastrada com sucesso!";
    header("Location: ../admin.php?aba=financeiro");
    exit;

} else {

    die("Erro ao cadastrar assinatura: " . $conn->error);
}
