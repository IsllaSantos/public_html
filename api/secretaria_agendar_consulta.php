<?php

require_once('../config/auth.php');
require_once('../config/database.php');

verificarPerfil('secretaria');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../secretaria.php");
    exit;
}

$id_clinica = $_SESSION['id_clinica'];

if (empty($id_clinica)) {
    $_SESSION['mensagem_sucesso'] = "Erro: secretária sem clínica vinculada.";
    header("Location: ../secretaria.php?aba=agendamentos");
    exit;
}


/* CAMPOS */

$id_paciente = intval($_POST['id_paciente'] ?? 0);
$id_medico = intval($_POST['id_medico'] ?? 0);
$data_consulta = trim($_POST['data_consulta'] ?? '');
$hora_consulta = trim($_POST['hora_consulta'] ?? '');
$tipo_consulta = trim($_POST['tipo_consulta'] ?? 'presencial');
$motivo_consulta = trim($_POST['motivo_consulta'] ?? '');
$observacoes = trim($_POST['observacoes'] ?? '');

if (
    $id_paciente <= 0 ||
    $id_medico <= 0 ||
    empty($data_consulta) ||
    empty($hora_consulta)
) {
    $_SESSION['mensagem_sucesso'] = "Preencha todos os campos obrigatórios.";
    header("Location: ../secretaria.php?aba=agendamentos");
    exit;
}


/* VERIFICA SE PACIENTE É DA CLÍNICA */

$stmt = $conn->prepare("
    SELECT id_usuario FROM usuarios
    WHERE id_usuario = ? AND id_clinica = ? AND perfil = 'paciente'
");
$stmt->bind_param("ii", $id_paciente, $id_clinica);
$stmt->execute();

if ($stmt->get_result()->num_rows === 0) {
    $_SESSION['mensagem_sucesso'] = "Erro: Paciente não pertence a esta clínica.";
    header("Location: ../secretaria.php?aba=agendamentos");
    exit;
}


/* VERIFICA SE MÉDICO É DA CLÍNICA */

$stmt = $conn->prepare("
    SELECT id_usuario FROM usuarios
    WHERE id_usuario = ? AND id_clinica = ? AND perfil = 'medico'
");
$stmt->bind_param("ii", $id_medico, $id_clinica);
$stmt->execute();

if ($stmt->get_result()->num_rows === 0) {
    $_SESSION['mensagem_sucesso'] = "Erro: Médico não pertence a esta clínica.";
    header("Location: ../secretaria.php?aba=agendamentos");
    exit;
}

/* VERIFICA CONFLITO DE HORÁRIO DO MÉDICO */
$stmtConflito = $conn->prepare("
    SELECT id_consulta FROM consulta
    WHERE id_medico = ? AND data_consulta = ? AND hora_consulta = ? AND status != 'cancelada'
");
$stmtConflito->bind_param("iss", $id_medico, $data_consulta, $hora_consulta);
$stmtConflito->execute();
if ($stmtConflito->get_result()->num_rows > 0) {
    $_SESSION['mensagem_sucesso'] = "Erro: O médico já possui uma consulta agendada para este dia e horário.";
    header("Location: ../secretaria.php?aba=agendamentos");
    exit;
}


/* INSERE A CONSULTA */

$sql = "
INSERT INTO consulta
(
    id_paciente,
    id_medico,
    data_consulta,
    hora_consulta,
    tipo_consulta,
    motivo_consulta,
    observacoes,
    status
)
VALUES (?, ?, ?, ?, ?, ?, ?, 'agendada')
";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "iisssss",
    $id_paciente,
    $id_medico,
    $data_consulta,
    $hora_consulta,
    $tipo_consulta,
    $motivo_consulta,
    $observacoes
);

if ($stmt->execute()) {
    $_SESSION['mensagem_sucesso'] = "Consulta agendada com sucesso!";
    header("Location: ../secretaria.php?aba=agendamentos");
    exit;
} else {
    $_SESSION['mensagem_sucesso'] = "Erro ao agendar consulta: " . $conn->error;
    header("Location: ../secretaria.php?aba=agendamentos");
    exit;
}
