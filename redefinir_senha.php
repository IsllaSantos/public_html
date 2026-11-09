<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/seguranca.php';
require_once __DIR__ . '/config/logs.php';

garantirEsquemaSeguranca($conn);

$token = $_GET['token'] ?? ($_POST['token'] ?? '');
$token = is_string($token) ? $token : '';
$valido = false;
$erro = '';
$sucesso = false;
$registro = null;

try {
    if (preg_match('/^[a-f0-9]{64}$/', $token)) {
        $hash = hash('sha256', $token);
        $stmt = @$conn->prepare("SELECT id, id_usuario FROM recuperacao_senha WHERE token_hash = ? AND usado = 0 AND expira_em > NOW() LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $hash);
            $stmt->execute();
            $res = $stmt->get_result();
            $registro = $res ? $res->fetch_assoc() : null;
            $valido = (bool) $registro;
        }
    }

    if ($valido && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        exigirOrigemValida();

        $nova = $_POST['senha'] ?? '';
        $conf = $_POST['confirmar'] ?? '';

        if (!csrfValido()) {
            $erro = 'Sessão expirada. Recarregue a página e tente novamente.';
        } elseif ($nova !== $conf) {
            $erro = 'As senhas não coincidem.';
        } elseif (!senhaForte($nova, $msg)) {
            $erro = $msg;
        } else {
            $novoHash = password_hash($nova, PASSWORD_DEFAULT);
            $idUsuario = (int) $registro['id_usuario'];
            $idReg = (int) $registro['id'];

            $up = @$conn->prepare("UPDATE usuarios SET senha = ? WHERE id_usuario = ?");
            if ($up) {
                $up->bind_param("si", $novoHash, $idUsuario);

                if ($up->execute()) {
                    try {
                        @$conn->query("UPDATE recuperacao_senha SET usado = 1 WHERE id_usuario = $idUsuario");
                    } catch (\Throwable $e) {}
                    try {
                        $sel = @$conn->query("SELECT email FROM usuarios WHERE id_usuario = $idUsuario");
                        if ($sel && ($u = $sel->fetch_assoc())) {
                            limparTentativas($conn, 'login', strtolower($u['email']));
                        }
                    } catch (\Throwable $e) {}
                    registrarLog($conn, "Senha redefinida por recuperação (usuário #$idUsuario)");
                    $sucesso = true;
                    $valido = false;
                } else {
                    $erro = 'Não foi possível atualizar a senha. Tente novamente.';
                }
            } else {
                $erro = 'Não foi possível preparar a atualização de senha.';
            }
        }
    }
} catch (\Throwable $e) {
    error_log("Erro em redefinir_senha.php: " . $e->getMessage());
    $erro = 'Erro interno ao processar redefinição: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="referrer" content="no-referrer">
    <title>NyesClinic - Redefinir Senha</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>">
</head>
<body>
<section class="login-screen active">
    <div class="login-box">
        <div style="text-align:center;margin-bottom:28px;">
            <h1 class="logo login-logo" style="justify-content:center;">Nyes<span>Clinic</span></h1>
        </div>

        <h2>Redefinir senha</h2>

        <?php if ($sucesso): ?>
            <div style="background:var(--success-light);border:1px solid rgba(16,185,129,0.3);color:var(--success);padding:12px 16px;border-radius:var(--radius-md);margin-bottom:18px;font-size:0.9rem;">
                Senha alterada com sucesso! Você já pode entrar com a nova senha.
            </div>
            <button class="btn btn-primary" style="width:100%;padding:12px;" onclick="window.location.href='login.php'">Ir para o login →</button>

        <?php elseif (!$valido): ?>
            <div style="background:var(--danger-light);border:1px solid rgba(239,68,68,0.3);color:var(--danger);padding:12px 16px;border-radius:var(--radius-md);margin-bottom:18px;font-size:0.9rem;">
                Este link é inválido ou expirou. Solicite uma nova redefinição.
            </div>
            <button class="btn btn-primary" style="width:100%;padding:12px;" onclick="window.location.href='esqueci_senha.php'">Solicitar novo link</button>

        <?php else: ?>
            <?php if ($erro): ?>
                <div style="background:var(--danger-light);border:1px solid rgba(239,68,68,0.3);color:var(--danger);padding:12px 16px;border-radius:var(--radius-md);margin-bottom:18px;font-size:0.88rem;">
                    <?= htmlspecialchars($erro) ?>
                </div>
            <?php endif; ?>
            <form method="POST" action="redefinir_senha.php">
                <?= csrfCampo() ?>
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <div class="input-group">
                    <label for="senha">Nova senha</label>
                    <input type="password" id="senha" name="senha" minlength="8" required autocomplete="new-password" autofocus>
                    <small style="color:var(--text-muted);">Mínimo de 8 caracteres, com letras e números.</small>
                </div>
                <div class="input-group">
                    <label for="confirmar">Confirmar nova senha</label>
                    <input type="password" id="confirmar" name="confirmar" minlength="8" required autocomplete="new-password">
                </div>
                <button class="btn btn-primary" type="submit" style="width:100%;margin-top:8px;padding:12px;">Salvar nova senha</button>
            </form>
        <?php endif; ?>
    </div>
</section>
</body>
</html>

