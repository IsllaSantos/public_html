<?php

require_once('../config/auth.php');
require_once('../config/database.php');
require_once('../config/arquivos.php');

if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../config/sessao.php';
}

if (!isset($_SESSION['id_usuario'], $_SESSION['perfil'])) {
    http_response_code(401);
    exit('Não autorizado.');
}

$id_arquivo = intval($_GET['id'] ?? 0);
$uid = intval($_SESSION['id_usuario']);
$perfil = $_SESSION['perfil'];

$st = $conn->prepare("SELECT * FROM arquivo_paciente WHERE id_arquivo = ?");
$st->bind_param("i", $id_arquivo);
$st->execute();
$arq = $st->get_result()->fetch_assoc();

$permitido = false;
if ($arq) {
    $id_paciente_arq = intval($arq['id_paciente']);
    $id_clinica_sessao = intval($_SESSION['id_clinica'] ?? 0);

    if ($perfil === 'admin') {
        $permitido = true;
    } elseif ($perfil === 'paciente' && $id_paciente_arq === $uid) {
        $permitido = true;
    } elseif ($perfil === 'medico') {
        if (!empty($arq['id_consulta'])) {
            $st = $conn->prepare("SELECT 1 FROM consulta WHERE id_consulta = ? AND id_medico = ?");
            $st->bind_param("ii", $arq['id_consulta'], $uid);
            $st->execute();
            if ($st->get_result()->num_rows > 0) {
                $permitido = true;
            }
        }
        if (!$permitido) {
            // Permite se o paciente tem/teve consulta com este médico
            $st = $conn->prepare("SELECT 1 FROM consulta WHERE id_paciente = ? AND id_medico = ? LIMIT 1");
            $st->bind_param("ii", $id_paciente_arq, $uid);
            $st->execute();
            if ($st->get_result()->num_rows > 0) {
                $permitido = true;
            }
        }
        if (!$permitido && $id_clinica_sessao > 0) {
            // Permite se o paciente pertence à mesma clínica do médico
            $st = $conn->prepare("SELECT 1 FROM usuarios WHERE id_usuario = ? AND id_clinica = ?");
            $st->bind_param("ii", $id_paciente_arq, $id_clinica_sessao);
            $st->execute();
            if ($st->get_result()->num_rows > 0) {
                $permitido = true;
            }
        }
    } elseif ($perfil === 'secretaria' && $id_clinica_sessao > 0) {
        $st = $conn->prepare("SELECT 1 FROM usuarios WHERE id_usuario = ? AND id_clinica = ?");
        $st->bind_param("ii", $id_paciente_arq, $id_clinica_sessao);
        $st->execute();
        if ($st->get_result()->num_rows > 0) {
            $permitido = true;
        }
    }
}

if (!$permitido) {
    http_response_code(403);
    exit('Acesso negado.');
}

$caminho = caminhoArquivoPaciente($arq['nome_armazenado']);
if (!$caminho) {
    http_response_code(404);
    exit('Arquivo não encontrado no servidor. Peça ao paciente para reenviá-lo.');
}

$inline = in_array(strtolower(pathinfo($caminho, PATHINFO_EXTENSION)), ['pdf', 'jpg', 'jpeg', 'png', 'webp'], true);
$nome = str_replace(['"', "\r", "\n"], '', $arq['nome_original']);

header('Content-Type: ' . ($arq['mime'] ?: 'application/octet-stream'));
header('Content-Length: ' . filesize($caminho));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $nome . '"');
header('X-Content-Type-Options: nosniff');
readfile($caminho);
exit;
