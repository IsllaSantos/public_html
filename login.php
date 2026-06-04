<?php
session_start();
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>TechNyes - Login</title>

<link rel="stylesheet" href="assets/css/style.css">

</head>

<body>

<section id="loginSection" class="login-screen active">

    <div class="login-box">

        <div style="text-align:center;margin-bottom:25px;">

            <h1 class="logo login-logo">
                Tech<span>Nyes</span>
            </h1>

            <p style="margin-top:10px;color:#777;">
                Sistema Inteligente para Clínicas
            </p>

        </div>

        <h2>
            Acessar Sistema
        </h2>


        <form
    action="api/login.php"
    method="POST"
    autocomplete="off"
    onsubmit="return validarLogin()">

            <div class="input-group">

                <label for="email">
                    E-mail
                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="email@clinica.com"
                    required autocomplete="email">

            </div>

            <div class="input-group">

                <label for="senha">
                    Senha
                </label>

                <input
                    type="password"
                    id="senha"
                    name="senha"
                    placeholder="••••••••"
                    required autocomplete="new-password">

            </div>

            <?php

if (isset($_SESSION['erro_login'])) {
    echo "<p style='color:red; margin-bottom:10px;'>"
        . $_SESSION['erro_login'] .
    "</p>";

    unset($_SESSION['erro_login']);
}
?>

            <button
                class="btn btn-primary"
                type="submit">

                Entrar

            </button>

        </form>

        <button
            class="btn btn-secondary"
            style="width:100%;margin-top:15px;"
            onclick="window.location.href='index.php'">

            Voltar ao Site

        </button>

    </div>

</section>

<script src="assets/js/login.js"></script>

</body>
</html>