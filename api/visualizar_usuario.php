<?php

ob_start();
error_reporting(0);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');

try {
    require_once('../config/auth.php');
    require_once('../config/database.php');

    verificarLogin();

    $perfilLogado = $_SESSION['perfil'] ?? '';
    $ehSecretaria = ($perfilLogado === 'secretaria');
    $ehAdmin      = ($perfilLogado === 'admin');

    if (!$ehSecretaria && !$ehAdmin) {
        ob_clean();
        echo json_encode(['erro' => 'Sem permissão para visualizar dados de usuários.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $id_usuario = intval($_GET['id'] ?? 0);
    if ($id_usuario <= 0) {
        ob_clean();
        echo json_encode(['erro' => 'Usuário inválido.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $sql = "
        SELECT
            u.id_usuario,
            u.nome,
            u.email,
            u.perfil,
            u.status,
            u.id_clinica,
            u.cpf,
            u.rg,
            u.data_nascimento,
            u.sexo,
            u.telefone,
            u.celular,
            u.crm,
            u.uf_crm,
            u.especialidade,
            u.horario_inicio,
            u.horario_fim,
            u.duracao_consulta,
            u.cep,
            u.endereco,
            u.numero,
            u.complemento,
            u.bairro,
            u.cidade,
            u.estado,
            u.convenio,
            u.numero_carteirinha,
            u.contato_emergencia_nome,
            u.contato_emergencia_telefone,
            u.data_cadastro,
            c.nome AS nome_clinica
        FROM usuarios u
        LEFT JOIN clinica c ON u.id_clinica = c.id_clinica
        WHERE u.id_usuario = ?
    ";

    if ($ehSecretaria) {
        $sql .= " AND u.id_clinica = ? AND u.perfil IN ('medico', 'paciente')";
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        ob_clean();
        echo json_encode(['erro' => 'Erro ao preparar consulta.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($ehSecretaria) {
        $idClinicaSessao = intval($_SESSION['id_clinica'] ?? 0);
        $stmt->bind_param("ii", $id_usuario, $idClinicaSessao);
    } else {
        $stmt->bind_param("i", $id_usuario);
    }

    $stmt->execute();
    $res = $stmt->get_result();

    if (!$res || $res->num_rows === 0) {
        $stmt->close();
        ob_clean();
        echo json_encode(['erro' => 'Usuário não encontrado ou sem permissão de acesso.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $usuario = $res->fetch_assoc();
    $stmt->close();

    /* Busca quantidade de consultas vinculadas (se médico ou paciente) */
    $totalConsultas = 0;
    if ($usuario['perfil'] === 'medico' || $usuario['perfil'] === 'paciente') {
        $campoVinculo = ($usuario['perfil'] === 'medico') ? 'id_medico' : 'id_paciente';
        $stmtC = @$conn->prepare("SELECT COUNT(*) AS total FROM consulta WHERE $campoVinculo = ?");
        if ($stmtC) {
            $stmtC->bind_param("i", $id_usuario);
            $stmtC->execute();
            $rowC = $stmtC->get_result()->fetch_assoc();
            $totalConsultas = intval($rowC['total'] ?? 0);
            $stmtC->close();
        }
    }
    $usuario['total_consultas'] = $totalConsultas;

    ob_clean();
    echo json_encode([
        'sucesso' => true,
        'usuario' => $usuario
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    ob_clean();
    echo json_encode([
        'erro' => 'Erro ao carregar dados do usuário: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

