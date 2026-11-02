<?php

require_once('../config/auth.php');
require_once('../config/database.php');

verificarPerfil('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../admin.php?aba=especialidades");
    exit;
}

$id = intval($_POST['id_especialidade'] ?? 0);

if ($id <= 0) {
    $_SESSION['mensagem_sucesso'] = "ID de especialidade inválido.";
    header("Location: ../admin.php?aba=especialidades");
    exit;
}

try {
    /* Busca nome da especialidade */
    $stmtEsp = $conn->prepare("SELECT nome FROM especialidades WHERE id_especialidade = ?");
    $stmtEsp->bind_param("i", $id);
    $stmtEsp->execute();
    $resEsp = $stmtEsp->get_result()->fetch_assoc();
    $stmtEsp->close();

    if (!$resEsp) {
        $_SESSION['mensagem_sucesso'] = "Especialidade não encontrada.";
        header("Location: ../admin.php?aba=especialidades");
        exit;
    }

    $nomeEsp = $resEsp['nome'];

    /* Verifica se há médicos vinculados */
    $stmtCheck = $conn->prepare("SELECT COUNT(*) AS total FROM usuarios WHERE perfil = 'medico' AND especialidade = ?");
    $stmtCheck->bind_param("s", $nomeEsp);
    $stmtCheck->execute();
    $resCheck = $stmtCheck->get_result()->fetch_assoc();
    $stmtCheck->close();

    if ($resCheck && $resCheck['total'] > 0) {
        $_SESSION['mensagem_sucesso'] = "Não é possível excluir \"" . $nomeEsp . "\": existem " . $resCheck['total'] . " médico(s) vinculados a ela.";
        header("Location: ../admin.php?aba=especialidades");
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM especialidades WHERE id_especialidade = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    $_SESSION['mensagem_sucesso'] = "Especialidade \"" . $nomeEsp . "\" excluída com sucesso!";
} catch (Throwable $e) {
    $_SESSION['mensagem_sucesso'] = "Erro ao excluir especialidade: " . $e->getMessage();
}

header("Location: ../admin.php?aba=especialidades");
exit;
