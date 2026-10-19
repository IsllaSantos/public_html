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
    require_once('../config/status_consulta.php');
    garantirStatusConsulta($conn);

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['id_usuario']) || !isset($_SESSION['perfil']) || $_SESSION['perfil'] !== 'medico') {
        ob_clean();
        echo json_encode(['erro' => 'Sessão expirada ou acesso não autorizado. Por favor, faça login novamente.']);
        exit;
    }

    if (!isset($conn) || $conn->connect_error) {
        ob_clean();
        echo json_encode(['erro' => 'Erro de conexão com o banco de dados.']);
        exit;
    }

    $id_medico   = intval($_SESSION['id_usuario']);
    $id_paciente = intval($_GET['id_paciente'] ?? 0);
    $id_consulta = intval($_GET['id_consulta'] ?? 0);

    /* Se o id_paciente não veio mas o id_consulta veio, busca o paciente da consulta */
    if ($id_paciente <= 0 && $id_consulta > 0) {
        try {
            $stmtC = $conn->prepare("SELECT id_paciente FROM consulta WHERE id_consulta = ?");
            if ($stmtC) {
                $stmtC->bind_param("i", $id_consulta);
                $stmtC->execute();
                $resC = $stmtC->get_result();
                if ($resC && $rowC = $resC->fetch_assoc()) {
                    $id_paciente = intval($rowC['id_paciente']);
                }
                $stmtC->close();
            }
        } catch (Throwable $eC) {}
    }

    if ($id_paciente <= 0) {
        ob_clean();
        echo json_encode(['erro' => 'Paciente não especificado.']);
        exit;
    }

    /* Verificação e migração dinâmica de tabelas/colunas */
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

    $tableProntuario = true;
    $tableReceita = true;
    $tableExames = true;

    /* =========================
       DADOS DO PACIENTE
    ========================= */
    $paciente = null;
    try {
        $stmt = $conn->prepare("
            SELECT
                id_usuario, nome, cpf, rg,
                data_nascimento, sexo,
                telefone, celular, email,
                convenio, numero_carteirinha
            FROM usuarios
            WHERE id_usuario = ?
        ");
        if ($stmt) {
            $stmt->bind_param("i", $id_paciente);
            $stmt->execute();
            $resP = $stmt->get_result();
            if ($resP) {
                $paciente = $resP->fetch_assoc();
            }
            $stmt->close();
        }
    } catch (Throwable $eP) {
        try {
            $stmtFallback = $conn->prepare("
                SELECT id_usuario, nome, cpf, email FROM usuarios WHERE id_usuario = ?
            ");
            if ($stmtFallback) {
                $stmtFallback->bind_param("i", $id_paciente);
                $stmtFallback->execute();
                $resPF = $stmtFallback->get_result();
                if ($resPF) {
                    $paciente = $resPF->fetch_assoc();
                }
                $stmtFallback->close();
            }
        } catch (Throwable $eP2) {}
    }

    if (!$paciente) {
        $paciente = [
            'id_usuario'         => $id_paciente,
            'nome'               => 'Paciente não localizado',
            'cpf'                => '—',
            'rg'                 => '—',
            'data_nascimento'    => null,
            'sexo'               => '—',
            'telefone'           => '—',
            'celular'            => '—',
            'email'              => '—',
            'convenio'           => '—',
            'numero_carteirinha' => '—'
        ];
    }

    /* =========================
       DADOS DA CONSULTA ATUAL
    ========================= */
    $consultaAtual = null;

    if ($id_consulta > 0) {
        try {
            $stmt = $conn->prepare("
                SELECT
                    c.id_consulta,
                    c.data_consulta,
                    c.hora_consulta,
                    c.tipo_consulta,
                    c.motivo_consulta,
                    c.observacoes,
                    c.status,
                    c.chegada_em
                FROM consulta c
                WHERE c.id_consulta = ?
            ");
            if ($stmt) {
                $stmt->bind_param("i", $id_consulta);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res) {
                    $consultaAtual = $res->fetch_assoc();
                }
                $stmt->close();
            }
        } catch (Throwable $eC) {
            try {
                $stmtF = $conn->prepare("
                    SELECT id_consulta, data_consulta, hora_consulta, motivo_consulta, status
                    FROM consulta WHERE id_consulta = ?
                ");
                if ($stmtF) {
                    $stmtF->bind_param("i", $id_consulta);
                    $stmtF->execute();
                    $resF = $stmtF->get_result();
                    if ($resF) {
                        $consultaAtual = $resF->fetch_assoc();
                    }
                    $stmtF->close();
                }
            } catch (Throwable $eF) {}
        }

        if ($consultaAtual && $consultaAtual['status'] === 'aguardando_atendimento' && isset($_GET['iniciar'])) {
            try {
                $stmtUpd = $conn->prepare("UPDATE consulta SET status = 'em_atendimento' WHERE id_consulta = ?");
                if ($stmtUpd) {
                    $stmtUpd->bind_param("i", $id_consulta);
                    $stmtUpd->execute();
                    $stmtUpd->close();
                    $consultaAtual['status'] = 'em_atendimento';
                }
            } catch (Throwable $eU) {}
        }
    }

    /* =========================
       PRONTUÁRIO DA CONSULTA ATUAL
    ========================= */
    $prontuarioAtual = null;

    if ($id_consulta > 0 && $tableProntuario) {
        try {
            $stmt = $conn->prepare("
                SELECT
                    queixa_principal, historico_doenca, antecedentes,
                    alergias, medicamentos, historico_familiar, habitos_vida,
                    pressao_arterial, frequencia_cardiaca, frequencia_respiratoria,
                    temperatura, peso, altura, saturacao, observacoes_exame,
                    hipotese_diagnostica, cid10, conduta, orientacoes,
                    orientacoes_pos, retorno_necessario, prazo_retorno, observacoes_finais
                FROM prontuario
                WHERE id_consulta = ?
            ");
            if ($stmt) {
                $stmt->bind_param("i", $id_consulta);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res) {
                    $prontuarioAtual = $res->fetch_assoc();
                }
                $stmt->close();
            }
        } catch (Throwable $ePront) {
            $prontuarioAtual = null;
        }
    }

    /* =========================
       RECEITA DA CONSULTA ATUAL
    ========================= */
    $receitaAtual = null;

    if ($id_consulta > 0 && $tableReceita) {
        try {
            $stmt = $conn->prepare("SELECT medicamentos FROM receita WHERE id_consulta = ?");
            if ($stmt) {
                $stmt->bind_param("i", $id_consulta);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res) {
                    $receitaAtual = $res->fetch_assoc();
                }
                $stmt->close();
            }
        } catch (Throwable $eR) {
            $receitaAtual = null;
        }
    }

    /* =========================
       EXAMES DA CONSULTA ATUAL
    ========================= */
    $examesAtual = null;

    if ($id_consulta > 0 && $tableExames) {
        try {
            $stmt = $conn->prepare("SELECT exames, observacoes FROM solicitacao_exame WHERE id_consulta = ?");
            if ($stmt) {
                $stmt->bind_param("i", $id_consulta);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res) {
                    $examesAtual = $res->fetch_assoc();
                }
                $stmt->close();
            }
        } catch (Throwable $eE) {
            $examesAtual = null;
        }
    }

    /* =========================
       HISTÓRICO DE CONSULTAS
    ========================= */
    $historico = [];
    $buscouHistorico = false;

    if ($tableProntuario) {
        try {
            $sqlH = "
                SELECT
                    c.id_consulta,
                    c.data_consulta,
                    c.hora_consulta,
                    c.motivo_consulta,
                    c.status,
                    p.queixa_principal,
                    p.historico_doenca,
                    p.antecedentes,
                    p.alergias,
                    p.medicamentos,
                    p.historico_familiar,
                    p.habitos_vida,
                    p.pressao_arterial,
                    p.frequencia_cardiaca,
                    p.frequencia_respiratoria,
                    p.temperatura,
                    p.peso,
                    p.altura,
                    p.saturacao,
                    p.observacoes_exame,
                    p.hipotese_diagnostica,
                    p.cid10,
                    p.conduta,
                    p.orientacoes,
                    p.orientacoes_pos,
                    p.retorno_necessario,
                    p.prazo_retorno,
                    p.observacoes_finais
                FROM consulta c
                LEFT JOIN prontuario p ON p.id_consulta = c.id_consulta
                WHERE c.id_paciente = ?
                AND c.status NOT IN ('cancelada')
                ORDER BY c.data_consulta DESC, c.hora_consulta DESC
                LIMIT 50
            ";
            $stmtH = $conn->prepare($sqlH);
            if ($stmtH) {
                $stmtH->bind_param("i", $id_paciente);
                $stmtH->execute();
                $res = $stmtH->get_result();
                if ($res) {
                    while ($row = $res->fetch_assoc()) {
                        $row['receita'] = null;
                        $row['exames_solicitados'] = null;
                        $row['observacoes_exames'] = null;

                        if ($tableReceita) {
                            try {
                                $stmtR = $conn->prepare("SELECT medicamentos FROM receita WHERE id_consulta = ?");
                                if ($stmtR) {
                                    $stmtR->bind_param("i", $row['id_consulta']);
                                    $stmtR->execute();
                                    $resR = $stmtR->get_result();
                                    if ($resR && $rec = $resR->fetch_assoc()) {
                                        $row['receita'] = $rec['medicamentos'];
                                    }
                                    $stmtR->close();
                                }
                            } catch (Throwable $eR2) {}
                        }

                        if ($tableExames) {
                            try {
                                $stmtE = $conn->prepare("SELECT exames, observacoes FROM solicitacao_exame WHERE id_consulta = ?");
                                if ($stmtE) {
                                    $stmtE->bind_param("i", $row['id_consulta']);
                                    $stmtE->execute();
                                    $resE = $stmtE->get_result();
                                    if ($resE && $exm = $resE->fetch_assoc()) {
                                        $row['exames_solicitados']   = $exm['exames'];
                                        $row['observacoes_exames']   = $exm['observacoes'];
                                    }
                                    $stmtE->close();
                                }
                            } catch (Throwable $eE2) {}
                        }

                        $historico[] = $row;
                    }
                }
                $stmtH->close();
                $buscouHistorico = true;
            }
        } catch (Throwable $eHist) {
            $buscouHistorico = false;
        }
    }

    if (!$buscouHistorico) {
        try {
            $sqlHSimple = "
                SELECT
                    c.id_consulta,
                    c.data_consulta,
                    c.hora_consulta,
                    c.motivo_consulta,
                    c.status
                FROM consulta c
                WHERE c.id_paciente = ?
                AND c.status NOT IN ('cancelada')
                ORDER BY c.data_consulta DESC, c.hora_consulta DESC
                LIMIT 50
            ";
            $stmtHSimple = $conn->prepare($sqlHSimple);
            if ($stmtHSimple) {
                $stmtHSimple->bind_param("i", $id_paciente);
                $stmtHSimple->execute();
                $resSimple = $stmtHSimple->get_result();
                if ($resSimple) {
                    while ($row = $resSimple->fetch_assoc()) {
                        $row['receita'] = null;
                        $row['exames_solicitados'] = null;
                        $row['observacoes_exames'] = null;
                        $historico[] = $row;
                    }
                }
                $stmtHSimple->close();
            }
        } catch (Throwable $eHistSimple) {}
    }

    /* =========================
       ANEXOS (arquivos compartilhados pelo paciente)
       Apenas arquivos vinculados a consultas deste médico.
    ========================= */
    $anexosAtual = [];
    try {
        @$conn->query("CREATE TABLE IF NOT EXISTS arquivo_paciente (
            id_arquivo INT AUTO_INCREMENT PRIMARY KEY, id_paciente INT NOT NULL, id_consulta INT NULL,
            titulo VARCHAR(150) NOT NULL, nome_original VARCHAR(255) NOT NULL, nome_armazenado VARCHAR(100) NOT NULL,
            mime VARCHAR(100) NULL, tamanho INT NOT NULL DEFAULT 0,
            criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $stmtA = $conn->prepare("
            SELECT a.id_arquivo, a.id_consulta, a.titulo, a.nome_original, a.tamanho, a.criado_em
            FROM arquivo_paciente a
            INNER JOIN consulta c ON c.id_consulta = a.id_consulta
            WHERE a.id_paciente = ? AND c.id_medico = ?
            ORDER BY a.criado_em DESC
        ");
        if ($stmtA) {
            $stmtA->bind_param("ii", $id_paciente, $id_medico);
            $stmtA->execute();
            $resA = $stmtA->get_result();
            $porConsulta = [];
            while ($resA && $a = $resA->fetch_assoc()) {
                $porConsulta[intval($a['id_consulta'])][] = $a;
            }
            $stmtA->close();

            foreach ($historico as &$hh) {
                $hh['anexos'] = $porConsulta[intval($hh['id_consulta'])] ?? [];
            }
            unset($hh);

            if ($id_consulta > 0) {
                $anexosAtual = $porConsulta[$id_consulta] ?? [];
            }
        }
    } catch (Throwable $eAx) {}

    /* =========================
       RESPOSTA JSON
    ========================= */
    ob_clean();
    echo json_encode([
        'paciente'        => $paciente,
        'consulta_atual'  => $consultaAtual,
        'prontuario'      => $prontuarioAtual,
        'receita'         => $receitaAtual,
        'exames'          => $examesAtual,
        'anexos'          => $anexosAtual,
        'historico'       => $historico
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $eGlobal) {
    ob_clean();
    echo json_encode([
        'erro' => 'Erro interno no servidor: ' . $eGlobal->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

