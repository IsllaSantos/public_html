<?php

require_once('../config/auth.php');
require_once('../config/database.php');

verificarPerfil('admin');


/* VERIFICA SE RECEBEU O ID */

$id_clinica = intval($_POST['id_clinica'] ?? 0);

if ($id_clinica <= 0) {
    $_SESSION['mensagem_sucesso'] = "Clínica inválida.";
    header("Location: ../admin.php?aba=clinicas");
    exit;
}

/* RECEBE OS DADOS */
$nome = trim($_POST['nome'] ?? '');
$cnpj = preg_replace('/\D/', '', trim($_POST['cnpj'] ?? ''));
$telefone = preg_replace('/\D/', '', trim($_POST['telefone'] ?? ''));
$email = trim($_POST['email'] ?? '');
$cep = preg_replace('/\D/', '', trim($_POST['cep'] ?? ''));
$endereco = trim($_POST['endereco'] ?? '');
$numero = trim($_POST['numero'] ?? '');
$complemento = trim($_POST['complemento'] ?? '');
$bairro = trim($_POST['bairro'] ?? '');
$cidade = trim($_POST['cidade'] ?? '');
$estado = trim($_POST['estado'] ?? '');
$status = trim($_POST['status'] ?? 'ativa');

/* VALIDAÇÃO */
if (empty($nome) || empty($cnpj)) {
    $_SESSION['mensagem_sucesso'] = "Preencha todos os campos obrigatórios da clínica.";
    header("Location: ../editar_clinica.php?id=" . $id_clinica);
    exit;
}

/* VALIDA STATUS */
if ($status !== 'ativa' && $status !== 'inativa') {
    $_SESSION['mensagem_sucesso'] = "Status da clínica inválido.";
    header("Location: ../editar_clinica.php?id=" . $id_clinica);
    exit;
}

try {
    /* VERIFICA CNPJ (Ignorando a própria clínica) */
    $sql = "
    SELECT id_clinica
    FROM clinica
    WHERE cnpj = ?
    AND id_clinica <> ?
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $cnpj, $id_clinica);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows > 0) {
        $stmt->close();
        $_SESSION['mensagem_sucesso'] = "Este CNPJ já está cadastrado em outra clínica.";
        header("Location: ../editar_clinica.php?id=" . $id_clinica);
        exit;
    }
    $stmt->close();

    /* ATUALIZA */
    $sql = "
    UPDATE clinica
    SET
        nome = ?,
        cnpj = ?,
        telefone = ?,
        email = ?,
        cep = ?,
        endereco = ?,
        numero = ?,
        complemento = ?,
        bairro = ?,
        cidade = ?,
        estado = ?,
        status = ?
    WHERE id_clinica = ?
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "ssssssssssssi",
        $nome,
        $cnpj,
        $telefone,
        $email,
        $cep,
        $endereco,
        $numero,
        $complemento,
        $bairro,
        $cidade,
        $estado,
        $status,
        $id_clinica
    );

    $stmt->execute();
    $stmt->close();

    $_SESSION['mensagem_sucesso'] = "Clínica \"" . $nome . "\" atualizada com sucesso!";
    header("Location: ../admin.php?aba=clinicas");
    exit;

} catch (Throwable $e) {
    $_SESSION['mensagem_sucesso'] = "Erro ao editar clínica: " . $e->getMessage();
    header("Location: ../editar_clinica.php?id=" . $id_clinica);
    exit;
}