<?php

require_once('../config/auth.php');
require_once('../config/database.php');

verificarPerfil('paciente');

header('Content-Type: application/json');

$id_paciente = $_SESSION['id_usuario'];
$id_clinica  = $_SESSION['id_clinica'];

/* Parâmetro: ação */
$acao = $_GET['acao'] ?? '';


/* ==============================
   LISTAR MÉDICOS POR ESPECIALIDADE
============================== */

if ($acao === 'medicos') {

    $especialidade = trim($_GET['especialidade'] ?? '');

    if (empty($especialidade)) {
        echo json_encode([]);
        exit;
    }

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
   LISTAR HORÁRIOS DISPONÍVEIS
============================== */

if ($acao === 'horarios') {

    $id_medico = intval($_GET['id_medico'] ?? 0);
    $data      = trim($_GET['data'] ?? '');

    if ($id_medico <= 0 || empty($data)) {
        echo json_encode([]);
        exit;
    }

    /* Busca disponibilidade do médico nessa data */
    $stmt = $conn->prepare("
        SELECT hora_inicio, hora_fim
        FROM agenda_medico
        WHERE id_medico = ?
        AND data_disp = ?
        AND ativo = 1
        ORDER BY hora_inicio
    ");
    $stmt->bind_param("is", $id_medico, $data);
    $stmt->execute();
    $res = $stmt->get_result();

    $blocos = [];
    while ($row = $res->fetch_assoc()) {
        $blocos[] = $row;
    }

    if (empty($blocos)) {
        echo json_encode([]);
        exit;
    }

    /* Busca duração da consulta */
    $stmt = $conn->prepare("SELECT duracao_consulta FROM usuarios WHERE id_usuario = ?");
    $stmt->bind_param("i", $id_medico);
    $stmt->execute();
    $med = $stmt->get_result()->fetch_assoc();
    $duracao = intval($med['duracao_consulta'] ?? 30) ?: 30;

    /* Busca horários já agendados nessa data */
    $stmt = $conn->prepare("
        SELECT hora_consulta
        FROM consulta
        WHERE id_medico = ?
        AND data_consulta = ?
        AND status NOT IN ('cancelada')
    ");
    $stmt->bind_param("is", $id_medico, $data);
    $stmt->execute();
    $res = $stmt->get_result();

    $ocupados = [];
    while ($r = $res->fetch_assoc()) {
        $ocupados[] = substr($r['hora_consulta'], 0, 5);
    }

    /* Gera slots */
    $slots = [];
    foreach ($blocos as $bloco) {
        $inicio = strtotime($data . ' ' . $bloco['hora_inicio']);
        $fim    = strtotime($data . ' ' . $bloco['hora_fim']);

        while ($inicio + ($duracao * 60) <= $fim) {
            $hora = date('H:i', $inicio);
            if (!in_array($hora, $ocupados)) {
                $slots[] = $hora;
            }
            $inicio += $duracao * 60;
        }
    }

    echo json_encode($slots);
    exit;
}


/* ==============================
   LISTAR DATAS DISPONÍVEIS
============================== */

if ($acao === 'datas') {

    $id_medico = intval($_GET['id_medico'] ?? 0);

    if ($id_medico <= 0) {
        echo json_encode([]);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT DISTINCT data_disp
        FROM agenda_medico
        WHERE id_medico = ?
        AND data_disp >= CURDATE()
        AND ativo = 1
        ORDER BY data_disp
        LIMIT 60
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
   AGENDAR CONSULTA
============================== */

if ($acao === 'agendar' && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $id_medico = intval($_POST['id_medico'] ?? 0);
    $data      = trim($_POST['data'] ?? '');
    $hora      = trim($_POST['hora'] ?? '');
    $motivo    = trim($_POST['motivo'] ?? '');

    if ($id_medico <= 0 || empty($data) || empty($hora)) {
        echo json_encode(['erro' => 'Preencha todos os campos obrigatórios.']);
        exit;
    }

    /* Verifica se o médico pertence à clínica do paciente */
    $stmt = $conn->prepare("
        SELECT id_usuario FROM usuarios
        WHERE id_usuario = ? AND id_clinica = ? AND perfil = 'medico'
    ");
    $stmt->bind_param("ii", $id_medico, $id_clinica);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        echo json_encode(['erro' => 'Médico não encontrado nesta clínica.']);
        exit;
    }

    /* Verifica conflito de horário */
    $stmt = $conn->prepare("
        SELECT id_consulta FROM consulta
        WHERE id_medico = ? AND data_consulta = ? AND hora_consulta = ?
        AND status NOT IN ('cancelada')
    ");
    $stmt->bind_param("iss", $id_medico, $data, $hora);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['erro' => 'Este horário já está ocupado.']);
        exit;
    }

    /* Insere a consulta */
    $stmt = $conn->prepare("
        INSERT INTO consulta
            (id_paciente, id_medico, data_consulta, hora_consulta, motivo_consulta, status)
        VALUES
            (?, ?, ?, ?, ?, 'agendada')
    ");
    $stmt->bind_param("iisss", $id_paciente, $id_medico, $data, $hora, $motivo);

    if ($stmt->execute()) {
        echo json_encode(['sucesso' => true, 'mensagem' => 'Consulta agendada com sucesso!']);
    } else {
        echo json_encode(['erro' => 'Erro ao agendar: ' . $conn->error]);
    }
    exit;
}


/* ==============================
   LISTAR MINHAS CONSULTAS
============================== */

function garantirColunaConfirmacao($conn) {
    $r = @$conn->query("SHOW COLUMNS FROM consulta LIKE 'confirmado_paciente_em'");
    if ($r && $r->num_rows === 0) {
        @$conn->query("ALTER TABLE consulta ADD COLUMN confirmado_paciente_em DATETIME NULL");
    }
}

if ($acao === 'minhas_consultas') {

    garantirColunaConfirmacao($conn);

    $stmt = $conn->prepare("
        SELECT
            c.id_consulta,
            c.data_consulta,
            c.hora_consulta,
            c.motivo_consulta,
            c.status,
            c.confirmado_paciente_em,
            med.nome AS nome_medico,
            med.especialidade
        FROM consulta c
        INNER JOIN usuarios med ON c.id_medico = med.id_usuario
        WHERE c.id_paciente = ?
        ORDER BY c.data_consulta DESC, c.hora_consulta DESC
        LIMIT 50
    ");
    $stmt->bind_param("i", $id_paciente);
    $stmt->execute();
    $res = $stmt->get_result();

    $consultas = [];
    while ($r = $res->fetch_assoc()) {
        $consultas[] = $r;
    }

    echo json_encode($consultas);
    exit;
}


/* ==============================
   CONFIRMAR PRESENÇA (PACIENTE)
============================== */

if ($acao === 'confirmar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    garantirColunaConfirmacao($conn);
    $id_consulta = intval($_POST['id_consulta'] ?? 0);

    $stmt = $conn->prepare("
        UPDATE consulta SET confirmado_paciente_em = NOW()
        WHERE id_consulta = ? AND id_paciente = ? AND status = 'agendada'
        AND data_consulta >= CURDATE()
    ");
    $stmt->bind_param("ii", $id_consulta, $id_paciente);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo json_encode(['sucesso' => true, 'mensagem' => 'Presença confirmada! Até lá.']);
    } else {
        echo json_encode(['erro' => 'Não foi possível confirmar esta consulta.']);
    }
    exit;
}

/* ==============================
   CANCELAR CONSULTA (PACIENTE)
============================== */

if ($acao === 'cancelar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_consulta = intval($_POST['id_consulta'] ?? 0);

    if ($id_consulta <= 0) {
        echo json_encode(['erro' => 'Consulta inválida.']);
        exit;
    }

    // Verifica se a consulta pertence ao paciente e está agendada
    $stmt = $conn->prepare("
        SELECT id_consulta FROM consulta
        WHERE id_consulta = ? AND id_paciente = ? AND status = 'agendada'
    ");
    $stmt->bind_param("ii", $id_consulta, $id_paciente);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        echo json_encode(['erro' => 'Consulta não encontrada ou não pode ser cancelada.']);
        exit;
    }

    $stmtUp = $conn->prepare("UPDATE consulta SET status = 'cancelada' WHERE id_consulta = ?");
    $stmtUp->bind_param("i", $id_consulta);
    if ($stmtUp->execute()) {
        echo json_encode(['sucesso' => true, 'mensagem' => 'Consulta cancelada com sucesso! O horário foi liberado.']);
    } else {
        echo json_encode(['erro' => 'Erro ao cancelar consulta: ' . $conn->error]);
    }
    exit;
}


/* ==============================
   LISTAR RECEITAS & DOCUMENTOS DO PACIENTE
============================== */

if ($acao === 'minhas_receitas') {
    $stmt = $conn->prepare("
        SELECT
            c.id_consulta,
            c.data_consulta,
            med.nome AS nome_medico,
            med.crm AS crm_medico,
            med.uf_crm AS uf_medico,
            med.especialidade,
            r.medicamentos,
            se.exames,
            se.observacoes AS observacoes_exames
        FROM consulta c
        INNER JOIN usuarios med ON c.id_medico = med.id_usuario
        LEFT JOIN receita r ON c.id_consulta = r.id_consulta
        LEFT JOIN solicitacao_exame se ON c.id_consulta = se.id_consulta
        WHERE c.id_paciente = ?
        AND c.status = 'finalizada'
        AND (
            (r.medicamentos IS NOT NULL AND r.medicamentos != '')
            OR (se.exames IS NOT NULL AND se.exames != '')
        )
        ORDER BY c.data_consulta DESC
    ");
    $stmt->bind_param("i", $id_paciente);
    $stmt->execute();
    $res = $stmt->get_result();

    $receitas = [];
    while ($r = $res->fetch_assoc()) {
        $receitas[] = $r;
    }

    echo json_encode($receitas);
    exit;
}


echo json_encode(['erro' => 'Ação inválida.']);

