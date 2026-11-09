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
        require_once __DIR__ . '/../config/sessao.php';
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
        echo json_encode(['erro' => 'Consulta não encontrada ou não pertence a este médico.']);
        exit;
    }
    /* CONSULTA FINALIZADA: SOMENTE LEITURA */
    if ($consulta['status'] === 'finalizada') {
        ob_clean();
        echo json_encode(['erro' => 'Consulta finalizada: não é possível alterar as informações.'], JSON_UNESCAPED_UNICODE);
        exit;
    }


    /* GARANTE QUE A TABELA prontuario EXISTA E TENHA TODAS AS COLUNAS */
    garantirColunasTabela($conn, 'prontuario', [
        'id_prontuario'        => 'INT AUTO_INCREMENT PRIMARY KEY',
        'id_consulta'          => 'INT NOT NULL',
        'id_paciente'          => 'INT NULL',
        'id_medico'            => 'INT NULL',
        'queixa_principal'     => 'TEXT NULL',
        'historico_doenca'     => 'TEXT NULL',
        'antecedentes'         => 'TEXT NULL',
        'alergias'             => 'TEXT NULL',
        'medicamentos'         => 'TEXT NULL',
        'historico_familiar'   => 'TEXT NULL',
        'habitos_vida'         => 'TEXT NULL',
        'pressao_arterial'     => 'VARCHAR(50) NULL',
        'frequencia_cardiaca'  => 'VARCHAR(50) NULL',
        'frequencia_respiratoria' => 'VARCHAR(50) NULL',
        'temperatura'          => 'VARCHAR(50) NULL',
        'peso'                 => 'VARCHAR(50) NULL',
        'altura'               => 'VARCHAR(50) NULL',
        'saturacao'            => 'VARCHAR(50) NULL',
        'observacoes_exame'    => 'TEXT NULL',
        'hipotese_diagnostica' => 'TEXT NULL',
        'cid10'                => 'VARCHAR(50) NULL',
        'conduta'              => 'TEXT NULL',
        'orientacoes'          => 'TEXT NULL',
        'orientacoes_pos'      => 'TEXT NULL',
        'retorno_necessario'   => 'INT DEFAULT 0',
        'prazo_retorno'        => 'VARCHAR(100) NULL',
        'observacoes_finais'   => 'TEXT NULL',
        'criado_em'            => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
        'atualizado_em'        => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
    ]);

    /* CAMPOS */
    $queixa_principal        = trim($_POST['queixa_principal']        ?? '');
    $historico_doenca        = trim($_POST['historico_doenca']        ?? '');
    $antecedentes            = trim($_POST['antecedentes']            ?? '');
    $alergias                = trim($_POST['alergias']                ?? '');
    $medicamentos            = trim($_POST['medicamentos']            ?? '');
    $historico_familiar      = trim($_POST['historico_familiar']      ?? '');
    $habitos_vida            = trim($_POST['habitos_vida']            ?? '');
    $pressao_arterial        = trim($_POST['pressao_arterial']        ?? '');
    $frequencia_cardiaca     = trim($_POST['frequencia_cardiaca']     ?? '');
    $frequencia_respiratoria = trim($_POST['frequencia_respiratoria'] ?? '');
    $temperatura             = trim($_POST['temperatura']             ?? '');
    $peso                    = trim($_POST['peso']                    ?? '');
    $altura                  = trim($_POST['altura']                  ?? '');
    $saturacao               = trim($_POST['saturacao']               ?? '');
    $observacoes_exame       = trim($_POST['observacoes_exame']       ?? '');
    $hipotese_diagnostica    = trim($_POST['hipotese_diagnostica']    ?? '');
    $cid10                    = trim($_POST['cid10']                    ?? '');
    $conduta                  = trim($_POST['conduta']                  ?? '');
    $orientacoes              = trim($_POST['orientacoes']              ?? '');
    $orientacoes_pos        = trim($_POST['orientacoes_pos']        ?? '');
    $retorno_necessario     = intval($_POST['retorno_necessario']     ?? 0);
    $prazo_retorno          = trim($_POST['prazo_retorno']          ?? '');
    $observacoes_finais     = trim($_POST['observacoes_finais']     ?? '');

    /* UPSERT PRONTUÁRIO */
    $stmtCheck = $conn->prepare("SELECT id_prontuario FROM prontuario WHERE id_consulta = ?");
    $stmtCheck->bind_param("i", $id_consulta);
    $stmtCheck->execute();
    $resCheck = $stmtCheck->get_result();
    $existente = $resCheck ? $resCheck->fetch_assoc() : null;
    $stmtCheck->close();

    if ($existente) {
        $stmtUp = $conn->prepare("
            UPDATE prontuario SET
                queixa_principal         = ?,
                historico_doenca         = ?,
                antecedentes             = ?,
                alergias                 = ?,
                medicamentos             = ?,
                historico_familiar       = ?,
                habitos_vida             = ?,
                pressao_arterial         = ?,
                frequencia_cardiaca      = ?,
                frequencia_respiratoria  = ?,
                temperatura              = ?,
                peso                     = ?,
                altura                   = ?,
                saturacao                = ?,
                observacoes_exame        = ?,
                hipotese_diagnostica     = ?,
                cid10                    = ?,
                conduta                  = ?,
                orientacoes              = ?,
                orientacoes_pos          = ?,
                retorno_necessario       = ?,
                prazo_retorno            = ?,
                observacoes_finais       = ?,
                atualizado_em            = NOW()
            WHERE id_consulta = ?
        ");
        if (!$stmtUp) {
            ob_clean();
            echo json_encode(['erro' => 'Erro ao preparar atualização do prontuário: ' . $conn->error]);
            exit;
        }
        $stmtUp->bind_param(
            "ssssssssssssssssssssissi",
            $queixa_principal,
            $historico_doenca,
            $antecedentes,
            $alergias,
            $medicamentos,
            $historico_familiar,
            $habitos_vida,
            $pressao_arterial,
            $frequencia_cardiaca,
            $frequencia_respiratoria,
            $temperatura,
            $peso,
            $altura,
            $saturacao,
            $observacoes_exame,
            $hipotese_diagnostica,
            $cid10,
            $conduta,
            $orientacoes,
            $orientacoes_pos,
            $retorno_necessario,
            $prazo_retorno,
            $observacoes_finais,
            $id_consulta
        );
        $ok = $stmtUp->execute();
        if (!$ok) {
            $err = $stmtUp->error;
            $stmtUp->close();
            ob_clean();
            echo json_encode(['erro' => 'Erro ao gravar atualização no banco: ' . $err]);
            exit;
        }
        $stmtUp->close();
    } else {
        $stmtIns = $conn->prepare("
            INSERT INTO prontuario (
                id_consulta, id_paciente, id_medico,
                queixa_principal, historico_doenca, antecedentes,
                alergias, medicamentos, historico_familiar, habitos_vida,
                pressao_arterial, frequencia_cardiaca, frequencia_respiratoria,
                temperatura, peso, altura, saturacao, observacoes_exame,
                hipotese_diagnostica, cid10, conduta, orientacoes,
                orientacoes_pos, retorno_necessario, prazo_retorno, observacoes_finais,
                criado_em
            ) VALUES (
                ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                NOW()
            )
        ");
        if (!$stmtIns) {
            ob_clean();
            echo json_encode(['erro' => 'Erro ao preparar inserção do prontuário: ' . $conn->error]);
            exit;
        }
        $stmtIns->bind_param(
            "iiisssssssssssssssssssssss",
            $id_consulta,
            $consulta['id_paciente'],
            $id_medico,
            $queixa_principal,
            $historico_doenca,
            $antecedentes,
            $alergias,
            $medicamentos,
            $historico_familiar,
            $habitos_vida,
            $pressao_arterial,
            $frequencia_cardiaca,
            $frequencia_respiratoria,
            $temperatura,
            $peso,
            $altura,
            $saturacao,
            $observacoes_exame,
            $hipotese_diagnostica,
            $cid10,
            $conduta,
            $orientacoes,
            $orientacoes_pos,
            $retorno_necessario,
            $prazo_retorno,
            $observacoes_finais
        );
        $ok = $stmtIns->execute();
        if (!$ok) {
            $err = $stmtIns->error;
            $stmtIns->close();
            ob_clean();
            echo json_encode(['erro' => 'Erro ao gravar prontuário no banco de dados: ' . $err]);
            exit;
        }
        $stmtIns->close();
    }

    /* MUDA STATUS PARA 'em_atendimento' SE ESTIVER AGUARDANDO */
    if ($consulta['status'] === 'aguardando_atendimento') {
        try {
            $conn->query("UPDATE consulta SET status = 'em_atendimento' WHERE id_consulta = " . intval($id_consulta));
        } catch (Throwable $eSt) {}
    }

    ob_clean();
    echo json_encode(['sucesso' => true, 'mensagem' => 'Prontuário salvo com sucesso!'], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $eGlobal) {
    ob_clean();
    echo json_encode(['erro' => 'Erro ao salvar prontuário: ' . $eGlobal->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

