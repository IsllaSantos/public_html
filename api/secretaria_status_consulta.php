<?php

require_once('../config/auth.php');
require_once('../config/database.php');

verificarPerfil('secretaria');

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
          || (isset($_POST['ajax']) && $_POST['ajax'] === '1')
          || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
          || (function_exists('ehRequisicaoApi') && ehRequisicaoApi());

$abaRetorno = trim($_POST['aba'] ?? 'dashboard');
if (!in_array($abaRetorno, ['dashboard', 'agendamentos', 'medicos', 'pacientes'])) {
    $abaRetorno = 'dashboard';
}

function responderErroStatus($msg, $isAjax, $abaRetorno) {
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['sucesso' => false, 'erro' => $msg]);
        exit;
    }
    $_SESSION['mensagem_sucesso'] = "Erro: " . $msg;
    header("Location: ../secretaria.php?aba=" . urlencode($abaRetorno));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($isAjax) {
        responderErroStatus("Método não permitido.", true, $abaRetorno);
    }
    header("Location: ../secretaria.php");
    exit;
}

$id_clinica = $_SESSION['id_clinica'] ?? null;
if (empty($id_clinica)) {
    responderErroStatus("Secretária sem clínica vinculada.", $isAjax, $abaRetorno);
}

$id_consulta = intval($_POST['id_consulta'] ?? 0);
$novo_status = trim($_POST['status'] ?? '');

if ($id_consulta <= 0) {
    responderErroStatus("Consulta inválida.", $isAjax, $abaRetorno);
}

$statusPermitidos = ['aguardando_atendimento', 'cancelada', 'agendada'];
if (!in_array($novo_status, $statusPermitidos)) {
    responderErroStatus("Status inválido.", $isAjax, $abaRetorno);
}


/* VERIFICA SE A CONSULTA PERTENCE À CLÍNICA */

$stmt = $conn->prepare("
    SELECT c.id_consulta
    FROM consulta c
    INNER JOIN usuarios p ON c.id_paciente = p.id_usuario
    WHERE c.id_consulta = ?
    AND p.id_clinica = ?
");

$stmt->bind_param("ii", $id_consulta, $id_clinica);
$stmt->execute();

if ($stmt->get_result()->num_rows === 0) {
    responderErroStatus("Consulta não encontrada nesta clínica.", $isAjax, $abaRetorno);
}


/* ATUALIZA O STATUS */

if ($novo_status === 'aguardando_atendimento') {

    $stmt = $conn->prepare("
        UPDATE consulta
        SET status = 'aguardando_atendimento',
            chegada_em = NOW()
        WHERE id_consulta = ?
    ");
    $stmt->bind_param("i", $id_consulta);

} else {

    $stmt = $conn->prepare("
        UPDATE consulta
        SET status = ?
        WHERE id_consulta = ?
    ");
    $stmt->bind_param("si", $novo_status, $id_consulta);
}

if ($stmt->execute()) {
    $msg = ($novo_status === 'aguardando_atendimento') 
        ? "Presença confirmada! Paciente encaminhado para atendimento." 
        : "Status da consulta atualizado para " . htmlspecialchars($novo_status) . ".";

    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'sucesso' => true,
            'novo_status' => $novo_status,
            'mensagem' => $msg
        ]);
        exit;
    }

    $_SESSION['mensagem_sucesso'] = $msg;
    header("Location: ../secretaria.php?aba=" . urlencode($abaRetorno));
    exit;
} else {
    responderErroStatus("Erro ao atualizar consulta: " . $conn->error, $isAjax, $abaRetorno);
}
