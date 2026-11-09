<?php

require_once('../config/auth.php');
require_once('../config/database.php');
require_once('../config/assinaturas.php');
require_once('../config/logs.php');

verificarPerfil('admin');

$voltar = "Location: ../admin.php?aba=financeiro";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header($voltar);
    exit;
}

$id = intval($_POST['id_assinatura'] ?? 0);

if ($id <= 0) {
    $_SESSION['mensagem_sucesso'] = "Assinatura inválida.";
    header($voltar);
    exit;
}

try {
    $pk = pkAssinatura($conn);

    $stmt = $conn->prepare("UPDATE assinaturas SET status = 'cancelada' WHERE `$pk` = ? AND status = 'ativa'");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $afetadas = $stmt->affected_rows;
    $stmt->close();

    if ($afetadas > 0) { registrarLog($conn, 'Cancelamento de assinatura #' . $id); }
    $_SESSION['mensagem_sucesso'] = $afetadas > 0
        ? "Assinatura cancelada com sucesso!"
        : "Não foi possível cancelar: assinatura não encontrada ou já cancelada.";
} catch (Throwable $e) {
    $_SESSION['mensagem_sucesso'] = "Erro ao cancelar assinatura: " . $e->getMessage();
}

header($voltar);
exit;

