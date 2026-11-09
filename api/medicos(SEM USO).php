<?php

require_once __DIR__ . '/../config/sessao.php';

if(
!isset($_SESSION['id_usuario'])
){

    header(
    "Location: login.html"
    );

    exit;
}