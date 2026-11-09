<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/seguranca.php';
require_once __DIR__ . '/config/email.php';

garantirEsquemaSeguranca($conn);

$enviado = false;
$erro = '';
$linkFallback = '';
$motivoFallback = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    try {
        exigirOrigemValida();

        $email = trim($_POST['email'] ?? '');

        if (!csrfValido()) {
            $erro = 'Sessão expirada. Recarregue a página e tente novamente.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erro = 'Informe um e-mail válido.';
        } else {
            $chaveEmail = strtolower($email);
            $chaveIp = ipCliente();

            if (
                contarTentativas($conn, 'recuperacao', $chaveEmail, 60) >= 5 ||
                contarTentativas($conn, 'recuperacao_ip', $chaveIp, 60) >= 15
            ) {
                $erro = 'Muitas solicitações. Aguarde alguns minutos e tente novamente.';
            } else {
                registrarTentativa($conn, 'recuperacao', $chaveEmail);
                registrarTentativa($conn, 'recuperacao_ip', $chaveIp);

                $stmt = @$conn->prepare("SELECT id_usuario, nome FROM usuarios WHERE email = ? AND status = 'ativo' LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param("s", $email);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    $usuario = $res ? $res->fetch_assoc() : null;

                    if ($usuario) {
                        $token = bin2hex(random_bytes(32));
                        $hash = hash('sha256', $token);
                        $idUsuario = (int) $usuario['id_usuario'];

                        // Invalida pedidos anteriores
                        try {
                            @$conn->query("UPDATE recuperacao_senha SET usado = 1 WHERE id_usuario = $idUsuario AND usado = 0");
                        } catch (\Throwable $e) {}

                        $ins = @$conn->prepare("INSERT INTO recuperacao_senha (id_usuario, token_hash, expira_em) VALUES (?, ?, NOW() + INTERVAL 60 MINUTE)");
                        if ($ins) {
                            $ins->bind_param("is", $idUsuario, $hash);
                            if ($ins->execute()) {
                                $link = urlBaseNyes() . '/redefinir_senha.php?token=' . $token;
                                $nome = htmlspecialchars($usuario['nome']);
                                $corpo = "<p>Olá, <strong>{$nome}</strong>.</p>"
                                    . "<p>Recebemos um pedido para redefinir a senha da sua conta no NyesClinic. "
                                    . "Clique no botão abaixo para criar uma nova senha. O link vale por <strong>60 minutos</strong> e só pode ser usado uma vez.</p>"
                                    . "<p><a href=\"" . htmlspecialchars($link) . "\" style=\"background:#f0b24f;color:#020617;padding:12px 22px;border-radius:8px;text-decoration:none;font-weight:bold;display:inline-block;\">Redefinir minha senha</a></p>"
                                    . "<p style=\"font-size:13px;color:#64748b;\">Se você não fez esse pedido, ignore este e-mail: sua senha continua a mesma.</p>";

                                $erroSmtp = null;
                                $emailEnviado = enviarEmailNyes($email, 'Redefinição de senha - NyesClinic', $corpo, $erroSmtp);
                                if (!$emailEnviado) {
                                    $linkFallback = $link;
                                    $motivoFallback = $erroSmtp;
                                }
                            }
                        }
                    }
                }

                // Resposta sempre de sucesso para o usuário (evita enumeração de e-mails)
                $enviado = true;
            }
        }
    } catch (\Throwable $e) {
        error_log("Erro em esqueci_senha.php: " . $e->getMessage());
        $erro = "Não foi possível processar a solicitação no momento: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NyesClinic - Recuperar Senha</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>">
</head>
<body>
<section class="login-screen active">
    <div class="login-box">
        <div style="text-align:center;margin-bottom:28px;">
            <h1 class="logo login-logo" style="justify-content:center;">Nyes<span>Clinic</span></h1>
        </div>

        <h2>Recuperar senha</h2>

        <?php if ($erro): ?>
            <div style="background:var(--danger-light);border:1px solid rgba(239,68,68,0.3);color:var(--danger);padding:12px 16px;border-radius:var(--radius-md);margin-bottom:18px;font-size:0.88rem;">
                <?= htmlspecialchars($erro) ?>
            </div>
        <?php endif; ?>

        <?php if ($enviado): ?>
            <div style="background:var(--success-light);border:1px solid rgba(16,185,129,0.3);color:var(--success);padding:12px 16px;border-radius:var(--radius-md);margin-bottom:18px;font-size:0.9rem;line-height:1.5;">
                Se o e-mail informado estiver cadastrado, as instruções para redefinição de senha foram geradas. Verifique também a caixa de spam do seu e-mail.
            </div>

            <?php if (!empty($linkFallback)): ?>
                <div style="background:rgba(240,178,79,0.12);border:1px dashed #f0b24f;color:#92400e;padding:14px;border-radius:var(--radius-md);margin-bottom:18px;font-size:0.86rem;line-height:1.4;">
                    <strong>Ambiente de Apresentação / Testes:</strong>
                    <?= !empty($motivoFallback) ? '<br><small style="color:#78350f;">(' . htmlspecialchars($motivoFallback) . ')</small><br>' : '' ?>
                    Para continuar seu teste agora mesmo, utilize o link direto gerado:
                    <div style="margin-top:10px;">
                        <a href="<?= htmlspecialchars($linkFallback) ?>" class="btn btn-primary" style="display:inline-block;padding:8px 14px;font-size:0.85rem;text-decoration:none;">
                            Acessar Link de Redefinição →
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <p style="color:var(--text-muted);font-size:0.9rem;margin-bottom:16px;">
                Informe o e-mail da sua conta e enviaremos um link para criar uma nova senha.
            </p>
            <form method="POST" action="esqueci_senha.php">
                <?= csrfCampo() ?>
                <div class="input-group">
                    <label for="email">E-mail</label>
                    <input type="email" id="email" name="email" placeholder="seuemail@clinica.com" required autofocus autocomplete="email">
                </div>
                <button class="btn btn-primary" type="submit" style="width:100%;margin-top:8px;padding:12px;">Enviar link de redefinição</button>
            </form>
        <?php endif; ?>

        <button class="btn btn-secondary" style="width:100%;margin-top:14px;padding:10px;" onclick="window.location.href='login.php'">← Voltar ao login</button>
    </div>
</section>
</body>
</html>

