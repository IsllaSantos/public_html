<?php

date_default_timezone_set('America/Sao_Paulo');

/*
 * Credenciais: variáveis de ambiente (DB_HOST, DB_USER, DB_PASS, DB_NAME)
 * ou arquivo technyes_config.php (preferencialmente fora do public_html;
 * alternativa: dentro de config/, protegido pelo .htaccess).
 */
$cfg = [];
foreach ([__DIR__ . '/../../technyes_config.php', __DIR__ . '/technyes_config.php'] as $cfgArquivo) {
    if (is_file($cfgArquivo)) {
        $cfg = require $cfgArquivo;
        break;
    }
}

$host    = getenv('DB_HOST') ?: ($cfg['host'] ?? '');
$usuario = getenv('DB_USER') ?: ($cfg['usuario'] ?? '');
$senha   = getenv('DB_PASS') ?: ($cfg['senha'] ?? '');
$banco   = getenv('DB_NAME') ?: ($cfg['banco'] ?? '');

if ($host === '' || $usuario === '' || $banco === '') {
    die("Configuração do banco de dados ausente.");
}

$conn = new mysqli(
    $host,
    $usuario,
    $senha,
    $banco
);

if ($conn->connect_error) {

    die("Erro de conexão: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
$conn->query("SET time_zone = '-03:00'");
require_once(__DIR__ . '/status_consulta.php');
garantirStatusConsulta($conn);
