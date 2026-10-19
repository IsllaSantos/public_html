<?php

require_once('../config/auth.php');
require_once('../config/database.php');

header('Content-Type: application/json');

/* Aceita qualquer perfil logado */

$res = $conn->query("
    SELECT id_especialidade, nome
    FROM especialidades
    WHERE status = 'ativa'
    ORDER BY nome
");

$lista = [];
while ($row = $res->fetch_assoc()) {
    $lista[] = $row;
}

echo json_encode($lista);
