<?php

require_once('../config/auth.php');
require_once('../config/database.php');

verificarPerfil('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../admin.php?aba=usuarios");
    exit;
}


/* RECEBE O ID */

$id_usuario = intval($_POST['id_usuario'] ?? 0);

if ($id_usuario <= 0) {
    die("Usuário inválido.");
}


/* IMPEDE EXCLUSÃO DO PRÓPRIO ADMIN LOGADO */

if ($id_usuario === intval($_SESSION['id_usuario'])) {

    $_SESSION['mensagem_sucesso'] =
        "Você não pode excluir sua própria conta.";

    header("Location: ../admin.php?aba=usuarios");
    exit;
}


/* VERIFICA SE O USUÁRIO EXISTE */

$sql = "
SELECT id_usuario, nome, perfil
FROM usuarios
WHERE id_usuario = ?
";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "i",
    $id_usuario
);

$stmt->execute();

$resultado = $stmt->get_result();

if ($resultado->num_rows === 0) {
    die("Usuário não encontrado.");
}

$usuario = $resultado->fetch_assoc();


/* VERIFICA SE HÁ CONSULTAS VINCULADAS (médico ou paciente) */

if ($usuario['perfil'] === 'medico') {

    $sql = "
    SELECT COUNT(*) AS total
    FROM consulta
    WHERE id_medico = ?
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if ($row['total'] > 0) {

        $_SESSION['mensagem_sucesso'] =
            "Não é possível excluir: este médico possui " .
            $row['total'] .
            " consulta(s) vinculada(s).";

        header("Location: ../admin.php?aba=usuarios");
        exit;
    }
}

if ($usuario['perfil'] === 'paciente') {

    $sql = "
    SELECT COUNT(*) AS total
    FROM consulta
    WHERE id_paciente = ?
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if ($row['total'] > 0) {

        $_SESSION['mensagem_sucesso'] =
            "Não é possível excluir: este paciente possui " .
            $row['total'] .
            " consulta(s) vinculada(s).";

        header("Location: ../admin.php?aba=usuarios");
        exit;
    }
}


/* EXCLUI O USUÁRIO */

$sql = "
DELETE FROM usuarios
WHERE id_usuario = ?
";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "i",
    $id_usuario
);

if ($stmt->execute()) {

    $_SESSION['mensagem_sucesso'] =
        "Usuário \"" . $usuario['nome'] . "\" excluído com sucesso!";

    header("Location: ../admin.php?aba=usuarios");
    exit;

} else {

    die(
        "Erro ao excluir usuário: " .
        $conn->error
    );
}
