<?php

require_once('../config/auth.php');
require_once('../config/database.php');

verificarPerfil('admin');


/* VERIFICA SE RECEBEU O ID */

$id_clinica = intval($_POST['id_clinica'] ?? 0);

if ($id_clinica <= 0) {
    die("Clínica inválida.");
}


/* RECEBE OS DADOS */

$nome = trim($_POST['nome'] ?? '');
$cnpj = trim($_POST['cnpj'] ?? '');
$telefone = trim($_POST['telefone'] ?? '');
$email = trim($_POST['email'] ?? '');
$cep = trim($_POST['cep'] ?? '');
$endereco = trim($_POST['endereco'] ?? '');
$numero = trim($_POST['numero'] ?? '');
$complemento = trim($_POST['complemento'] ?? '');
$bairro = trim($_POST['bairro'] ?? '');
$cidade = trim($_POST['cidade'] ?? '');
$estado = trim($_POST['estado'] ?? '');
$status = trim($_POST['status'] ?? 'ativa');


/* VALIDAÇÃO */

if (
    empty($nome) ||
    empty($cnpj)
) {
    die("Preencha os campos obrigatórios.");
}


/* VALIDA STATUS */

if (
    $status !== 'ativa' &&
    $status !== 'inativa'
) {
    die("Status inválido.");
}


/* VERIFICA CNPJ
   Ignorando a própria clínica */

$sql = "
SELECT id_clinica
FROM clinica
WHERE cnpj = ?
AND id_clinica <> ?
";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "si",
    $cnpj,
    $id_clinica
);

$stmt->execute();

$resultado = $stmt->get_result();

if ($resultado->num_rows > 0) {
    die("Este CNPJ já está cadastrado em outra clínica.");
}


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


if ($stmt->execute()) {


    $_SESSION['mensagem_sucesso'] = "Clínica atualizada com sucesso!";

    header("Location: admin.php?aba=clinicas");
    exit;

} else {

    die(
        "Erro ao editar clínica: " .
        $conn->error
    );
}