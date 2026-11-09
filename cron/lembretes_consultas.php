<?php

/**
 * Lembretes de consulta por e-mail.
 *
 * Envia, para pacientes com consulta "agendada" no dia seguinte, um e-mail de lembrete
 * (uma única vez por consulta). Execução somente via linha de comando (cron).
 *
 * Exemplo de agendamento diário às 08:00 no painel da hospedagem:
 *   0 8 * * *  php /caminho/para/public_html/cron/lembretes_consultas.php
 *
 * Opções:
 *   --dias=N   antecedência em dias (padrão 1)
 *   --teste    apenas lista, sem enviar nem marcar como enviado
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Acesso negado.');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/seguranca.php';
require_once __DIR__ . '/../config/email.php';

garantirEsquemaSeguranca($conn);

$dias  = 1;
$teste = false;
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--dias=(\d+)$/', $arg, $m)) {
        $dias = max(0, min(30, (int) $m[1]));
    } elseif ($arg === '--teste') {
        $teste = true;
    }
}

$sql = "
    SELECT c.id_consulta, c.data_consulta, c.hora_consulta,
           p.nome AS paciente, p.email AS email_paciente,
           m.nome AS medico
    FROM consulta c
    INNER JOIN usuarios p ON p.id_usuario = c.id_paciente
    INNER JOIN usuarios m ON m.id_usuario = c.id_medico
    WHERE c.status = 'agendada'
      AND c.data_consulta = (CURDATE() + INTERVAL ? DAY)
      AND c.lembrete_enviado_em IS NULL
      AND p.status = 'ativo'
      AND p.email IS NOT NULL AND p.email <> ''
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $dias);
$stmt->execute();
$res = $stmt->get_result();

$enviados = 0;
$falhas   = 0;
$marcar   = $conn->prepare("UPDATE consulta SET lembrete_enviado_em = NOW() WHERE id_consulta = ?");
$urlBase  = urlBaseNyes();

while ($c = $res->fetch_assoc()) {
    $data = date('d/m/Y', strtotime($c['data_consulta']));
    $hora = substr($c['hora_consulta'], 0, 5);

    if ($teste) {
        echo "[teste] {$c['email_paciente']} - {$data} {$hora} com {$c['medico']}\n";
        continue;
    }

    $corpo = '<p>Olá, <strong>' . htmlspecialchars($c['paciente']) . '</strong>.</p>'
        . '<p>Este é um lembrete da sua consulta:</p>'
        . '<p style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:14px;">'
        . '<strong>Data:</strong> ' . $data . '<br>'
        . '<strong>Horário:</strong> ' . $hora . '<br>'
        . '<strong>Profissional:</strong> ' . htmlspecialchars($c['medico']) . '</p>'
        . '<p>Chegue com alguns minutos de antecedência. Se não puder comparecer, avise a clínica.</p>'
        . ($urlBase !== '' ? '<p><a href="' . htmlspecialchars($urlBase) . '/login.php">Acessar o NyesClinic</a></p>' : '');

    if (enviarEmailNyes($c['email_paciente'], 'Lembrete de consulta - ' . $data . ' às ' . $hora, $corpo)) {
        $id = (int) $c['id_consulta'];
        $marcar->bind_param("i", $id);
        $marcar->execute();
        $enviados++;
    } else {
        $falhas++;
    }
}

echo date('Y-m-d H:i:s') . " - lembretes enviados: {$enviados}, falhas: {$falhas}\n";

