<?php

/**
 * Garante que a tabela `consulta` aceite os status completos sem truncamento,
 * corrige colunas necessárias (como chegada_em) e atualiza registros antigos.
 */
function garantirStatusConsulta($conn) {
    if (!$conn || $conn->connect_error) return;

    // Evita rodar 4 queries (incluindo UPDATE/ALTER) a cada requisição:
    // executa no máximo uma vez a cada 6 horas (marcador em arquivo temporário).
    $marcador = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'technyes_status_consulta_' . md5(__FILE__) . '.flag';
    if (is_file($marcador) && (time() - @filemtime($marcador)) < 6 * 3600) {
        return;
    }
    @touch($marcador);

    try {
        // 1. Verifica a coluna 'status'
        $res = @$conn->query("SHOW COLUMNS FROM `consulta` LIKE 'status'");
        if ($res && $col = $res->fetch_assoc()) {
            $tipo = strtolower($col['Type']);
            $precisaAlterar = false;

            if (strpos($tipo, 'enum') === 0) {
                $precisaAlterar = true;
            } elseif (preg_match('/varchar\((\d+)\)/', $tipo, $m)) {
                if (intval($m[1]) < 40) {
                    $precisaAlterar = true;
                }
            }

            if ($precisaAlterar) {
                @$conn->query("ALTER TABLE `consulta` MODIFY `status` VARCHAR(50) NOT NULL DEFAULT 'agendada'");
            }
        }

        // 2. Corrige registros que foram truncados em VARCHAR(20)
        @$conn->query("UPDATE `consulta` SET `status` = 'aguardando_atendimento' WHERE `status` = 'aguardando_atendimen' OR `status` = 'aguardando'");

        // 3. Garante colunas de data/hora de atendimento
        $resCols = @$conn->query("SHOW COLUMNS FROM `consulta`");
        $colsExistentes = [];
        if ($resCols) {
            while ($c = $resCols->fetch_assoc()) {
                $colsExistentes[] = strtolower($c['Field']);
            }
        }

        if (!in_array('chegada_em', $colsExistentes)) {
            @$conn->query("ALTER TABLE `consulta` ADD COLUMN `chegada_em` DATETIME NULL AFTER `status`");
        }
        if (!in_array('fim_atendimento', $colsExistentes)) {
            @$conn->query("ALTER TABLE `consulta` ADD COLUMN `fim_atendimento` DATETIME NULL AFTER `chegada_em`");
        }

    } catch (Throwable $e) {}
}
