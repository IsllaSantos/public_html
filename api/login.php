<?php

require_once('../config/database.php');

session_start();

$email = trim($_POST['email'] ?? '');
$senha = trim($_POST['senha'] ?? '');

if (
    empty($email) ||
    empty($senha)
) {

    header("Location: ../login.php");
    exit;
}

$sql = "
SELECT *
FROM usuarios
WHERE email = ?
AND status = 'ativo'
";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "s",
    $email
);

$stmt->execute();

$resultado =
$stmt->get_result();

if ($resultado->num_rows == 0) {
    $_SESSION['erro_login'] = "E-mail ou senha incorretos.";
    header("Location: ../login.php");
    exit;
}

$usuario = $resultado->fetch_assoc();

if (
    password_verify(
        $senha,
        $usuario['senha']
    )
) {
    $_SESSION['id_usuario'] = $usuario['id_usuario'];
    $_SESSION['nome']       = $usuario['nome'];
    $_SESSION['email']      = $usuario['email'];
    $_SESSION['perfil']     = $usuario['perfil'];
    $_SESSION['id_clinica'] = $usuario['id_clinica'];
} else {
    $_SESSION['erro_login'] = "E-mail ou senha incorretos.";
    header("Location: ../login.php");
    exit;
}


/* REDIRECIONA */

switch ($usuario['perfil']) {

    case 'admin':

        header(
            "Location: ../admin.php"
        );
        break;

    case 'secretaria':

        header(
            "Location: ../secretaria.php"
        );
        break;

    case 'medico':

        header(
            "Location: ../medico.php"
        );
        break;

    case 'paciente':

        header(
            "Location: ../paciente.php"
        );
        break;

    default:

        session_destroy();

        header(
            "Location: ../login.php"
        );
        break;
}

exit;