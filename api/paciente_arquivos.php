<?php

require_once('../config/auth.php');
require_once('../config/database.php');
require_once('../config/arquivos.php');

verificarPerfil('paciente');

header('Content-Type: application/json; charset=utf-8');

$id_paciente = intval($_SESSION['id_usuario']);
$acao = $_GET['acao'] ?? '';

const ARQ_MAX_BYTES = 10 * 1024 * 1024;
const ARQ_EXT_PERMITIDAS = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx'];

$conn->query("
    CREATE TABLE IF NOT EXISTS arquivo_paciente (
        id_arquivo    INT AUTO_INCREMENT PRIMARY KEY,
        id_paciente   INT NOT NULL,
        id_consulta   INT NULL,
        titulo        VARCHAR(150) NOT NULL,
        nome_original VARCHAR(255) NOT NULL,
        nome_armazenado VARCHAR(100) NOT NULL,
        mime          VARCHAR(100) NULL,
        tamanho       INT NOT NULL DEFAULT 0,
        criado_em     DATETIME DEFAULT CURRENT_TIMESTAMP,
        atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_pac (id_paciente),
        INDEX idx_cons (id_consulta)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

/* Valida que a consulta pertence ao paciente. Retorna id ou null. */
function consultaDoPaciente($conn, $id_consulta, $id_paciente) {
    if ($id_consulta <= 0) return null;
    $st = $conn->prepare("SELECT id_consulta FROM consulta WHERE id_consulta = ? AND id_paciente = ?");
    $st->bind_param("ii", $id_consulta, $id_paciente);
    $st->execute();
    return $st->get_result()->num_rows > 0 ? $id_consulta : false;
}

function erro($msg) {
    echo json_encode(['erro' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ===== CONSULTAS PARA VÍNCULO ===== */
if ($acao === 'consultas') {
    $st = $conn->prepare("
        SELECT c.id_consulta, c.data_consulta, c.hora_consulta, c.status, m.nome AS nome_medico
        FROM consulta c
        LEFT JOIN usuarios m ON m.id_usuario = c.id_medico
        WHERE c.id_paciente = ?
        ORDER BY c.data_consulta DESC, c.hora_consulta DESC
        LIMIT 100
    ");
    if (!$st) erro('Erro ao buscar consultas.');
    $st->bind_param("i", $id_paciente);
    $st->execute();
    $res = $st->get_result();
    $out = [];
    while ($r = $res->fetch_assoc()) {
        if (($r['status'] ?? '') !== 'cancelada') $out[] = $r;
    }
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

/* ===== LISTAR ===== */
if ($acao === 'listar') {
    $st = $conn->prepare("
        SELECT a.id_arquivo, a.titulo, a.nome_original, a.mime, a.tamanho, a.criado_em,
               a.id_consulta, c.data_consulta, c.hora_consulta, m.nome AS nome_medico
        FROM arquivo_paciente a
        LEFT JOIN consulta c ON c.id_consulta = a.id_consulta
        LEFT JOIN usuarios m ON m.id_usuario = c.id_medico
        WHERE a.id_paciente = ?
        ORDER BY a.criado_em DESC
    ");
    $st->bind_param("i", $id_paciente);
    $st->execute();
    $res = $st->get_result();
    $out = [];
    while ($r = $res->fetch_assoc()) $out[] = $r;
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

/* ===== ENVIAR ===== */
if ($acao === 'enviar' && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $titulo = trim($_POST['titulo'] ?? '');
    $id_consulta = intval($_POST['id_consulta'] ?? 0);

    if ($titulo === '') erro('Informe o título do arquivo.');
    if (mb_strlen($titulo) > 150) erro('Título muito longo (máx. 150 caracteres).');

    if (!isset($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
        erro('Selecione um arquivo válido (máx. 10 MB).');
    }
    $f = $_FILES['arquivo'];
    if ($f['size'] > ARQ_MAX_BYTES) erro('Arquivo excede 10 MB.');

    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ARQ_EXT_PERMITIDAS, true)) {
        erro('Tipo não permitido. Use: ' . implode(', ', ARQ_EXT_PERMITIDAS) . '.');
    }

    $vinculo = null;
    if ($id_consulta > 0) {
        $vinculo = consultaDoPaciente($conn, $id_consulta, $id_paciente);
        if ($vinculo === false) erro('Consulta inválida.');
    }

    $dir = pastaUploadPacientes();
    if (!$dir) erro('Pasta de armazenamento indisponível no servidor.');

    $nomeArmazenado = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $nomeArmazenado)) {
        erro('Falha ao salvar o arquivo.');
    }

    $mime = function_exists('mime_content_type') ? mime_content_type($dir . '/' . $nomeArmazenado) : ($f['type'] ?? null);
    $nomeOriginal = mb_substr(basename($f['name']), 0, 255);
    $tamanho = intval($f['size']);

    $st = $conn->prepare("
        INSERT INTO arquivo_paciente
            (id_paciente, id_consulta, titulo, nome_original, nome_armazenado, mime, tamanho)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $st->bind_param("iissssi", $id_paciente, $vinculo, $titulo, $nomeOriginal, $nomeArmazenado, $mime, $tamanho);
    if (!$st->execute()) {
        @unlink($dir . '/' . $nomeArmazenado);
        erro('Erro ao registrar arquivo.');
    }
    echo json_encode(['sucesso' => true, 'mensagem' => 'Arquivo enviado com sucesso!'], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ===== EDITAR (título e vínculo/compartilhamento) ===== */
if ($acao === 'editar' && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $id_arquivo = intval($_POST['id_arquivo'] ?? 0);
    $titulo = trim($_POST['titulo'] ?? '');
    $id_consulta = intval($_POST['id_consulta'] ?? 0);

    if ($id_arquivo <= 0 || $titulo === '') erro('Dados inválidos.');
    if (mb_strlen($titulo) > 150) erro('Título muito longo (máx. 150 caracteres).');

    $vinculo = null;
    if ($id_consulta > 0) {
        $vinculo = consultaDoPaciente($conn, $id_consulta, $id_paciente);
        if ($vinculo === false) erro('Consulta inválida.');
    }

    $st = $conn->prepare("UPDATE arquivo_paciente SET titulo = ?, id_consulta = ? WHERE id_arquivo = ? AND id_paciente = ?");
    $st->bind_param("siii", $titulo, $vinculo, $id_arquivo, $id_paciente);
    $st->execute();
    echo json_encode(['sucesso' => true, 'mensagem' => 'Arquivo atualizado.'], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ===== EXCLUIR ===== */
if ($acao === 'excluir' && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $id_arquivo = intval($_POST['id_arquivo'] ?? 0);

    $st = $conn->prepare("SELECT nome_armazenado FROM arquivo_paciente WHERE id_arquivo = ? AND id_paciente = ?");
    $st->bind_param("ii", $id_arquivo, $id_paciente);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    if (!$row) erro('Arquivo não encontrado.');

    $st = $conn->prepare("DELETE FROM arquivo_paciente WHERE id_arquivo = ? AND id_paciente = ?");
    $st->bind_param("ii", $id_arquivo, $id_paciente);
    $st->execute();
    @unlink(caminhoArquivoPaciente($row['nome_armazenado']) ?: '');

    echo json_encode(['sucesso' => true, 'mensagem' => 'Arquivo excluído.'], JSON_UNESCAPED_UNICODE);
    exit;
}

erro('Ação inválida.');
