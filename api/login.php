<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://technyes.devfatec-campinas.com.br');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

session_start();

require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$email = filter_var($data['email'] ?? '', FILTER_SANITIZE_EMAIL);
$senha = $data['password'] ?? '';
$tipo_usuario = trim($data['user_type'] ?? '');

if (empty($email) || empty($senha) || empty($tipo_usuario)) {
    echo json_encode(['success' => false, 'message' => 'Todos os campos são obrigatórios']);
    exit;
}

try {
    if ($tipo_usuario === 'paciente') {
        $stmt = $pdo->prepare("
            SELECT id_paciente AS id, nome_completo, email, senha_hash, 'paciente' AS tipo
            FROM paciente
            WHERE email = ? AND ativo = 1
            LIMIT 1
        ");
    } elseif ($tipo_usuario === 'medico') {
        $stmt = $pdo->prepare("
            SELECT id_medico AS id, nome_completo, email, senha_hash, especialidade, 'medico' AS tipo
            FROM medico
            WHERE email = ? AND ativo = 1
            LIMIT 1
        ");
    } else {
        echo json_encode(['success' => false, 'message' => 'Tipo de usuário inválido']);
        exit;
    }

    $stmt->execute([$email]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        echo json_encode(['success' => false, 'message' => 'Usuário não encontrado ou inativo']);
        exit;
    }

    if (!password_verify($senha, $usuario['senha_hash'])) {
        echo json_encode(['success' => false, 'message' => 'E-mail ou senha incorretos']);
        exit;
    }

    $_SESSION['user_id'] = $usuario['id'];
    $_SESSION['user_name'] = $usuario['nome_completo'];
    $_SESSION['user_type'] = $usuario['tipo'];
    $_SESSION['user_email'] = $usuario['email'];

    echo json_encode([
        'success' => true,
        'user' => [
            'id' => $usuario['id'],
            'nome' => $usuario['nome_completo'],
            'email' => $usuario['email'],
            'tipo' => $usuario['tipo'],
            'especialidade' => $usuario['especialidade'] ?? null
        ]
    ]);
} catch (PDOException $e) {
    error_log('Erro login: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro no servidor. Tente novamente.']);
}
?>