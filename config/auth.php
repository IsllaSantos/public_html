<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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