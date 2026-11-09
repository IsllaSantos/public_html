<?php

ob_start();
error_reporting(0);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');

try {
    require_once('../config/auth.php');
    require_once('../config/database.php');
    require_once('../config/status_consulta.php');
    require_once('../config/logs.php');
    garantirStatusConsulta($conn);

    if (session_status() === PHP_SESSION_NONE) {
        require_once __DIR__ . '/../config/sessao.php';
    }

    if (!isset($_SESSION['id_usuario']) || !isset($_SESSION['perfil']) || $_SESSION['perfil'] !== 'medico') {
        ob_clean();
        echo json_encode(['erro' => 'Sessão expirada ou acesso não autorizado. Por favor, faça login novamente.']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ob_clean();
        echo json_encode(['erro' => 'Método não permitido.']);
        exit;
    }

    $id_medico   = intval($_SESSION['id_usuario']);
    $id_consulta = intval($_POST['id_consulta'] ?? 0);

    if ($id_consulta <= 0) {
        ob_clean();
        echo json_encode(['erro' => 'Consulta inválida.']);
        exit;
    }

    /* VERIFICA SE A CONSULTA PERTENCE AO MÉDICO */
    $stmt = $conn->prepare("
        SELECT id_consulta, status
        FROM consulta
        WHERE id_consulta = ? AND id_medico = ?
    ");
    if (!$stmt) {
        ob_clean();
        echo json_encode(['erro' => 'Erro ao consultar agendamento.']);
        exit;
    }
    $stmt->bind_param("ii", $id_consulta, $id_medico);
    $stmt->execute();
    $res = $stmt->get_result();
    $consulta = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$consulta) {
        ob_clean();
        echo json_encode(['erro' => 'Consulta não encontrada.']);
        exit;
    }

    if ($consulta['status'] === 'cancelada') {
        ob_clean();
        echo json_encode(['erro' => 'Esta consulta foi cancelada e não pode ser finalizada.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($consulta['status'] === 'finalizada') {
        ob_clean();
        echo json_encode(['sucesso' => true, 'mensagem' => 'Consulta já estava finalizada.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* FINALIZA A CONSULTA */
    try {
        $stmtUp = $conn->prepare("UPDATE consulta SET status = 'finalizada', fim_atendimento = NOW() WHERE id_consulta = ?");
        $stmtUp->bind_param("i", $id_consulta);
        $stmtUp->execute();
        $stmtUp->close();
    } catch (Throwable $eFim) {
        $stmtUp = $conn->prepare("UPDATE consulta SET status = 'finalizada' WHERE id_consulta = ?");
        $stmtUp->bind_param("i", $id_consulta);
        $stmtUp->execute();
        $stmtUp->close();
    }

    /* CONFIRMA QUE O STATUS FOI GRAVADO */
    $chk = $conn->prepare("SELECT status FROM consulta WHERE id_consulta = ?");
    $chk->bind_param("i", $id_consulta);
    $chk->execute();
    $rowChk = $chk->get_result()->fetch_assoc();
    $chk->close();
    if (!$rowChk || $rowChk['status'] !== 'finalizada') {
        ob_clean();
        echo json_encode(['erro' => 'Não foi possível atualizar o status da consulta.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    registrarLog($conn, 'Consulta finalizada #' . $id_consulta);
    ob_clean();
    echo json_encode(['sucesso' => true, 'mensagem' => 'Consulta finalizada com sucesso!'], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $eGlobal) {
    ob_clean();
    echo json_encode(['erro' => 'Erro ao finalizar consulta: ' . $eGlobal->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

