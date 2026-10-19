<?php

require_once('../config/auth.php');
require_once('../config/database.php');

verificarPerfil('medico');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erro' => 'Método inválido.']);
    exit;
}

$id_medico = $_SESSION['id_usuario'];
$id        = intval($_POST['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['erro' => 'ID inválido.']);
    exit;
}

/* GARANTE QUE O BLOCO PERTENCE AO MÉDICO LOGADO */

$stmt = $conn->prepare("
    UPDATE agenda_medico
    SET ativo = 0
    WHERE id = ? AND id_medico = ?
");
$stmt->bind_param("ii", $id, $id_medico);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode(['sucesso' => true]);
} else {
    echo json_encode(['erro' => 'Bloco não encontrado ou sem permissão.']);
}
