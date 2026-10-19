<?php

date_default_timezone_set('America/Sao_Paulo');

$host = "db_technyes.mysql.dbaas.com.br";
$usuario = "db_technyes";
$senha = "wUd@Ea#x3PhiFL";
$banco = "db_technyes";

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
