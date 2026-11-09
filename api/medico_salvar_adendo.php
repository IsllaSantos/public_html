<?php

ob_start();
error_reporting(0);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');

try {
    require_once('../config/auth.php');
    require_once('../config/database.php');
    require_once('../config/logs.php');

    if (!isset($_SESSION['id_usuario']) || ($_SESSION['perfil'] ?? '') !== 'medico') {
        ob_clean();
        echo json_encode(['erro' => 'Sessão expirada ou acesso não autorizado.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ob_clean();
        echo json_encode(['erro' => 'Método não permitido.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $id_medico = intval($_SESSION['id_usuario']);
    $id_consulta = intval($_POST['id_consulta'] ?? 0);
    $texto = trim($_POST['texto'] ?? '');

    if ($id_consulta <= 0 || $texto === '') {
        ob_clean();
        echo json_encode(['erro' => 'Informe o texto do adendo.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $conn->prepare("SELECT status FROM consulta WHERE id_consulta = ? AND id_medico = ?");
    $stmt->bind_param("ii", $id_consulta, $id_medico);
    $stmt->execute();
    $consulta = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$consulta) {
        ob_clean();
        echo json_encode(['erro' => 'Consulta não encontrada.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($consulta['status'] !== 'finalizada') {
        ob_clean();
        echo json_encode(['erro' => 'Adendos só podem ser feitos em consultas finalizadas.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    @$conn->query("CREATE TABLE IF NOT EXISTS prontuario_adendo (
        id_adendo INT AUTO_INCREMENT PRIMARY KEY,
        id_consulta INT NOT NULL,
        id_medico INT NOT NULL,
        texto TEXT NOT NULL,
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX (id_consulta)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $stmt = $conn->prepare("INSERT INTO prontuario_adendo (id_consulta, id_medico, texto) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $id_consulta, $id_medico, $texto);
    $stmt->execute();
    $stmt->close();

    registrarLog($conn, 'Adendo ao prontuário da consulta #' . $id_consulta);

    ob_clean();
    echo json_encode(['sucesso' => true, 'mensagem' => 'Adendo registrado.'], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    ob_clean();
    echo json_encode(['erro' => 'Erro ao salvar adendo: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

