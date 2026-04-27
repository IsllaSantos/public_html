<?php
define('DB_HOST', 'db_technyes.mysql.dbaas.com.br');
define('DB_NAME', 'db_technyes');
define('DB_USER', 'db_technyes');
define('DB_PASS', 'wUd@Ea#x3PhiFL');  
define('DB_CHARSET', 'utf8mb4');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false
        ]
    );
} catch(PDOException $e) {
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Erro de conexão']));
}
?>
