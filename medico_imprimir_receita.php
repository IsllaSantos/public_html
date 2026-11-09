<?php

/*
 * Impressão isolada da receita / exames de uma consulta (inclusive finalizada).
 * Disponível apenas para o médico responsável pela consulta.
 */

require_once('config/auth.php');
require_once('config/database.php');

verificarPerfil('medico');

$id_medico   = intval($_SESSION['id_usuario']);
$id_consulta = intval($_GET['id_consulta'] ?? 0);

$stmt = $conn->prepare("
    SELECT c.id_consulta, c.data_consulta, c.hora_consulta,
           p.nome AS paciente, p.cpf,
           m.nome AS medico, m.crm, m.uf_crm, m.especialidade
    FROM consulta c
    INNER JOIN usuarios p ON p.id_usuario = c.id_paciente
    INNER JOIN usuarios m ON m.id_usuario = c.id_medico
    WHERE c.id_consulta = ? AND c.id_medico = ?
");
$stmt->bind_param("ii", $id_consulta, $id_medico);
$stmt->execute();
$c = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$c) {
    http_response_code(404);
    exit('Consulta não encontrada.');
}

function buscarLinhaRec($conn, $sql, $id)
{
    $st = @$conn->prepare($sql);
    if (!$st) return null;
    $st->bind_param("i", $id);
    $st->execute();
    $r = $st->get_result();
    $row = $r ? $r->fetch_assoc() : null;
    $st->close();
    return $row;
}

$receita = buscarLinhaRec($conn, "SELECT medicamentos FROM receita WHERE id_consulta = ? LIMIT 1", $id_consulta);
$exames  = buscarLinhaRec($conn, "SELECT exames, observacoes FROM solicitacao_exame WHERE id_consulta = ? LIMIT 1", $id_consulta);

$medicamentos = trim($receita['medicamentos'] ?? '');
$examesTxt    = trim($exames['exames'] ?? '');
$examesObs    = trim($exames['observacoes'] ?? '');

function blocoRec($titulo, $texto)
{
    echo '<div class="bloco"><h4>' . htmlspecialchars($titulo) . '</h4><div class="txt">' . nl2br(htmlspecialchars($texto)) . '</div></div>';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Receituário - <?= htmlspecialchars($c['paciente']) ?></title>
<style>
    body { font-family: 'Segoe UI', Arial, sans-serif; color: #1e293b; max-width: 800px; margin: 30px auto; padding: 0 20px; }
    .acoes { margin-bottom: 20px; }
    .cab { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #0f172a; padding-bottom: 14px; margin-bottom: 20px; }
    .titulo { font-size: 1.5rem; font-weight: 800; color: #0f172a; }
    .meta { font-size: .85rem; color: #64748b; }
    .box { background: #f8fafc; padding: 12px; border-radius: 6px; margin-bottom: 18px; font-size: .95rem; }
    .bloco { margin-bottom: 24px; }
    .bloco h4 { font-size: .95rem; text-transform: uppercase; letter-spacing: .5px; border-bottom: 1px dashed #cbd5e1; padding-bottom: 4px; margin: 0 0 8px; }
    .txt { font-size: .95rem; line-height: 1.7; }
    .rodape { margin-top: 60px; display: flex; justify-content: space-between; align-items: flex-end; border-top: 1px solid #e2e8f0; padding-top: 20px; }
    .assin { text-align: center; width: 280px; border-top: 1px solid #0f172a; padding-top: 6px; font-size: .85rem; font-weight: 600; }
    @media print { .acoes { display: none; } body { margin: 0; max-width: none; } }
</style>
</head>
<body>

<div class="acoes"><button onclick="window.print()">🖨 Imprimir / Salvar PDF</button></div>

<div class="cab">
    <div>
        <div class="titulo">NyesClinic</div>
        <div class="meta">Receituário e Pedidos de Exames</div>
    </div>
    <div class="meta">Data da consulta: <?= date('d/m/Y', strtotime($c['data_consulta'])) ?></div>
</div>

<div class="box">
    <strong>Paciente:</strong> <?= htmlspecialchars($c['paciente']) ?>
    <?php if (!empty($c['cpf'])): ?> | <strong>CPF:</strong> <?= htmlspecialchars($c['cpf']) ?><?php endif; ?>
</div>

<?php
if ($medicamentos !== '') blocoRec('💊 Prescrição Medicamentosa', $medicamentos);
if ($examesTxt !== '') {
    blocoRec('🔎 Exames Solicitados', $examesTxt);
    if ($examesObs !== '') blocoRec('Observações', $examesObs);
}
if ($medicamentos === '' && $examesTxt === '') {
    echo '<p style="color:#888;text-align:center;padding:24px;"><em>Nenhuma prescrição ou exame registrado nesta consulta.</em></p>';
}
?>

<div class="rodape">
    <div class="meta">Emitido em <?= date('d/m/Y H:i') ?> via NyesClinic.</div>
    <div class="assin">
        Dr(a). <?= htmlspecialchars($c['medico']) ?><br>
        <?= htmlspecialchars($c['especialidade'] ?? '') ?>
        <?php if (!empty($c['crm'])): ?> — CRM <?= htmlspecialchars($c['crm']) ?><?= !empty($c['uf_crm']) ? '-' . htmlspecialchars($c['uf_crm']) : '' ?><?php endif; ?>
        <br>Assinatura / Carimbo
    </div>
</div>

</body>
</html>

