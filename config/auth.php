<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function verificarLogin()
{
    if (!isset($_SESSION['id_usuario'])) {
        header("Location: login.php");
        exit;
    }
}

function verificarPerfil($perfilPermitido)
{
    verificarLogin();

    if (!isset($_SESSION['perfil']) ||
        $_SESSION['perfil'] !== $perfilPermitido
    ) {
        session_unset();
        session_destroy();

        header("Location: login.php");
        exit;
    }
}