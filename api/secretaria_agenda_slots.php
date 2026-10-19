<?php

require_once('../config/auth.php');
require_once('../config/database.php');

verificarPerfil('secretaria');

header('Content-Type: application/json');

$id_clinica = $_SESSION['id_clinica'];
$id_medico  = intval($_GET['id_medico'] ?? 0);
$data       = trim($_GET['data'] ?? '');

if ($id_medico <= 0 || empty($data)) {
    echo json_encode([]);
    exit;
}

/* VERIFICA QUE O MÉDICO PERTENCE À CLÍNICA */

$stmt = $conn->prepare("
    SELECT duracao_consulta
    FROM usuarios
    WHERE id_usuario = ? AND id_clinica = ? AND perfil = 'medico' AND status = 'ativo'
");
$stmt->bind_param("ii", $id_medico, $id_clinica);
$stmt->execute();
$medico = $stmt->get_result()->fetch_assoc();

if (!$medico) {
    echo json_encode([]);
    exit;
}

$duracao = intval($medico['duracao_consulta']) ?: 30;


/* BLOCOS DISPONÍVEIS DO MÉDICO NESSA DATA */

$stmt = $conn->prepare("
    SELECT hora_inicio, hora_fim
    FROM agenda_medico
    WHERE id_medico = ? AND data_disp = ? AND ativo = 1
    ORDER BY hora_inicio
");
$stmt->bind_param("is", $id_medico, $data);
$stmt->execute();
$blocos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if (empty($blocos)) {
    echo json_encode([]);
    exit;
}


/* CONSULTAS JÁ AGENDADAS NESSA DATA/MÉDICO */

$stmt = $conn->prepare("
    SELECT hora_consulta
    FROM consulta
    WHERE id_medico = ? AND data_consulta = ?
    AND status NOT IN ('cancelada')
");
$stmt->bind_param("is", $id_medico, $data);
$stmt->execute();
$res = $stmt->get_result();

$ocupados = [];
while ($row = $res->fetch_assoc()) {
    $ocupados[] = substr($row['hora_consulta'], 0, 5); // HH:MM
}


/* GERA OS SLOTS */

$slots = [];

foreach ($blocos as $bloco) {
    $atual = strtotime($data . ' ' . $bloco['hora_inicio']);
    $fim   = strtotime($data . ' ' . $bloco['hora_fim']);

    while ($atual + ($duracao * 60) <= $fim) {
        $hora = date('H:i', $atual);
        if (!in_array($hora, $ocupados)) {
            $slots[] = $hora;
        }
        $atual += $duracao * 60;
    }
}

echo json_encode($slots);
