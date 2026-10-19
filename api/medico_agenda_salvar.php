<?php

require_once('../config/auth.php');
require_once('../config/database.php');

verificarPerfil('medico');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erro' => 'Método inválido.']);
    exit;
}

$id_medico    = $_SESSION['id_usuario'];
$data_inicio  = trim($_POST['data_inicio'] ?? '');
$hora_inicio  = trim($_POST['hora_inicio'] ?? '');
$hora_fim     = trim($_POST['hora_fim']    ?? '');
$recorrente   = intval($_POST['recorrente'] ?? 0); // 0 = não, 1 = sim
$num_semanas  = intval($_POST['num_semanas'] ?? 1);

/* VALIDAÇÕES BÁSICAS */

if (empty($data_inicio) || empty($hora_inicio) || empty($hora_fim)) {
    echo json_encode(['erro' => 'Preencha todos os campos.']);
    exit;
}

if ($hora_fim <= $hora_inicio) {
    echo json_encode(['erro' => 'Hora fim deve ser maior que hora início.']);
    exit;
}

$limite = date('Y-m-d', strtotime('+2 months'));

if ($data_inicio > $limite) {
    echo json_encode(['erro' => 'Data não pode ultrapassar 2 meses no futuro.']);
    exit;
}

if ($data_inicio < date('Y-m-d')) {
    echo json_encode(['erro' => 'Data não pode ser no passado.']);
    exit;
}

/* MONTA AS DATAS A INSERIR */

$datas = [$data_inicio];

if ($recorrente && $num_semanas > 1) {
    $max_semanas = min($num_semanas, 8); // máximo 8 semanas (~2 meses)
    for ($i = 1; $i < $max_semanas; $i++) {
        $proxima = date('Y-m-d', strtotime($data_inicio . " +{$i} weeks"));
        if ($proxima > $limite) break;
        $datas[] = $proxima;
    }
}

/* INSERE */

$stmt = $conn->prepare("
    INSERT IGNORE INTO agenda_medico
        (id_medico, data_disp, hora_inicio, hora_fim)
    VALUES (?, ?, ?, ?)
");

$inseridos = 0;
foreach ($datas as $data) {
    $stmt->bind_param("isss", $id_medico, $data, $hora_inicio, $hora_fim);
    if ($stmt->execute()) $inseridos++;
}

if ($inseridos > 0) {
    echo json_encode(['sucesso' => true, 'inseridos' => $inseridos]);
} else {
    echo json_encode(['erro' => 'Nenhuma data foi inserida. Pode ser que já existam blocos para essas datas e horários.']);
}
