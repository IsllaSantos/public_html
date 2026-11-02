<?php

require_once('../config/auth.php');
require_once('../config/database.php');

verificarPerfil('secretaria');

header('Content-Type: application/json');

$id_clinica = $_SESSION['id_clinica'];
$acao       = trim($_GET['acao'] ?? '');
$id_medico  = intval($_GET['id_medico'] ?? 0);
$data       = trim($_GET['data'] ?? '');

/* ==============================
   1. AÇÃO: MÉDICOS (POR ESPECIALIDADE OU TODOS DA CLÍNICA)
============================== */
if ($acao === 'medicos') {
    $especialidade = trim($_GET['especialidade'] ?? '');

    if (!empty($especialidade) && $especialidade !== 'todas') {
        $stmt = $conn->prepare("
            SELECT id_usuario, nome, especialidade, duracao_consulta
            FROM usuarios
            WHERE id_clinica = ?
            AND perfil = 'medico'
            AND status = 'ativo'
            AND especialidade = ?
            ORDER BY nome
        ");
        $stmt->bind_param("is", $id_clinica, $especialidade);
    } else {
        $stmt = $conn->prepare("
            SELECT id_usuario, nome, especialidade, duracao_consulta
            FROM usuarios
            WHERE id_clinica = ?
            AND perfil = 'medico'
            AND status = 'ativo'
            ORDER BY nome
        ");
        $stmt->bind_param("i", $id_clinica);
    }

    $stmt->execute();
    $res = $stmt->get_result();
    $medicos = [];
    while ($m = $res->fetch_assoc()) {
        $medicos[] = $m;
    }

    echo json_encode($medicos);
    exit;
}

/* ==============================
   2. AÇÃO: DATAS DISPONÍVEIS DO MÉDICO
============================== */
if ($acao === 'datas' || (empty($data) && $id_medico > 0)) {
    if ($id_medico <= 0) {
        echo json_encode([]);
        exit;
    }

    // Verifica que o médico pertence à clínica
    $stmt = $conn->prepare("
        SELECT id_usuario FROM usuarios
        WHERE id_usuario = ? AND id_clinica = ? AND perfil = 'medico' AND status = 'ativo'
    ");
    $stmt->bind_param("ii", $id_medico, $id_clinica);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        echo json_encode([]);
        exit;
    }

    // Busca datas a partir de hoje cadastradas na agenda do médico
    $stmt = $conn->prepare("
        SELECT DISTINCT data_disp
        FROM agenda_medico
        WHERE id_medico = ?
        AND data_disp >= CURDATE()
        AND ativo = 1
        ORDER BY data_disp ASC
    ");
    $stmt->bind_param("i", $id_medico);
    $stmt->execute();
    $res = $stmt->get_result();

    $datas = [];
    while ($r = $res->fetch_assoc()) {
        $datas[] = $r['data_disp'];
    }

    echo json_encode($datas);
    exit;
}

/* ==============================
   3. AÇÃO: HORÁRIOS / SLOTS DISPONÍVEIS
============================== */
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

$duracao = intval($medico['duracao_consulta'] ?? 30) ?: 30;

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
