<?php

require_once('../config/auth.php');
require_once('../config/database.php');

verificarLogin();

$ehSecretaria = ($_SESSION['perfil'] ?? '') === 'secretaria';

if (!$ehSecretaria) {
    verificarPerfil('admin');
}

$urlRetorno = '../admin.php?aba=usuarios';

if ($ehSecretaria) {
    $urlRetorno = (($_POST['voltar'] ?? '') === 'secretaria.php?aba=pacientes')
        ? '../secretaria.php?aba=pacientes'
        : '../secretaria.php?aba=medicos';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . $urlRetorno);
    exit;
}


/* ==============================
   RECEBE O ID
============================== */

$id_usuario = intval($_POST['id_usuario'] ?? 0);

if ($id_usuario <= 0) {
    $_SESSION['mensagem_sucesso'] = "Usuário inválido.";
    header("Location: " . $urlRetorno);
    exit;
}

/* Secretária: só edita médicos/pacientes da própria clínica; perfil e clínica não mudam */
$perfilFixo = null;
$clinicaFixa = null;

if ($ehSecretaria) {

    $stmtChk = $conn->prepare("
        SELECT perfil, id_clinica
        FROM usuarios
        WHERE id_usuario = ?
        AND id_clinica = ?
        AND perfil IN ('medico','paciente')
    ");
    $stmtChk->bind_param("ii", $id_usuario, $_SESSION['id_clinica']);
    $stmtChk->execute();
    $alvo = $stmtChk->get_result()->fetch_assoc();
    $stmtChk->close();

    if (!$alvo) {
        $_SESSION['mensagem_sucesso'] = "Sem permissão para editar este usuário.";
        header("Location: " . $urlRetorno);
        exit;
    }

    $perfilFixo = $alvo['perfil'];
    $clinicaFixa = intval($alvo['id_clinica']);
    $_POST['perfil'] = $perfilFixo;
    $_POST['id_clinica'] = $clinicaFixa;
}


/* ==============================
   CAMPOS OBRIGATÓRIOS
============================== */

$nome = trim($_POST['nome'] ?? '');
$email = trim($_POST['email'] ?? '');
$perfil = trim($_POST['perfil'] ?? '');
$status = trim($_POST['status'] ?? 'ativo');

if (
    empty($nome) ||
    empty($email) ||
    empty($perfil)
) {
    $_SESSION['mensagem_sucesso'] = "Preencha todos os campos obrigatórios do usuário.";
    header("Location: ../editar_usuario.php?id=" . $id_usuario);
    exit;
}


/* ==============================
   CAMPOS CONDICIONAIS
============================== */

$id_clinica = !empty($_POST['id_clinica'])
    ? intval($_POST['id_clinica'])
    : null;

/* Dados pessoais */
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

/* Senha (opcional na edição) */
$senha_nova = !empty($_POST['senha'])
    ? $_POST['senha']
    : null;


/* ==============================
   VALIDA STATUS
============================== */

if (
    $status !== 'ativo' &&
    $status !== 'inativo'
) {
    $_SESSION['mensagem_sucesso'] = "Status do usuário inválido.";
    header("Location: ../editar_usuario.php?id=" . $id_usuario);
    exit;
}


/* ==============================
   VERIFICA E-MAIL DUPLICADO
============================== */

$sql = "
SELECT id_usuario
FROM usuarios
WHERE email = ?
AND id_usuario <> ?
";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "si",
    $email,
    $id_usuario
);

$stmt->execute();

$resultado = $stmt->get_result();

if ($resultado->num_rows > 0) {
    $_SESSION['mensagem_sucesso'] = "Este e-mail já está cadastrado em outro usuário.";
    header("Location: ../editar_usuario.php?id=" . $id_usuario);
    exit;
}


/* ==============================
   VERIFICA CPF DUPLICADO
============================== */

if (!empty($cpf)) {

    $sql = "
    SELECT id_usuario
    FROM usuarios
    WHERE cpf = ?
    AND id_usuario <> ?
    ";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param(
        "si",
        $cpf,
        $id_usuario
    );

    $stmt->execute();

    $resultado = $stmt->get_result();

    if ($resultado->num_rows > 0) {
        $_SESSION['mensagem_sucesso'] = "Este CPF já está cadastrado em outro usuário.";
        header("Location: ../editar_usuario.php?id=" . $id_usuario);
        exit;
    }
}


/* ==============================
   ATUALIZA NO BANCO
============================== */

/* Monta o SQL base (sem senha) */
$sql = "
UPDATE usuarios
SET
    nome = ?,
    email = ?,
    perfil = ?,
    status = ?,
    id_clinica = ?,
    cpf = ?,
    rg = ?,
    data_nascimento = ?,
    sexo = ?,
    telefone = ?,
    celular = ?,
    crm = ?,
    uf_crm = ?,
    especialidade = ?,
    horario_inicio = ?,
    horario_fim = ?,
    duracao_consulta = ?,
    cep = ?,
    endereco = ?,
    numero = ?,
    complemento = ?,
    bairro = ?,
    cidade = ?,
    estado = ?,
    convenio = ?,
    numero_carteirinha = ?,
    contato_emergencia_nome = ?,
    contato_emergencia_telefone = ?
";


/* Se informou nova senha, inclui no UPDATE */
if ($senha_nova) {

    $senhaHash = password_hash(
        $senha_nova,
        PASSWORD_DEFAULT
    );

    $sql .= ", senha = ?";
}

$sql .= " WHERE id_usuario = ?";


$stmt = $conn->prepare($sql);


if ($senha_nova) {

    $stmt->bind_param(
        "ssssisssssssssssissssssssssssi",
        $nome,
        $email,
        $perfil,
        $status,
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
        $contato_emergencia_telefone,
        $senhaHash,
        $id_usuario
    );

} else {

    $stmt->bind_param(
        "ssssisssssssssssisssssssssssi",
        $nome,
        $email,
        $perfil,
        $status,
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
        $contato_emergencia_telefone,
        $id_usuario
    );
}


try {
    if ($stmt->execute()) {
        $_SESSION['mensagem_sucesso'] = "Usuário atualizado com sucesso!";
        header("Location: " . $urlRetorno);
        exit;
    } else {
        $_SESSION['mensagem_sucesso'] = "Erro ao editar usuário: " . $stmt->error;
        header("Location: ../editar_usuario.php?id=" . $id_usuario);
        exit;
    }
} catch (Throwable $e) {
    $_SESSION['mensagem_sucesso'] = "Erro ao editar usuário: " . $e->getMessage();
    header("Location: ../editar_usuario.php?id=" . $id_usuario);
    exit;
}
