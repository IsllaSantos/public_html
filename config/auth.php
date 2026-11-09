<?php

if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/sessao.php';
}

require_once __DIR__ . '/seguranca.php';
// Qualquer página/API protegida rejeita POST vindo de outra origem (CSRF)
exigirOrigemValida();

function ehRequisicaoApi()
{
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    $uri = $_SERVER['REQUEST_URI'] ?? '';

    return (
        stripos($accept, 'application/json') !== false ||
        strtolower($requestedWith) === 'xmlhttprequest' ||
        strpos($uri, '/api/') !== false
    );
}

function redirecionarLogin()
{
    if (ehRequisicaoApi()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode([
            'sucesso' => false,
            'erro' => 'Sessão expirada ou não autenticada. Faça login novamente.',
            'auth' => false
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $caminhoLogin = file_exists(__DIR__ . '/../login.php') ? 'login.php' : '../login.php';
    if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
        $caminhoLogin = '../login.php';
    }

    header("Location: " . $caminhoLogin);
    exit;
}

function verificarLogin()
{
    if (!isset($_SESSION['id_usuario'])) {
        redirecionarLogin();
    }

    // Expira a sessão após 2 horas sem atividade
    $agora = time();
    if (isset($_SESSION['ultima_atividade']) && ($agora - $_SESSION['ultima_atividade']) > 7200) {
        $_SESSION = [];
        session_destroy();
        redirecionarLogin();
    }
    $_SESSION['ultima_atividade'] = $agora;

    // Exige o aceite vigente da Política de Privacidade (LGPD)
    if (empty($_SESSION['lgpd_ok'])) {
        if (ehRequisicaoApi()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(403);
            echo json_encode([
                'sucesso' => false,
                'erro' => 'É necessário aceitar a Política de Privacidade para continuar.',
                'lgpd' => false
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        header('Location: ' . caminhoRaiz() . 'aceite_lgpd.php');
        exit;
    }
}

function verificarPerfil($perfilPermitido)
{
    verificarLogin();

    if (!isset($_SESSION['perfil']) ||
        $_SESSION['perfil'] !== $perfilPermitido
    ) {
        if (!ehRequisicaoApi()) {
            session_unset();
            session_destroy();
        }

        redirecionarLogin();
    }
}