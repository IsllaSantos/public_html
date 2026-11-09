<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/sessao.php';
require_once __DIR__ . '/../config/seguranca.php';

exigirOrigemValida();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header("Location: ../login.php");
    exit;
}

garantirEsquemaSeguranca($conn);

$email = trim($_POST['email'] ?? '');
$senha = trim($_POST['senha'] ?? '');

if (!csrfValido()) {
    $_SESSION['erro_login'] = "Sessão expirada. Tente novamente.";
    header("Location: ../login.php");
    exit;
}

if (empty($email) || empty($senha)) {
    header("Location: ../login.php");
    exit;
}

/* LIMITE DE TENTATIVAS (5 por e-mail ou 30 por IP em 15 min) */
$chaveEmail = strtolower($email);
$chaveIp    = ipCliente();

if (
    contarTentativas($conn, 'login', $chaveEmail, 15) >= 5 ||
    contarTentativas($conn, 'login_ip', $chaveIp, 15) >= 30
) {
    $_SESSION['erro_login'] = "Muitas tentativas de acesso. Aguarde 15 minutos e tente novamente.";
    header("Location: ../login.php");
    exit;
}

$stmt = $conn->prepare("SELECT * FROM usuarios WHERE email = ? AND status = 'ativo'");
$stmt->bind_param("s", $email);
$stmt->execute();
$resultado = $stmt->get_result();

$usuario = $resultado->num_rows > 0 ? $resultado->fetch_assoc() : null;

if (!$usuario || !password_verify($senha, $usuario['senha'])) {
    registrarTentativa($conn, 'login', $chaveEmail);
    registrarTentativa($conn, 'login_ip', $chaveIp);
    $_SESSION['erro_login'] = "E-mail ou senha incorretos.";
    header("Location: ../login.php");
    exit;
}

/* LOGIN OK: limpa tentativas e renova o ID da sessão (evita session fixation) */
limparTentativas($conn, 'login', $chaveEmail);
session_regenerate_id(true);
unset($_SESSION['csrf_token']);

$_SESSION['id_usuario']       = $usuario['id_usuario'];
$_SESSION['nome']             = $usuario['nome'];
$_SESSION['email']            = $usuario['email'];
$_SESSION['perfil']           = $usuario['perfil'];
$_SESSION['id_clinica']       = $usuario['id_clinica'];
$_SESSION['ultima_atividade'] = time();
$_SESSION['lgpd_ok']          = (($usuario['aceite_lgpd_versao'] ?? '') === LGPD_VERSAO);

/* REDIRECIONA */

if (!$_SESSION['lgpd_ok']) {
    header("Location: ../aceite_lgpd.php");
    exit;
}

switch ($usuario['perfil']) {
    case 'admin':
        header("Location: ../admin.php");
        break;
    case 'secretaria':
        header("Location: ../secretaria.php");
        break;
    case 'medico':
        header("Location: ../medico.php");
        break;
    case 'paciente':
        header("Location: ../paciente.php");
        break;
    default:
        session_destroy();
        header("Location: ../login.php");
        break;
}

exit;
