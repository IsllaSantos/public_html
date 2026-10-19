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
    die("ID inválido.");
}

$stmt = $conn->prepare("DELETE FROM especialidades WHERE id_especialidade = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    $_SESSION['mensagem_sucesso'] = "Especialidade excluída!";
    header("Location: ../admin.php?aba=especialidades");
    exit;
} else {
    die("Erro ao excluir: " . $conn->error);
}
