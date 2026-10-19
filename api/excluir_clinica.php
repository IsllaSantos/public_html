<?php

require_once('../config/auth.php');
require_once('../config/database.php');

verificarPerfil('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../admin.php");
    exit;
}


/* RECEBE O ID */

$id_clinica = intval($_POST['id_clinica'] ?? 0);

if ($id_clinica <= 0) {
    die("Clínica inválida.");
}


/* VERIFICA SE HÁ USUÁRIOS VINCULADOS */

$sql = "
SELECT COUNT(*) AS total
FROM usuarios
WHERE id_clinica = ?
";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "i",
    $id_clinica
);

$stmt->execute();

$resultado = $stmt->get_result();
$row = $resultado->fetch_assoc();

if ($row['total'] > 0) {

    $_SESSION['mensagem_sucesso'] =
        "Não é possível excluir: existem " .
        $row['total'] .
        " usuário(s) vinculado(s) a esta clínica.";

    header("Location: ../admin.php?aba=clinicas");
    exit;
}


/* EXCLUI A CLÍNICA */

$sql = "
DELETE FROM clinica
WHERE id_clinica = ?
";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "i",
    $id_clinica
);

if ($stmt->execute()) {

    $_SESSION['mensagem_sucesso'] = "Clínica excluída com sucesso!";

    header("Location: ../admin.php?aba=clinicas");
    exit;

} else {

    die(
        "Erro ao excluir clínica: " .
        $conn->error
    );
}
