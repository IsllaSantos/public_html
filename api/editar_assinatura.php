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
$id_plano = intval($_POST['id_plano'] ?? 0);
$data_inicio = trim($_POST['data_inicio'] ?? '');
$data_vencimento = trim($_POST['data_vencimento'] ?? '');
$valor = floatval($_POST['valor'] ?? 0);

if ($id <= 0 || $id_plano <= 0 || $data_inicio === '' || $data_vencimento === '' || $valor <= 0) {
    $_SESSION['mensagem_sucesso'] = "Preencha todos os campos obrigatórios da assinatura.";
    header($voltar);
    exit;
}

if ($data_vencimento < $data_inicio) {
    $_SESSION['mensagem_sucesso'] = "A data de vencimento não pode ser anterior ao início.";
    header($voltar);
    exit;
}

try {
    $pk = pkAssinatura($conn);

    $stmt = $conn->prepare("
        UPDATE assinaturas
        SET id_plano = ?, valor = ?, data_inicio = ?, data_vencimento = ?
        WHERE `$pk` = ? AND status = 'ativa'
    ");
    $stmt->bind_param("idssi", $id_plano, $valor, $data_inicio, $data_vencimento, $id);
    $stmt->execute();
    $afetadas = $stmt->affected_rows;
    $stmt->close();

    registrarLog($conn, 'Edição de assinatura #' . $id);
    $_SESSION['mensagem_sucesso'] = $afetadas >= 0
        ? "Assinatura atualizada com sucesso!"
        : "Não foi possível atualizar a assinatura.";
} catch (Throwable $e) {
    $_SESSION['mensagem_sucesso'] = "Erro ao editar assinatura: " . $e->getMessage();
}

header($voltar);
exit;

