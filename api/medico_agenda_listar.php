<?php

require_once('../config/auth.php');
require_once('../config/database.php');

verificarPerfil('medico');

header('Content-Type: application/json');

$id_medico = $_SESSION['id_usuario'];

$stmt = $conn->prepare("
    SELECT id, data_disp, hora_inicio, hora_fim
    FROM agenda_medico
    WHERE id_medico = ? AND ativo = 1
    ORDER BY data_disp, hora_inicio
");
$stmt->bind_param("i", $id_medico);
$stmt->execute();
$res = $stmt->get_result();

$blocos = [];
while ($row = $res->fetch_assoc()) {
    $blocos[] = $row;
}

echo json_encode($blocos);
