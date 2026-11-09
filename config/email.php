<?php

/**
 * Envio de e-mails do NyesClinic (recuperação de senha, lembretes).
 *
 * Usa mail() do PHP (o servidor de hospedagem precisa ter e-mail habilitado).
 * Configurações opcionais (variáveis de ambiente ou technyes_config.php):
 *   EMAIL_REMETENTE / 'email_remetente'  -> ex.: nao-responda@seudominio.com.br
 *   URL_BASE        / 'url_base'         -> ex.: https://seudominio.com.br
 *   EMAIL_CONTATO   / 'email_contato'    -> e-mail de contato exibido na política
 */
function configNyes($chave, $padrao = '')
{
    static $cfg = null;

    if ($cfg === null) {
        $cfg = [];
        foreach ([__DIR__ . '/../../technyes_config.php', __DIR__ . '/technyes_config.php'] as $arq) {
            if (is_file($arq)) {
                $cfg = require $arq;
                break;
            }
        }
    }

    $env = getenv(strtoupper($chave));
    if ($env !== false && $env !== '') {
        return $env;
    }

    return $cfg[$chave] ?? $padrao;
}

function urlBaseNyes()
{
    $base = configNyes('url_base', '');
    if ($base !== '') {
        return rtrim($base, '/');
    }

    if (empty($_SERVER['HTTP_HOST'])) {
        return '';
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    $dir = preg_replace('#/(api|cron)$#', '', $dir);

    return ($https ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $dir;
}

/**
 * Envia um e-mail autenticado via socket SMTP puro (sem dependência de bibliotecas externas).
 * Suporta STARTTLS (porta 587) e SSL direto (porta 465).
 */
function enviarViaSmtp($para, $assunto, $htmlCorpo, &$erro = null)
{
    $host      = configNyes('smtp_host', 'smtp.gmail.com');
    $port      = (int) configNyes('smtp_port', 587);
    $seguranca = strtolower(configNyes('smtp_seguranca', 'tls'));
    $usuario   = configNyes('smtp_usuario', '');
    $senha     = configNyes('smtp_senha', '');
    $remetente = configNyes('smtp_remetente', '') ?: $usuario;
    $nomeDe    = configNyes('smtp_nome', 'NyesClinic');

    if (empty($host) || empty($usuario) || empty($senha)) {
        $erro = 'Configurações de SMTP incompletas (smtp_usuario ou smtp_senha vazios em technyes_config.php).';
        return false;
    }

    $context = stream_context_create([
        'ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
        ]
    ]);

    $protocolo = ($seguranca === 'ssl' || $port === 465) ? 'ssl://' : 'tcp://';
    $timeout = 15;

    $socket = @stream_socket_client(
        $protocolo . $host . ':' . $port,
        $errno,
        $errstr,
        $timeout,
        STREAM_CLIENT_CONNECT,
        $context
    );

    if (!$socket) {
        $erro = "Não foi possível conectar ao servidor SMTP ($host:$port): [$errno] $errstr";
        error_log($erro);
        return false;
    }

    stream_set_timeout($socket, $timeout);

    $ler = function() use ($socket) {
        $resp = '';
        while (!feof($socket)) {
            $linha = fgets($socket, 512);
            if ($linha === false) break;
            $resp .= $linha;
            if (isset($linha[3]) && ($linha[3] === ' ' || $linha[3] === "\r" || $linha[3] === "\n")) {
                break;
            }
        }
        return $resp;
    };

    $enviar = function($cmd, $codigosEsperados = [250]) use ($socket, $ler, &$erro) {
        if (!is_array($codigosEsperados)) {
            $codigosEsperados = [$codigosEsperados];
        }
        fwrite($socket, $cmd . "\r\n");
        $resp = $ler();
        $cod = (int) substr($resp, 0, 3);
        if (!in_array($cod, $codigosEsperados, true)) {
            $erro = "Falha no SMTP com o comando [" . explode(' ', $cmd)[0] . "]. Código: $cod. Resposta: " . trim($resp);
            error_log($erro);
            return false;
        }
        return $resp;
    };

    // Saudação inicial (220)
    $saudacao = $ler();
    if ((int) substr($saudacao, 0, 3) !== 220) {
        $erro = "Servidor SMTP não respondeu com código 220: " . trim($saudacao);
        fclose($socket);
        return false;
    }

    $clientHost = !empty($_SERVER['HTTP_HOST']) ? preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST']) : 'localhost';

    if (!$enviar("EHLO $clientHost", [250])) {
        if (!$enviar("HELO $clientHost", [250])) {
            fclose($socket);
            return false;
        }
    }

    // TLS / STARTTLS
    if ($seguranca === 'tls' || ($seguranca !== 'ssl' && $port === 587)) {
        if (!$enviar("STARTTLS", [220])) {
            fclose($socket);
            return false;
        }

        $cryptoOk = @stream_socket_enable_crypto(
            $socket,
            true,
            STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT
        );
        if (!$cryptoOk) {
            $cryptoOk = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        }
        if (!$cryptoOk) {
            $erro = "Falha ao negociar TLS com o servidor SMTP.";
            error_log($erro);
            fclose($socket);
            return false;
        }

        if (!$enviar("EHLO $clientHost", [250])) {
            fclose($socket);
            return false;
        }
    }

    // Autenticação AUTH LOGIN
    if (!$enviar("AUTH LOGIN", [334])) {
        fclose($socket);
        return false;
    }

    if (!$enviar(base64_encode($usuario), [334])) {
        $erro = "Usuário SMTP não aceito pelo servidor.";
        fclose($socket);
        return false;
    }

    if (!$enviar(base64_encode($senha), [235])) {
        $erro = "Senha de SMTP recusada pelo servidor (verifique usuário e senha/senha de app).";
        fclose($socket);
        return false;
    }

    // Envelope
    if (!$enviar("MAIL FROM: <$remetente>", [250])) {
        fclose($socket);
        return false;
    }

    if (!$enviar("RCPT TO: <$para>", [250, 251])) {
        fclose($socket);
        return false;
    }

    if (!$enviar("DATA", [354])) {
        fclose($socket);
        return false;
    }

    // Cabeçalhos e corpo MIME
    $boundary = "----=_Part_" . md5(uniqid(microtime(true), true));
    $assuntoB64 = '=?UTF-8?B?' . base64_encode($assunto) . '?=';
    $nomeDeB64 = '=?UTF-8?B?' . base64_encode($nomeDe) . '?=';

    $headers = [
        "Date: " . date('r'),
        "To: <$para>",
        "From: $nomeDeB64 <$remetente>",
        "Subject: $assuntoB64",
        "Message-ID: <" . md5(uniqid(microtime(true), true)) . "@$clientHost>",
        "MIME-Version: 1.0",
        "Content-Type: multipart/alternative; boundary=\"$boundary\"",
        "X-Mailer: NyesClinic Mailer"
    ];

    $textoPlano = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $htmlCorpo));

    $msg = implode("\r\n", $headers) . "\r\n\r\n";
    $msg .= "--$boundary\r\n";
    $msg .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $msg .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $msg .= chunk_split(base64_encode($textoPlano)) . "\r\n";
    $msg .= "--$boundary\r\n";
    $msg .= "Content-Type: text/html; charset=UTF-8\r\n";
    $msg .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $msg .= chunk_split(base64_encode($htmlCorpo)) . "\r\n";
    $msg .= "--$boundary--\r\n";
    $msg .= ".";

    fwrite($socket, $msg . "\r\n");
    $respData = $ler();
    if ((int) substr($respData, 0, 3) !== 250) {
        $erro = "Falha ao enviar mensagem: " . trim($respData);
        error_log($erro);
        fclose($socket);
        return false;
    }

    $enviar("QUIT", [221]);
    fclose($socket);
    return true;
}

