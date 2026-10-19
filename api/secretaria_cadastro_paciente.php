<?php

require_once('../config/auth.php');
require_once('../config/database.php');

verificarPerfil('secretaria');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../secretaria.php");
    exit;
}

$id_clinica = $_SESSION['id_clinica'];

if (empty($id_clinica)) {
    die("Erro: secretária sem clínica vinculada.");
}


/* CAMPOS */

$nome = trim($_POST['nome'] ?? '');
$email = trim($_POST['email'] ?? '');
$senha = $_POST['senha'] ?? '';

/* Dados Pessoais */
$cpf = !empty($_POST['cpf'])
    ? preg_replace('/\D/', '', trim($_POST['cpf']))
    : null;
$rg = !empty($_POST['rg'])
    ? trim($_POST['rg'])
    : null;
$data_nascimento = !empty($_POST['data_nascimento'])
    ? trim($_POST['data_nascimento'])
    : null;
$sexo = !empty($_POST['sexo'])
    ? trim($_POST['sexo'])
    : null;
$telefone = !empty($_POST['telefone'])
    ? preg_replace('/\D/', '', trim($_POST['telefone']))
    : null;
$celular = !empty($_POST['celular'])
    ? preg_replace('/\D/', '', trim($_POST['celular']))
    : null;

/* Endereço */
$cep = !empty($_POST['cep'])
    ? preg_replace('/\D/', '', trim($_POST['cep']))
    : null;
$endereco = !empty($_POST['endereco'])
    ? trim($_POST['endereco'])
    : null;
$numero = !empty($_POST['numero'])
    ? trim($_POST['numero'])
    : null;
$complemento = !empty($_POST['complemento'])
    ? trim($_POST['complemento'])
    : null;
$bairro = !empty($_POST['bairro'])
    ? trim($_POST['bairro'])
    : null;
$cidade = !empty($_POST['cidade'])
    ? trim($_POST['cidade'])
    : null;
$estado = !empty($_POST['estado'])
    ? trim($_POST['estado'])
    : null;

/* Convênio */
$convenio = !empty($_POST['convenio'])
    ? trim($_POST['convenio'])
    : null;
$numero_carteirinha = !empty($_POST['numero_carteirinha'])
    ? trim($_POST['numero_carteirinha'])
    : null;

/* Contato de Emergência */
$contato_emergencia_nome = !empty($_POST['contato_emergencia_nome'])
    ? trim($_POST['contato_emergencia_nome'])
    : null;
$contato_emergencia_telefone = !empty($_POST['contato_emergencia_telefone'])
    ? preg_replace('/\D/', '', trim($_POST['contato_emergencia_telefone']))
    : null;

if (
    empty($nome) ||
    empty($email) ||
    empty($senha)
) {
    die("Preencha todos os campos obrigatórios.");
}


/* VERIFICA E-MAIL DUPLICADO */

$stmt = $conn->prepare("SELECT id_usuario FROM usuarios WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();

if ($stmt->get_result()->num_rows > 0) {
    die("E-mail já cadastrado.");
}


/* VERIFICA CPF DUPLICADO */

if (!empty($cpf)) {
    $stmtCpf = $conn->prepare("SELECT id_usuario FROM usuarios WHERE cpf = ?");
    $stmtCpf->bind_param("s", $cpf);
    $stmtCpf->execute();

    if ($stmtCpf->get_result()->num_rows > 0) {
        die("CPF já cadastrado.");
    }
}


/* INSERE */

$senhaHash = password_hash($senha, PASSWORD_DEFAULT);

$sql = "
INSERT INTO usuarios
(
    nome, email, senha, perfil, id_clinica,
    cpf, rg, data_nascimento, sexo, telefone, celular,
    cep, endereco, numero, complemento, bairro, cidade, estado,
    convenio, numero_carteirinha,
    contato_emergencia_nome, contato_emergencia_telefone,
    status
)
VALUES
(
    ?, ?, ?, 'paciente', ?,
    ?, ?, ?, ?, ?, ?,
    ?, ?, ?, ?, ?, ?, ?,
    ?, ?,
    ?, ?,
    'ativo'
)
";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "sssisssssssssssssssss",
    $nome,
    $email,
    $senhaHash,
    $id_clinica,
    $cpf,
    $rg,
    $data_nascimento,
    $sexo,
    $telefone,
    $celular,
    $cep,
    $endereco,
    $numero,
    $complemento,
    $bairro,
    $cidade,
    $estado,
    $convenio,
    $numero_carteirinha,
    $contato_emergencia_nome,
    $contato_emergencia_telefone
);

if ($stmt->execute()) {
    $_SESSION['mensagem_sucesso'] = "Paciente cadastrado com sucesso!";
    header("Location: ../secretaria.php?aba=pacientes");
    exit;
} else {
    die("Erro ao cadastrar paciente: " . $conn->error);
}
