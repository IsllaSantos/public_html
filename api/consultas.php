<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userId = $_SESSION['id_usuario'] ?? $_SESSION['user_id'] ?? null;
$perfil = $_SESSION['perfil'] ?? $_SESSION['user_type'] ?? null;

if (!$userId || !$perfil) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

$idClinica = intval($_SESSION['id_clinica'] ?? 0);
$userId = intval($userId);

try {
    $consultas = [];

    if ($perfil === 'paciente') {
        $sql = "
            SELECT 
                c.*,
                m.nome AS medico_nome,
                m.crm AS medico_crm,
                m.uf_crm AS medico_uf_crm,
                COALESCE(esp.nome, 'Clínico Geral') AS especialidade
            FROM consulta c
            INNER JOIN usuarios m ON c.id_medico = m.id_usuario
            LEFT JOIN especialidade esp ON m.id_especialidade = esp.id_especialidade
            WHERE c.id_paciente = ?
            ORDER BY c.data_consulta DESC, c.hora_consulta DESC
            LIMIT 50
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $consultas[] = $row;
        }
    } elseif ($perfil === 'medico') {
        $sql = "
            SELECT 
                c.*,
                p.nome AS paciente_nome,
                p.cpf AS paciente_cpf,
                p.telefone AS paciente_telefone,
                p.celular AS paciente_celular
            FROM consulta c
            INNER JOIN usuarios p ON c.id_paciente = p.id_usuario
            WHERE c.id_medico = ?
            ORDER BY c.data_consulta DESC, c.hora_consulta DESC
            LIMIT 50
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $consultas[] = $row;
        }
    } elseif ($perfil === 'secretaria' && $idClinica > 0) {
        $sql = "
            SELECT 
                c.*,
                p.nome AS paciente_nome,
                m.nome AS medico_nome,
                COALESCE(esp.nome, 'Clínico Geral') AS especialidade
            FROM consulta c
            INNER JOIN usuarios p ON c.id_paciente = p.id_usuario
            INNER JOIN usuarios m ON c.id_medico = m.id_usuario
            LEFT JOIN especialidade esp ON m.id_especialidade = esp.id_especialidade
            WHERE p.id_clinica = ?
            ORDER BY c.data_consulta DESC, c.hora_consulta DESC
            LIMIT 50
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $idClinica);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $consultas[] = $row;
        }
    } elseif ($perfil === 'admin') {
        $sql = "
            SELECT 
                c.*,
                p.nome AS paciente_nome,
                m.nome AS medico_nome,
                cl.nome AS clinica_nome
            FROM consulta c
            INNER JOIN usuarios p ON c.id_paciente = p.id_usuario
            INNER JOIN usuarios m ON c.id_medico = m.id_usuario
            LEFT JOIN clinicas cl ON p.id_clinica = cl.id_clinica
            ORDER BY c.data_consulta DESC, c.hora_consulta DESC
            LIMIT 50
        ";
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $consultas[] = $row;
            }
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Tipo de usuário inválido']);
        exit;
    }

    echo json_encode(['success' => true, 'consultas' => $consultas], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('Erro ao buscar consultas: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao carregar consultas']);
}