/**
 * Envio via API HTTPS (Brevo ou Resend). Não usa SMTP nem senha de app:
 * só uma chave de API. Funciona em hospedagens que bloqueiam portas de e-mail.
 */
function enviarViaApiEmail($para, $assunto, $htmlCorpo, &$erro = null)
{
    $provedor  = strtolower(configNyes('email_provedor', ''));
    $apiKey    = configNyes('email_api_key', '');
    $remetente = configNyes('email_remetente', '');
    $nomeDe    = configNyes('email_nome', 'NyesClinic');

    if ($provedor === '' || $apiKey === '' || $remetente === '') {
        $erro = 'API de e-mail não configurada (email_provedor, email_api_key e email_remetente).';
        return false;
    }

    if ($provedor === 'brevo') {
        $url = 'https://api.brevo.com/v3/smtp/email';
        $headers = ['accept: application/json', 'content-type: application/json', 'api-key: ' . $apiKey];
        $payload = [
            'sender'      => ['name' => $nomeDe, 'email' => $remetente],
            'to'          => [['email' => $para]],
            'subject'     => $assunto,
            'htmlContent' => $htmlCorpo,
        ];
    } elseif ($provedor === 'resend') {
        $url = 'https://api.resend.com/emails';
        $headers = ['content-type: application/json', 'Authorization: Bearer ' . $apiKey];
        $payload = [
            'from'    => $nomeDe . ' <' . $remetente . '>',
            'to'      => [$para],
            'subject' => $assunto,
            'html'    => $htmlCorpo,
        ];
    } else {
        $erro = "Provedor de e-mail desconhecido: $provedor (use 'brevo' ou 'resend').";
        return false;
    }

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $status = 0;
    $resp = '';

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
        ]);
        $resp = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($resp === false) {
            $erro = 'Falha de rede ao chamar a API de e-mail: ' . curl_error($ch);
            curl_close($ch);
            error_log($erro);
            return false;
        }
        curl_close($ch);
    } else {
        $ctx = stream_context_create(['http' => [
            'method'        => 'POST',
            'header'        => implode("\r\n", $headers),
            'content'       => $json,
            'timeout'       => 20,
            'ignore_errors' => true,
        ]]);
        $resp = @file_get_contents($url, false, $ctx);
        if ($resp === false) {
            $erro = 'Falha de rede ao chamar a API de e-mail.';
            error_log($erro);
            return false;
        }
        foreach ($http_response_header ?? [] as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) {
                $status = (int) $m[1];
            }
        }
    }

    if ($status >= 200 && $status < 300) {
        return true;
    }

    $erro = "API de e-mail ($provedor) retornou HTTP $status: " . substr(trim((string) $resp), 0, 300);
    error_log($erro);
    return false;
}

