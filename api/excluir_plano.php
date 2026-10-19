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

/* Verifica se há assinaturas vinculadas */

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM assinaturas
    WHERE id_plano = ?
");

$stmt->bind_param("i", $id_plano);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if ($row['total'] > 0) {

    $_SESSION['mensagem_sucesso'] =
        "Não é possível excluir: existem " .
        $row['total'] .
        " assinatura(s) vinculada(s) a este plano.";

    header("Location: ../admin.php?aba=planos");
    exit;
}

/* Exclui */

$stmt = $conn->prepare("
    DELETE FROM planos
    WHERE id_plano = ?
");

$stmt->bind_param("i", $id_plano);

if ($stmt->execute()) {

    $_SESSION['mensagem_sucesso'] = "Plano excluído com sucesso!";
    header("Location: ../admin.php?aba=planos");
    exit;

} else {

    die("Erro ao excluir plano: " . $conn->error);
}
