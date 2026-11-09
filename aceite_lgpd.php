<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/seguranca.php';
require_once __DIR__ . '/config/logs.php';

if (!isset($_SESSION['id_usuario'])) {
    header('Location: login.php');
    exit;
}

garantirEsquemaSeguranca($conn);

$idUsuario = (int) $_SESSION['id_usuario'];

function destinoPorPerfil($perfil)
{
    switch ($perfil) {
        case 'admin':      return 'admin.php';
        case 'secretaria': return 'secretaria.php';
        case 'medico':     return 'medico.php';
        case 'paciente':   return 'paciente.php';
        default:           return 'login.php';
    }
}

// Já aceitou a versão vigente? Libera e redireciona.
$stmt = $conn->prepare("SELECT aceite_lgpd_versao FROM usuarios WHERE id_usuario = ? AND status = 'ativo'");
$stmt->bind_param("i", $idUsuario);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row) {
    header('Location: logout.php');
    exit;
}

if (($row['aceite_lgpd_versao'] ?? '') === LGPD_VERSAO) {
    $_SESSION['lgpd_ok'] = true;
    header('Location: ' . destinoPorPerfil($_SESSION['perfil'] ?? ''));
    exit;
}

$erro = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    exigirOrigemValida();

    if (!csrfValido()) {
        $erro = 'Sessão expirada. Recarregue a página e tente novamente.';
    } elseif (empty($_POST['aceito'])) {
        $erro = 'Para continuar, é necessário aceitar a Política de Privacidade.';
    } else {
        $versao = LGPD_VERSAO;
        $up = $conn->prepare("UPDATE usuarios SET aceite_lgpd_em = NOW(), aceite_lgpd_versao = ? WHERE id_usuario = ?");
        $up->bind_param("si", $versao, $idUsuario);

        if ($up->execute()) {
            $_SESSION['lgpd_ok'] = true;
            registrarLog($conn, "Aceite da Política de Privacidade (versão $versao)");
            header('Location: ' . destinoPorPerfil($_SESSION['perfil'] ?? ''));
            exit;
        }
        $erro = 'Não foi possível registrar o aceite. Tente novamente.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NyesClinic - Privacidade e Consentimento</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>">
</head>
<body>
<section class="login-screen active">
    <div class="login-box" style="max-width:520px;">
        <div style="text-align:center;margin-bottom:24px;">
            <h1 class="logo login-logo" style="justify-content:center;">Nyes<span>Clinic</span></h1>
        </div>

        <h2>Privacidade e consentimento</h2>
        <p style="color:var(--text-muted);font-size:0.92rem;line-height:1.6;margin-bottom:14px;">
            Olá, <strong><?= htmlspecialchars($_SESSION['nome'] ?? '') ?></strong>. Para usar o sistema, precisamos do seu aceite.
            O NyesClinic trata dados cadastrais e dados de saúde (prontuário, receitas e exames) para viabilizar seu atendimento,
            conforme a Lei Geral de Proteção de Dados (LGPD). O acesso é restrito por perfil e você pode exercer seus direitos
            junto à sua clínica.
        </p>
        <p style="font-size:0.92rem;margin-bottom:16px;">
            <a href="privacidade.php" target="_blank" rel="noopener" style="color:var(--gold-dark);font-weight:600;">Ler a Política de Privacidade completa ↗</a>
        </p>

        <?php if ($erro): ?>
            <div style="background:var(--danger-light);border:1px solid rgba(239,68,68,0.3);color:var(--danger);padding:12px 16px;border-radius:var(--radius-md);margin-bottom:16px;font-size:0.88rem;">
                <?= htmlspecialchars($erro) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="aceite_lgpd.php">
            <?= csrfCampo() ?>
            <label style="display:flex;gap:10px;align-items:flex-start;font-size:0.92rem;line-height:1.5;margin-bottom:16px;cursor:pointer;">
                <input type="checkbox" name="aceito" value="1" required style="margin-top:4px;">
                <span>Li e concordo com a Política de Privacidade e com o tratamento dos meus dados nos termos descritos.</span>
            </label>
            <button class="btn btn-primary" type="submit" style="width:100%;padding:12px;">Aceitar e continuar →</button>
        </form>

        <button class="btn btn-secondary" style="width:100%;margin-top:12px;padding:10px;" onclick="window.location.href='logout.php'">Não aceito (sair)</button>
    </div>
</section>
</body>
</html>

