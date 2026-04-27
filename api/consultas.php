<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config/database.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

try {
    if ($user_type === 'paciente') {
        $stmt = $pdo->prepare("
            SELECT c.*, m.nome_completo as medico_nome, m.especialidade 
            FROM consulta c
            INNER JOIN medico m ON c.id_medico = m.id_medico
            WHERE c.id_paciente = ?
            ORDER BY c.data_consulta DESC, c.hora_consulta DESC
            LIMIT 20
        ");
        $stmt->execute([$user_id]);
    } elseif ($user_type === 'medico' || $user_type === 'recepcionista' || $user_type === 'admin') {
        $stmt = $pdo->prepare("
            SELECT c.*, p.nome_completo as paciente_nome 
            FROM consulta c
            INNER JOIN paciente p ON c.id_paciente = p.id_paciente
            WHERE c.id_medico = ?
            ORDER BY c.data_consulta DESC, c.hora_consulta DESC
            LIMIT 20
        ");
        $stmt->execute([$user_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Tipo de usuário inválido']);
        exit;
    }
    
    $consultas = $stmt->fetchAll();
    echo json_encode(['success' => true, 'consultas' => $consultas]);
    
} catch(PDOException $e) {
    error_log('Erro ao buscar consultas: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao carregar consultas']);
}
?>
