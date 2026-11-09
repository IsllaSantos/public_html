<?php

/**
 * Funções de segurança do NyesClinic:
 * CSRF, verificação de origem, limite de tentativas, aceite LGPD e esquema auxiliar.
 */

require_once __DIR__ . '/sessao.php';

/* Versão vigente dos Termos/Política de Privacidade. Alterar força novo aceite. */
const LGPD_VERSAO = '2026-11';

/* ---------- CSRF ---------- */

function csrfToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfCampo()
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken()) . '">';
}

function csrfValido()
{
    $enviado = $_POST['csrf_token'] ?? '';
    return is_string($enviado)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $enviado);
}

/**
 * Defesa CSRF para requisições que alteram dados: o Origin (ou Referer)
 * precisa pertencer ao mesmo host do sistema.
 */
function origemRequisicaoValida()
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return true;
    }

    // Se o token CSRF estiver presente e válido, a requisição é legítima da sessão
    if (function_exists('csrfValido') && csrfValido()) {
        return true;
    }

    $origem = $_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
    if ($origem === '' || $origem === 'null') {
        return true; // navegadores sem cabeçalho/sandboxed; SameSite=Lax e CSRF cobrem
    }

    $hostOrigem = parse_url($origem, PHP_URL_HOST);
    if (!$hostOrigem) {
        return true;
    }
    $hostOrigem = strtolower($hostOrigem);

    // Coleta hosts válidos do servidor (suporta proxy reverso, subdomínios da Fatec e portas)
    $candidatos = [];
    foreach (['HTTP_X_FORWARDED_HOST', 'HTTP_HOST', 'SERVER_NAME'] as $header) {
        if (!empty($_SERVER[$header])) {
            foreach (explode(',', $_SERVER[$header]) as $p) {
                $h = strtolower(trim(preg_replace('/:\d+$/', '', $p)));
                if ($h !== '') {
                    $candidatos[] = $h;
                }
            }
        }
    }

    if (empty($candidatos)) {
        return true;
    }

    foreach ($candidatos as $hostServidor) {
        // Igualdade exata
        if ($hostOrigem === $hostServidor) {
            return true;
        }

        // Subdomínio (ex: technyes.devfatec-campinas.com.br vs devfatec-campinas.com.br)
        if (substr($hostOrigem, -strlen('.' . $hostServidor)) === '.' . $hostServidor) {
            return true;
        }
        if (substr($hostServidor, -strlen('.' . $hostOrigem)) === '.' . $hostOrigem) {
            return true;
        }

        // Localhost / 127.0.0.1 em testes
        if (in_array($hostServidor, ['localhost', '127.0.0.1', '::1'], true)) {
            return true;
        }
    }

    return false;
}

function exigirOrigemValida()
{
    if (origemRequisicaoValida()) {
        return;
    }

    http_response_code(403);
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    if (stripos($accept, 'application/json') !== false || strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['sucesso' => false, 'erro' => 'Requisição bloqueada (origem inválida).'], JSON_UNESCAPED_UNICODE);
    } else {
        echo 'Requisição bloqueada (origem inválida).';
    }
    exit;
}

/* ---------- Esquema auxiliar ---------- */

function colunaExiste($conn, $tabela, $coluna)
{
    try {
        $t = $conn->real_escape_string($tabela);
        $c = $conn->real_escape_string($coluna);
        $res = @$conn->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
        return $res && $res->num_rows > 0;
    } catch (\Throwable $e) {
        return false;
    }
}

function garantirEsquemaSeguranca($conn)
{
    static $feito = false;
    if ($feito) {
        return;
    }
    $feito = true;

    try {
        @$conn->query("
            CREATE TABLE IF NOT EXISTS seguranca_tentativas (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                tipo VARCHAR(30) NOT NULL,
                chave VARCHAR(190) NOT NULL,
                criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_tipo_chave (tipo, chave, criado_em)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } catch (\Throwable $e) {
        error_log("Erro ao criar seguranca_tentativas: " . $e->getMessage());
    }

    try {
        @$conn->query("
            CREATE TABLE IF NOT EXISTS recuperacao_senha (
                id INT AUTO_INCREMENT PRIMARY KEY,
                id_usuario INT NOT NULL,
                token_hash CHAR(64) NOT NULL,
                expira_em DATETIME NOT NULL,
                usado TINYINT(1) NOT NULL DEFAULT 0,
                criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_token (token_hash),
                INDEX idx_usuario (id_usuario)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } catch (\Throwable $e) {
        error_log("Erro ao criar recuperacao_senha: " . $e->getMessage());
    }

    try {
        if (!colunaExiste($conn, 'usuarios', 'aceite_lgpd_em')) {
            @$conn->query("ALTER TABLE usuarios ADD COLUMN aceite_lgpd_em DATETIME NULL");
        }
    } catch (\Throwable $e) {}

    try {
        if (!colunaExiste($conn, 'usuarios', 'aceite_lgpd_versao')) {
            @$conn->query("ALTER TABLE usuarios ADD COLUMN aceite_lgpd_versao VARCHAR(20) NULL");
        }
    } catch (\Throwable $e) {}

    try {
        if (!colunaExiste($conn, 'consulta', 'lembrete_enviado_em')) {
            @$conn->query("ALTER TABLE consulta ADD COLUMN lembrete_enviado_em DATETIME NULL");
        }
    } catch (\Throwable $e) {}
}

/* ---------- Limite de tentativas ---------- */

function registrarTentativa($conn, $tipo, $chave)
{
    try {
        $stmt = @$conn->prepare("INSERT INTO seguranca_tentativas (tipo, chave) VALUES (?, ?)");
        if ($stmt) {
            $stmt->bind_param("ss", $tipo, $chave);
            $stmt->execute();
        }
        @$conn->query("DELETE FROM seguranca_tentativas WHERE criado_em < (NOW() - INTERVAL 2 DAY)");
    } catch (\Throwable $e) {}
}

function contarTentativas($conn, $tipo, $chave, $minutos)
{
    try {
        $minutos = (int) $minutos;
        $stmt = @$conn->prepare(
            "SELECT COUNT(*) AS n FROM seguranca_tentativas
             WHERE tipo = ? AND chave = ? AND criado_em > (NOW() - INTERVAL $minutos MINUTE)"
        );
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param("ss", $tipo, $chave);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        return (int) ($row['n'] ?? 0);
    } catch (\Throwable $e) {
        return 0;
    }
}

function limparTentativas($conn, $tipo, $chave)
{
    try {
        $stmt = @$conn->prepare("DELETE FROM seguranca_tentativas WHERE tipo = ? AND chave = ?");
        if ($stmt) {
            $stmt->bind_param("ss", $tipo, $chave);
            $stmt->execute();
        }
    } catch (\Throwable $e) {}
}

function ipCliente()
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/* ---------- Senha ---------- */

function senhaForte($senha, &$erro = null)
{
    if (strlen($senha) < 8) {
        $erro = 'A senha deve ter pelo menos 8 caracteres.';
        return false;
    }
    if (!preg_match('/[A-Za-z]/', $senha) || !preg_match('/\d/', $senha)) {
        $erro = 'A senha deve conter letras e números.';
        return false;
    }
    return true;
}

/* ---------- LGPD ---------- */

function caminhoRaiz()
{
    return strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false ? '../' : '';
}

