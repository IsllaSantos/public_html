<?php

ob_start();
error_reporting(0);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');

function garantirColunasTabela($conn, $tabela, $colunasDesejadas) {
    $sqlCreate = "CREATE TABLE IF NOT EXISTS `$tabela` (";
    $defs = [];
    foreach ($colunasDesejadas as $coluna => $tipo) {
        $defs[] = "`$coluna` $tipo";
    }
    $sqlCreate .= implode(", ", $defs) . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    @$conn->query($sqlCreate);

    $res = $conn->query("SHOW COLUMNS FROM `$tabela`");
    $existentes = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $existentes[] = strtolower($row['Field']);
        }
    }

    foreach ($colunasDesejadas as $coluna => $tipo) {
        if (!in_array(strtolower($coluna), $existentes)) {
            $tipoClean = preg_replace('/PRIMARY KEY/i', '', $tipo);
            $tipoClean = preg_replace('/AUTO_INCREMENT/i', '', $tipoClean);
            @$conn->query("ALTER TABLE `$tabela` ADD COLUMN `$coluna` $tipoClean");
        }
    }
}

try {
    require_once('../config/auth.php');
    require_once('../config/database.php');

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['id_usuario']) || !isset($_SESSION['perfil']) || $_SESSION['perfil'] !== 'medico') {
        ob_clean();
        echo json_encode(['erro' => 'Sessão expirada ou acesso não autorizado. Por favor, faça login novamente.']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ob_clean();
        echo json_encode(['erro' => 'Método não permitido.']);
        exit;
    }

    $id_medico   = intval($_SESSION['id_usuario']);
    $id_consulta = intval($_POST['id_consulta'] ?? 0);

    if ($id_consulta <= 0) {
        ob_clean();
        echo json_encode(['erro' => 'Consulta não informada ou inválida.']);
        exit;
    }

    /* VERIFICA SE A CONSULTA PERTENCE AO MÉDICO */
    $stmt = $conn->prepare("
        SELECT id_consulta, id_paciente, status
        FROM consulta
        WHERE id_consulta = ? AND id_medico = ?
    ");
    if (!$stmt) {
        ob_clean();
        echo json_encode(['erro' => 'Erro ao consultar agendamento.']);
        exit;
    }
    $stmt->bind_param("ii", $id_consulta, $id_medico);
    $stmt->execute();
    $res = $stmt->get_result();
    $consulta = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$consulta) {
        ob_clean();
        echo json_encode(['erro' => 'Consulta não encontrada.']);
        exit;
    }

    /* GARANTE QUE AS TABELAS RECEITA E SOLICITACAO_EXAME EXISTAM E TENHAM AS COLUNAS NECESSÁRIAS */
    garantirColunasTabela($conn, 'receita', [
        'id_receita'    => 'INT AUTO_INCREMENT PRIMARY KEY',
        'id_consulta'   => 'INT NOT NULL',
        'id_medico'     => 'INT NULL',
        'id_paciente'   => 'INT NULL',
        'medicamentos'  => 'TEXT NULL',
        'criado_em'     => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
    ]);

    garantirColunasTabela($conn, 'solicitacao_exame', [
        'id_solicitacao' => 'INT AUTO_INCREMENT PRIMARY KEY',
        'id_consulta'    => 'INT NOT NULL',
        'id_medico'      => 'INT NULL',
        'id_paciente'    => 'INT NULL',
        'exames'         => 'TEXT NULL',
        'observacoes'    => 'TEXT NULL',
        'criado_em'      => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
    ]);

    $medicamentos_receita = trim($_POST['medicamentos_receita'] ?? '');
    $exames_solicitados   = trim($_POST['exames_solicitados']   ?? '');
    $observacoes_exames   = trim($_POST['observacoes_exames']   ?? '');

    $salvos = [];

    /* RECEITA */
    if (!empty($medicamentos_receita)) {
        $stmtR = $conn->prepare("SELECT id_receita FROM receita WHERE id_consulta = ?");
        $stmtR->bind_param("i", $id_consulta);
        $stmtR->execute();
        $resR = $stmtR->get_result();
        $existenteR = $resR ? $resR->fetch_assoc() : null;
        $stmtR->close();

        if ($existenteR) {
            $stmtUp = $conn->prepare("UPDATE receita SET medicamentos = ?, criado_em = NOW() WHERE id_consulta = ?");
            $stmtUp->bind_param("si", $medicamentos_receita, $id_consulta);
            if ($stmtUp->execute()) { $salvos[] = 'receita'; }
            $stmtUp->close();
        } else {
            $stmtIns = $conn->prepare("INSERT INTO receita (id_consulta, id_medico, id_paciente, medicamentos, criado_em) VALUES (?, ?, ?, ?, NOW())");
            $stmtIns->bind_param("iiis", $id_consulta, $id_medico, $consulta['id_paciente'], $medicamentos_receita);
            if ($stmtIns->execute()) { $salvos[] = 'receita'; }
            $stmtIns->close();
        }
    }

    /* EXAMES */
    if (!empty($exames_solicitados)) {
        $stmtE = $conn->prepare("SELECT id_solicitacao FROM solicitacao_exame WHERE id_consulta = ?");
        $stmtE->bind_param("i", $id_consulta);
        $stmtE->execute();
        $resE = $stmtE->get_result();
        $existenteE = $resE ? $resE->fetch_assoc() : null;
        $stmtE->close();

        if ($existenteE) {
            $stmtUp = $conn->prepare("UPDATE solicitacao_exame SET exames = ?, observacoes = ?, criado_em = NOW() WHERE id_consulta = ?");
            $stmtUp->bind_param("ssi", $exames_solicitados, $observacoes_exames, $id_consulta);
            if ($stmtUp->execute()) { $salvos[] = 'exames'; }
            $stmtUp->close();
        } else {
            $stmtIns = $conn->prepare("INSERT INTO solicitacao_exame (id_consulta, id_medico, id_paciente, exames, observacoes, criado_em) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmtIns->bind_param("iiiss", $id_consulta, $id_medico, $consulta['id_paciente'], $exames_solicitados, $observacoes_exames);
            if ($stmtIns->execute()) { $salvos[] = 'exames'; }
            $stmtIns->close();
        }
    }

    ob_clean();
    if (empty($salvos)) {
        echo json_encode(['sucesso' => true, 'mensagem' => 'Nenhuma alteração de receita/exames para salvar.'], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['sucesso' => true, 'mensagem' => 'Salvo: ' . implode(', ', $salvos) . '.'], JSON_UNESCAPED_UNICODE);
    }
    exit;

} catch (Throwable $eGlobal) {
    ob_clean();
    echo json_encode(['erro' => 'Erro ao salvar receita/exames: ' . $eGlobal->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}


