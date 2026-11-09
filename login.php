<?php
require_once __DIR__ . '/config/seguranca.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NyesClinic - Acesso ao Sistema</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>">
</head>
<body>

<section id="loginSection" class="login-screen active">

    <div class="login-box">

        <div style="text-align:center;margin-bottom:28px;">
            <h1 class="logo login-logo" style="justify-content:center;">
                Nyes<span>Clinic</span>
            </h1>
            <p style="margin-top:8px;color:var(--text-muted);font-size:0.92rem;">
                Sistema Inteligente para Clínicas e Consultórios
            </p>
        </div>

        <h2>Acessar Sistema</h2>

        <?php if (isset($_SESSION['erro_login'])): ?>
            <div style="background:var(--danger-light);border:1px solid rgba(239,68,68,0.3);color:var(--danger);padding:12px 16px;border-radius:var(--radius-md);margin-bottom:18px;font-size:0.88rem;display:flex;align-items:center;gap:10px;">
                <span style="font-size:1.1rem;font-weight:bold;">⚠</span>
                <span><?= htmlspecialchars($_SESSION['erro_login']) ?></span>
            </div>
            <?php unset($_SESSION['erro_login']); ?>
        <?php endif; ?>

        <form action="api/login.php" method="POST" autocomplete="on" onsubmit="return validarLogin()">
            <?= csrfCampo() ?>

            <div class="input-group">
                <label for="email">E-mail Profissional ou Paciente</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="seuemail@clinica.com"
                    required
                    autocomplete="email"
                    autofocus>
            </div>

            <div class="input-group">
                <label for="senha">Senha</label>
                <div class="input-password-wrapper">
                    <input
                        type="password"
                        id="senha"
                        name="senha"
                        placeholder="••••••••"
                        required
                        autocomplete="current-password">
                    <button type="button" class="btn-toggle-pass" id="btnToggleSenha" onclick="toggleVisibilidadeSenha()" title="Mostrar/ocultar senha">
                        👁
                    </button>
                </div>
            </div>

            <button class="btn btn-primary" type="submit" style="width:100%;margin-top:8px;padding:12px;">
                Entrar no Sistema →
            </button>

            <p style="text-align:center;margin-top:14px;font-size:0.88rem;">
                <a href="esqueci_senha.php" style="color:var(--gold-dark);font-weight:600;">Esqueci minha senha</a>
            </p>

        </form>

        <button
            class="btn btn-secondary"
            style="width:100%;margin-top:14px;padding:10px;"
            onclick="window.location.href='index.php'">
            ← Voltar à Página Inicial
        </button>

        <p style="text-align:center;margin-top:16px;font-size:0.8rem;color:var(--text-muted);">
            Ao acessar, você concorda com a <a href="privacidade.php" style="color:var(--gold-dark);">Política de Privacidade</a>.
        </p>

    </div>

</section>

<script src="assets/js/main.js"></script>
<script src="assets/js/login.js"></script>
</body>
</html>