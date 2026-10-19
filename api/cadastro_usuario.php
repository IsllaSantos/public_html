<?php

require_once('../config/auth.php');
require_once('../config/database.php');

verificarPerfil('admin');

if ($_SERVER['REQUEST_METHOD'] != 'POST') {

    header("Location: ../admin.php");
    exit;
}


/* ==============================
   CAMPOS OBRIGATÓRIOS (TODOS)
============================== */

$nome = trim($_POST['nome']);
$email = trim($_POST['email']);
$senha = $_POST['senha'];
$perfil = $_POST['perfil'];

if (
    empty($nome) ||
    empty($email) ||
    empty($senha) ||
    empty($perfil)
) {

    die("Preencha todos os campos obrigatórios.");
}


/* ==============================
   CAMPOS CONDICIONAIS
============================== */

$id_clinica = !empty($_POST['id_clinica'])
    ? intval($_POST['id_clinica'])
    : null;

/* Dados pessoais (secretaria, medico, paciente) */
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

/* Dados do médico */
$crm = !empty($_POST['crm'])
    ? trim($_POST['crm'])
    : null;
$uf_crm = !empty($_POST['uf_crm'])
    ? trim($_POST['uf_crm'])
    : null;
$especialidade = !empty($_POST['especialidade'])
    ? trim($_POST['especialidade'])
    : null;
$horario_inicio = !empty($_POST['horario_inicio'])
    ? trim($_POST['horario_inicio'])
    : null;
$horario_fim = !empty($_POST['horario_fim'])
    ? trim($_POST['horario_fim'])
    : null;
$duracao_consulta = !empty($_POST['duracao_consulta'])
    ? intval($_POST['duracao_consulta'])
    : 30;

/* Dados do paciente */
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
$convenio = !empty($_POST['convenio'])
    ? trim($_POST['convenio'])
    : null;
$numero_carteirinha = !empty($_POST['numero_carteirinha'])
    ? trim($_POST['numero_carteirinha'])
    : null;
$contato_emergencia_nome = !empty($_POST['contato_emergencia_nome'])
    ? trim($_POST['contato_emergencia_nome'])
    : null;
$contato_emergencia_telefone = !empty($_POST['contato_emergencia_telefone'])
    ? preg_replace('/\D/', '', trim($_POST['contato_emergencia_telefone']))
    : null;


/* ==============================
   VERIFICA E-MAIL DUPLICADO
============================== */

$sql = "
SELECT id_usuario
FROM usuarios
WHERE email = ?
";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "s",
    $email
);

$stmt->execute();

$resultado = $stmt->get_result();

if ($resultado->num_rows > 0) {

    die("E-mail já cadastrado.");
}


/* ==============================
   VERIFICA CPF DUPLICADO
============================== */

if (!empty($cpf)) {

    $sql = "
    SELECT id_usuario
    FROM usuarios
    WHERE cpf = ?
    ";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param(
        "s",
        $cpf
    );

    $stmt->execute();

    $resultado = $stmt->get_result();

    if ($resultado->num_rows > 0) {

        die("CPF já cadastrado.");
    }
}


/* ==============================
   CRIPTOGRAFA SENHA
============================== */

$senhaHash =
password_hash(
    $senha,
    PASSWORD_DEFAULT
);


/* ==============================
   INSERE NO BANCO
============================== */

$sql = "
INSERT INTO usuarios
(
    nome,
    email,
    senha,
    perfil,
    id_clinica,
    cpf,
    rg,
    data_nascimento,
    sexo,
    telefone,
    celular,
    crm,
    uf_crm,
    especialidade,
    horario_inicio,
    horario_fim,
    duracao_consulta,
    cep,
    endereco,
    numero,
    complemento,
    bairro,
    cidade,
    estado,
    convenio,
    numero_carteirinha,
    contato_emergencia_nome,
    contato_emergencia_telefone,
    status
)
VALUES
(
    ?, ?, ?, ?, ?,
    ?, ?, ?, ?, ?,
    ?, ?, ?, ?, ?,
    ?, ?, ?, ?, ?,
    ?, ?, ?, ?, ?,
    ?, ?, ?,
    'ativo'
)
";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "ssssisssssssssssisssssssssss",
    $nome,
    $email,
    $senhaHash,
    $perfil,
    $id_clinica,
    $cpf,
    $rg,
    $data_nascimento,
    $sexo,
    $telefone,
    $celular,
    $crm,
    $uf_crm,
    $especialidade,
    $horario_inicio,
    $horario_fim,
    $duracao_consulta,
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

    $_SESSION['mensagem_sucesso'] = "Usuário cadastrado com sucesso!";

    header(
        "Location: ../admin.php?aba=usuarios"
    );

    exit;

} else {

    die(
        "Erro ao cadastrar usuário: " .
        $conn->error
    );
}