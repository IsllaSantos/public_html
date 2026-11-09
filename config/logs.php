<?php

/**
 * Registro de ações do sistema (aba Logs do admin).
 * Nunca interrompe o fluxo: falhas de log são ignoradas.
 */
function registrarLog($conn, $acao)
{
    try {
        static $criada = false;

        if (!$criada) {
            @$conn->query("
                CREATE TABLE IF NOT EXISTS logs_sistema (
                    id_log INT AUTO_INCREMENT PRIMARY KEY,
                    id_usuario INT NULL,
                    nome_usuario VARCHAR(150) NULL,
                    perfil VARCHAR(30) NULL,
                    acao VARCHAR(255) NOT NULL,
                    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            $criada = true;
        }

        if (session_status() === PHP_SESSION_NONE) {
            require_once __DIR__ . '/sessao.php';
        }

        $idUsuario = isset($_SESSION['id_usuario']) ? intval($_SESSION['id_usuario']) : null;
        $nome = $_SESSION['nome'] ?? null;
        $perfil = $_SESSION['perfil'] ?? null;

        $stmt = @$conn->prepare("INSERT INTO logs_sistema (id_usuario, nome_usuario, perfil, acao) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("isss", $idUsuario, $nome, $perfil, $acao);
            @$stmt->execute();
            $stmt->close();
        }
    } catch (Throwable $e) {
        /* ignora */
    }
}

