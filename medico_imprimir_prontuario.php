<?php

require_once('config/auth.php');
require_once('config/database.php');

verificarPerfil('medico');

$id_medico = intval($_SESSION['id_usuario']);
$id_consulta = intval($_GET['id_consulta'] ?? 0);

$stmt = $conn->prepare("
    SELECT c.id_consulta, c.data_consulta, c.hora_consulta, c.motivo_consulta, c.status,
           p.nome AS paciente, p.cpf, m.nome AS medico
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

function buscarLinha($conn, $sql, $id)
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

$pront = buscarLinha($conn, "SELECT * FROM prontuario WHERE id_consulta = ? LIMIT 1", $id_consulta);
$receita = buscarLinha($conn, "SELECT medicamentos FROM receita WHERE id_consulta = ? LIMIT 1", $id_consulta);
$exames = buscarLinha($conn, "SELECT exames, observacoes FROM solicitacao_exame WHERE id_consulta = ? LIMIT 1", $id_consulta);

$adendos = [];
$st = @$conn->prepare("SELECT texto, criado_em FROM prontuario_adendo WHERE id_consulta = ? ORDER BY criado_em");
if ($st) {
    $st->bind_param("i", $id_consulta);
    $st->execute();
    $res = $st->get_result();
    while ($res && ($a = $res->fetch_assoc())) $adendos[] = $a;
    $st->close();
}

function campoImp($rotulo, $valor)
{
    $valor = trim((string)$valor);
    if ($valor === '') return;
    echo '<div class="campo"><strong>' . htmlspecialchars($rotulo) . '</strong><p>' . nl2br(htmlspecialchars($valor)) . '</p></div>';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Prontuário - <?= htmlspecialchars($c['paciente']) ?></title>
<style>
    body { font-family: Arial, sans-serif; color: #222; max-width: 800px; margin: 30px auto; padding: 0 20px; }
    h1 { font-size: 1.4rem; border-bottom: 2px solid #b8962e; padding-bottom: 8px; }
    h2 { font-size: 1.05rem; margin-top: 26px; color: #8a6d1d; }
    .cab { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 20px; font-size: .92rem; }
    .campo { margin: 10px 0; }
    .campo p { margin: 3px 0 0; white-space: normal; }
    .adendo { border-left: 3px solid #b8962e; padding-left: 10px; margin: 10px 0; }
    .acoes { margin-bottom: 20px; }
    @media print { .acoes { display: none; } }
</style>
</head>
<body>

<div class="acoes"><button onclick="window.print()">🖨 Imprimir / Salvar PDF</button></div>

<h1>NyesClinic — Prontuário</h1>

<div class="cab">
    <div><strong>Paciente:</strong> <?= htmlspecialchars($c['paciente']) ?></div>
    <div><strong>CPF:</strong> <?= htmlspecialchars($c['cpf'] ?? '') ?></div>
    <div><strong>Médico:</strong> <?= htmlspecialchars($c['medico']) ?></div>
    <div><strong>Data:</strong> <?= date('d/m/Y', strtotime($c['data_consulta'])) ?> às <?= substr($c['hora_consulta'], 0, 5) ?></div>
    <div><strong>Motivo:</strong> <?= htmlspecialchars($c['motivo_consulta'] ?? '') ?></div>
    <div><strong>Status:</strong> <?= htmlspecialchars($c['status']) ?></div>
</div>

<?php if ($pront): ?>
    <h2>Anamnese</h2>
    <?php
    campoImp('Queixa principal', $pront['queixa_principal'] ?? '');
    campoImp('História da doença atual', $pront['historico_doenca'] ?? '');
    campoImp('Antecedentes', $pront['antecedentes'] ?? '');
    campoImp('Alergias', $pront['alergias'] ?? '');
    campoImp('Medicamentos em uso', $pront['medicamentos'] ?? '');
    campoImp('Histórico familiar', $pront['historico_familiar'] ?? '');
    campoImp('Hábitos de vida', $pront['habitos_vida'] ?? '');
    ?>

    <h2>Exame físico</h2>
    <?php
    campoImp('Pressão arterial', $pront['pressao_arterial'] ?? '');
    campoImp('Frequência cardíaca', $pront['frequencia_cardiaca'] ?? '');
    campoImp('Frequência respiratória', $pront['frequencia_respiratoria'] ?? '');
    campoImp('Temperatura', $pront['temperatura'] ?? '');
    campoImp('Peso', $pront['peso'] ?? '');
    campoImp('Altura', $pront['altura'] ?? '');
    campoImp('Saturação', $pront['saturacao'] ?? '');
    campoImp('Observações do exame', $pront['observacoes_exame'] ?? '');
    ?>

    <h2>Diagnóstico e conduta</h2>
    <?php
    campoImp('Hipótese diagnóstica', $pront['hipotese_diagnostica'] ?? '');
    campoImp('CID-10', $pront['cid10'] ?? '');
    campoImp('Conduta', $pront['conduta'] ?? '');
    campoImp('Orientações', $pront['orientacoes'] ?? '');
    campoImp('Orientações pós-atendimento', $pront['orientacoes_pos'] ?? '');
    campoImp('Prazo de retorno', $pront['prazo_retorno'] ?? '');
    campoImp('Observações finais', $pront['observacoes_finais'] ?? '');
    ?>
<?php else: ?>
    <p><em>Sem prontuário registrado para esta consulta.</em></p>
<?php endif; ?>

<?php if ($receita && trim($receita['medicamentos'] ?? '') !== ''): ?>
    <h2>Receita</h2>
    <?php campoImp('Medicamentos', $receita['medicamentos']); ?>
<?php endif; ?>

<?php if ($exames && trim($exames['exames'] ?? '') !== ''): ?>
    <h2>Exames solicitados</h2>
    <?php campoImp('Exames', $exames['exames']); campoImp('Observações', $exames['observacoes'] ?? ''); ?>
<?php endif; ?>

<?php if (!empty($adendos)): ?>
    <h2>Adendos</h2>
    <?php foreach ($adendos as $a): ?>
        <div class="adendo">
            <small><?= date('d/m/Y H:i', strtotime($a['criado_em'])) ?></small>
            <p><?= nl2br(htmlspecialchars($a['texto'])) ?></p>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

</body>
</html>

