<?php

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