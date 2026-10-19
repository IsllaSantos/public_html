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
    die("Erro: secretária sem clínica vinculada.");
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
    die("Preencha todos os campos obrigatórios.");
}


/* VERIFICA SE PACIENTE É DA CLÍNICA */

$stmt = $conn->prepare("
    SELECT id_usuario FROM usuarios
    WHERE id_usuario = ? AND id_clinica = ? AND perfil = 'paciente'
");
$stmt->bind_param("ii", $id_paciente, $id_clinica);
$stmt->execute();

if ($stmt->get_result()->num_rows === 0) {
    die("Paciente não pertence a esta clínica.");
}


/* VERIFICA SE MÉDICO É DA CLÍNICA */

$stmt = $conn->prepare("
    SELECT id_usuario FROM usuarios
    WHERE id_usuario = ? AND id_clinica = ? AND perfil = 'medico'
");
$stmt->bind_param("ii", $id_medico, $id_clinica);
$stmt->execute();

if ($stmt->get_result()->num_rows === 0) {
    die("Médico não pertence a esta clínica.");
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
    die("Erro ao agendar consulta: " . $conn->error);
}
