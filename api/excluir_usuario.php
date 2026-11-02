<?php

require_once('../config/auth.php');
require_once('../config/database.php');

verificarPerfil('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../admin.php?aba=usuarios");
    exit;
}


/* RECEBE O ID */

require_once('../config/arquivos.php');

$id_usuario = intval($_POST['id_usuario'] ?? 0);

if ($id_usuario <= 0) {
    $_SESSION['mensagem_sucesso'] = "Usuário inválido.";
    header("Location: ../admin.php?aba=usuarios");
    exit;
}


/* IMPEDE EXCLUSÃO DO PRÓPRIO ADMIN LOGADO */

if ($id_usuario === intval($_SESSION['id_usuario'])) {
    $_SESSION['mensagem_sucesso'] = "Você não pode excluir sua própria conta.";
    header("Location: ../admin.php?aba=usuarios");
    exit;
}


/* VERIFICA SE O USUÁRIO EXISTE */

$sql = "
SELECT id_usuario, nome, perfil
FROM usuarios
WHERE id_usuario = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows === 0) {
    $stmt->close();
    $_SESSION['mensagem_sucesso'] = "Usuário não encontrado.";
    header("Location: ../admin.php?aba=usuarios");
    exit;
}

$usuario = $resultado->fetch_assoc();
$stmt->close();


/* VERIFICA SE HÁ CONSULTAS VINCULADAS (médico ou paciente) */

if ($usuario['perfil'] === 'medico') {
    $sql = "
    SELECT COUNT(*) AS total
    FROM consulta
    WHERE id_medico = ?
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row && $row['total'] > 0) {
        $_SESSION['mensagem_sucesso'] =
            "Não é possível excluir o médico \"" . $usuario['nome'] . "\": existem " .
            $row['total'] .
            " consulta(s) vinculada(s) a ele.";

        header("Location: ../admin.php?aba=usuarios");
        exit;
    }
}

if ($usuario['perfil'] === 'paciente') {
    $sql = "
    SELECT COUNT(*) AS total
    FROM consulta
    WHERE id_paciente = ?
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row && $row['total'] > 0) {
        $_SESSION['mensagem_sucesso'] =
            "Não é possível excluir o paciente \"" . $usuario['nome'] . "\": existem " .
            $row['total'] .
            " consulta(s) vinculada(s) a ele.";

        header("Location: ../admin.php?aba=usuarios");
        exit;
    }
}


try {
    $conn->begin_transaction();

    // 1. Limpa registros dependentes que não possuem consultas
    if ($usuario['perfil'] === 'medico') {
        $stmtDelAgenda = $conn->prepare("DELETE FROM agenda_medico WHERE id_medico = ?");
        if ($stmtDelAgenda) {
            $stmtDelAgenda->bind_param("i", $id_usuario);
            $stmtDelAgenda->execute();
            $stmtDelAgenda->close();
        }
    }

    if ($usuario['perfil'] === 'paciente') {
        // Exclui arquivos físicos se houver
        $stmtBuscaArqs = $conn->prepare("SELECT nome_armazenado FROM arquivo_paciente WHERE id_paciente = ?");
        if ($stmtBuscaArqs) {
            $stmtBuscaArqs->bind_param("i", $id_usuario);
            $stmtBuscaArqs->execute();
            $resArqs = $stmtBuscaArqs->get_result();
            while ($a = $resArqs->fetch_assoc()) {
                $caminho = caminhoArquivoPaciente($a['nome_armazenado']);
                if ($caminho && is_file($caminho)) {
                    @unlink($caminho);
                }
            }
            $stmtBuscaArqs->close();
        }

        $stmtDelArqs = $conn->prepare("DELETE FROM arquivo_paciente WHERE id_paciente = ?");
        if ($stmtDelArqs) {
            $stmtDelArqs->bind_param("i", $id_usuario);
            $stmtDelArqs->execute();
            $stmtDelArqs->close();
        }
    }

    // 2. Exclui o usuário
    $sql = "DELETE FROM usuarios WHERE id_usuario = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $stmt->close();

    $conn->commit();

    $_SESSION['mensagem_sucesso'] =
        "Usuário \"" . $usuario['nome'] . "\" excluído com sucesso!";

} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof mysqli && $conn->connect_errno === 0) {
        @$conn->rollback();
    }
    $_SESSION['mensagem_sucesso'] =
        "Erro ao excluir usuário: " . $e->getMessage();
}

header("Location: ../admin.php?aba=usuarios");
exit;
