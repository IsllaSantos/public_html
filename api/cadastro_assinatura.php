<?php

require_once('../config/auth.php');
require_once('../config/database.php');
require_once('../config/logs.php');
require_once('../config/assinaturas.php');

verificarPerfil('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../admin.php?aba=financeiro");
    exit;
}

$id_clinica = intval($_POST['id_clinica'] ?? 0);
$id_plano = intval($_POST['id_plano'] ?? 0);
$data_inicio = trim($_POST['data_inicio'] ?? '');
$data_vencimento = trim($_POST['data_vencimento'] ?? '');
$valor = floatval($_POST['valor'] ?? 0);

if (
    $id_clinica <= 0 ||
    $id_plano <= 0 ||
    empty($data_inicio) ||
    empty($data_vencimento) ||
    $valor <= 0
) {
    $_SESSION['mensagem_sucesso'] = "Preencha todos os campos obrigatórios da assinatura.";
    header("Location: ../admin.php?aba=financeiro");
    exit;
}

try {
    /* UMA ASSINATURA ATIVA POR CLÍNICA */
    $chk = $conn->prepare("SELECT 1 FROM assinaturas WHERE id_clinica = ? AND status = 'ativa' LIMIT 1");
    $chk->bind_param("i", $id_clinica);
    $chk->execute();
    $existe = $chk->get_result()->num_rows > 0;
    $chk->close();

    if ($existe) {
        $_SESSION['mensagem_sucesso'] = "Esta clínica já possui uma assinatura ativa. Edite ou cancele a existente.";
        header("Location: ../admin.php?aba=financeiro");
        exit;
    }

    $sql = "
    INSERT INTO assinaturas
    (
        id_clinica,
        id_plano,
        data_inicio,
        data_vencimento,
        valor,
        status
    )
    VALUES (?, ?, ?, ?, ?, 'ativa')
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "iissd",
        $id_clinica,
        $id_plano,
        $data_inicio,
        $data_vencimento,
        $valor
    );

    if ($stmt->execute()) {
        $_SESSION['mensagem_sucesso'] = "Assinatura cadastrada com sucesso!";
        registrarLog($conn, "Cadastro de assinatura (clínica #" . $id_clinica . ")");
    } else {
        $_SESSION['mensagem_sucesso'] = "Erro ao cadastrar assinatura: " . $stmt->error;
    }
    $stmt->close();

} catch (Throwable $e) {
    $_SESSION['mensagem_sucesso'] = "Erro ao cadastrar assinatura: " . $e->getMessage();
}

header("Location: ../admin.php?aba=financeiro");
exit;