function enviarEmailNyes($para, $assunto, $htmlCorpo, &$erro = null)
{
    if (!filter_var($para, FILTER_VALIDATE_EMAIL)) {
        $erro = 'E-mail de destino inválido.';
        return false;
    }

    $host = $_SERVER['HTTP_HOST'] ?? php_uname('n');
    $host = preg_replace('/:\d+$/', '', $host);

    $corpo = '<div style="font-family:Arial,sans-serif;max-width:560px;margin:auto;color:#0f172a;line-height:1.6;">'
        . '<h2 style="margin-bottom:8px;color:#1e293b;">Nyes<span style="color:#d99b38;">Clinic</span></h2>'
        . $htmlCorpo
        . '<hr style="border:none;border-top:1px solid #e2e8f0;margin:24px 0 12px;">'
        . '<p style="font-size:12px;color:#64748b;">Mensagem automática do sistema NyesClinic. Por favor, não responda este e-mail.</p>'
        . '</div>';

    // 1. Prioridade: API HTTPS (Brevo / Resend) - moderno, via porta 443, sem senha de app
    $provedor = strtolower(configNyes('email_provedor', ''));
    $apiKey   = configNyes('email_api_key', '');

    if ($provedor !== '' && $provedor !== 'smtp' && $provedor !== 'mail') {
        if ($apiKey === '') {
            $erro = "Preencha 'email_api_key' e 'email_remetente' em config/technyes_config.php.";
            return false;
        }
        return enviarViaApiEmail($para, $assunto, $corpo, $erro);
    }

    // 2. Alternativa: SMTP autenticado (se configurado)
    $smtpUsuario = configNyes('smtp_usuario', '');
    $smtpSenha   = configNyes('smtp_senha', '');

    if (!empty($smtpUsuario) && !empty($smtpSenha)) {
        return enviarViaSmtp($para, $assunto, $corpo, $erro);
    }

    // 3. Fallback: função mail() nativa apenas se email_provedor === 'mail'
    if ($provedor === 'mail' && function_exists('mail')) {
        $remetente = configNyes('email_remetente', 'nao-responda@' . $host);
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: NyesClinic <' . $remetente . '>',
            'X-Mailer: NyesClinic',
        ];
        $assuntoCodificado = '=?UTF-8?B?' . base64_encode($assunto) . '?=';
        try {
            if (@mail($para, $assuntoCodificado, $corpo, implode("\r\n", $headers))) {
                return true;
            }
        } catch (\Throwable $e) {
            $erro = "Erro no mail(): " . $e->getMessage();
        }
    }

    if (empty($erro)) {
        $erro = 'Serviço de e-mail ainda não configurado em config/technyes_config.php.';
    }
    return false;
}

