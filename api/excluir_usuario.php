<?php

require_once('../config/auth.php');
require_once('../config/database.php');
require_once('../config/logs.php');

verificarLogin();

$ehSecretaria = ($_SESSION['perfil'] ?? '') === 'secretaria';

if (!$ehSecretaria) {
    verificarPerfil('admin');
}

$abaPost = ($_POST['aba'] ?? '') === 'pacientes' ? 'pacientes' : 'medicos';
$urlRetorno = $ehSecretaria
    ? '../secretaria.php?aba=' . $abaPost
    : '../admin.php?aba=usuarios';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . $urlRetorno);
    exit;
}


/* RECEBE O ID */

require_once('../config/arquivos.php');

$id_usuario = intval($_POST['id_usuario'] ?? 0);

if ($id_usuario <= 0) {
    $_SESSION['mensagem_sucesso'] = "Usuário inválido.";
    header("Location: " . $urlRetorno);
    exit;
}


/* IMPEDE EXCLUSÃO DO PRÓPRIO USUÁRIO LOGADO */

if ($id_usuario === intval($_SESSION['id_usuario'])) {
    $_SESSION['mensagem_sucesso'] = "Você não pode excluir sua própria conta.";
    header("Location: " . $urlRetorno);
    exit;
}


/* VERIFICA SE O USUÁRIO EXISTE */

$sql = "
SELECT id_usuario, nome, perfil, id_clinica
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
    header("Location: " . $urlRetorno);
    exit;
}

$usuario = $resultado->fetch_assoc();
$stmt->close();

if ($ehSecretaria) {
    $urlRetorno = ($usuario['perfil'] === 'paciente')
        ? '../secretaria.php?aba=pacientes'
        : '../secretaria.php?aba=medicos';

    $idClinicaSessao = intval($_SESSION['id_clinica'] ?? 0);
    if (
        $idClinicaSessao <= 0 ||
        intval($usuario['id_clinica'] ?? 0) !== $idClinicaSessao ||
        !in_array($usuario['perfil'], ['medico', 'paciente'], true)
    ) {
        $_SESSION['mensagem_sucesso'] = "Sem permissão para excluir este usuário.";
        header("Location: " . $urlRetorno);
        exit;
    }
}


/* VERIFICA SE HÁ DADOS VINCULADOS (médico ou paciente) */

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

        header("Location: " . $urlRetorno);
        exit;
    }

    /* Verifica outros registros clínicos vinculados ao médico */
    $tabelasMedico = ['prontuario', 'receita', 'solicitacao_exame', 'prontuario_adendo'];
    foreach ($tabelasMedico as $tabMed) {
        try {
            $stmtExtra = @$conn->prepare("SELECT COUNT(*) AS total FROM `$tabMed` WHERE id_medico = ?");
            if ($stmtExtra) {
                $stmtExtra->bind_param("i", $id_usuario);
                $stmtExtra->execute();
                $rowExtra = $stmtExtra->get_result()->fetch_assoc();
                $stmtExtra->close();
                if ($rowExtra && intval($rowExtra['total']) > 0) {
                    $_SESSION['mensagem_sucesso'] =
                        "Não é possível excluir o médico \"" . $usuario['nome'] . "\": existem dados clínicos vinculados a ele.";
                    header("Location: " . $urlRetorno);
                    exit;
                }
            }
        } catch (Throwable $eExtra) {}
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

        header("Location: " . $urlRetorno);
        exit;
    }

    /* Verifica outros registros clínicos vinculados ao paciente */
    $tabelasPaciente = ['prontuario', 'receita', 'solicitacao_exame'];
    foreach ($tabelasPaciente as $tabPac) {
        try {
            $stmtExtra = @$conn->prepare("SELECT COUNT(*) AS total FROM `$tabPac` WHERE id_paciente = ?");
            if ($stmtExtra) {
                $stmtExtra->bind_param("i", $id_usuario);
                $stmtExtra->execute();
                $rowExtra = $stmtExtra->get_result()->fetch_assoc();
                $stmtExtra->close();
                if ($rowExtra && intval($rowExtra['total']) > 0) {
                    $_SESSION['mensagem_sucesso'] =
                        "Não é possível excluir o paciente \"" . $usuario['nome'] . "\": existem dados clínicos vinculados a ele.";
                    header("Location: " . $urlRetorno);
                    exit;
                }
            }
        } catch (Throwable $eExtra) {}
    }
}


try {
    $conn->begin_transaction();

    // 1. Limpa registros dependentes que não possuem consultas
    if ($usuario['perfil'] === 'medico') {
        try {
            $stmtDelAgenda = @$conn->prepare("DELETE FROM agenda_medico WHERE id_medico = ?");
            if ($stmtDelAgenda) {
                $stmtDelAgenda->bind_param("i", $id_usuario);
                $stmtDelAgenda->execute();
                $stmtDelAgenda->close();
            }
        } catch (Throwable $eAg) {}
    }

    if ($usuario['perfil'] === 'paciente') {
        try {
            // Exclui arquivos físicos se houver
            $stmtBuscaArqs = @$conn->prepare("SELECT nome_armazenado FROM arquivo_paciente WHERE id_paciente = ?");
            if ($stmtBuscaArqs) {
                $stmtBuscaArqs->bind_param("i", $id_usuario);
                $stmtBuscaArqs->execute();
                $resArqs = $stmtBuscaArqs->get_result();
                while ($resArqs && $a = $resArqs->fetch_assoc()) {
                    $caminho = caminhoArquivoPaciente($a['nome_armazenado']);
                    if ($caminho && is_file($caminho)) {
                        @unlink($caminho);
                    }
                }
                $stmtBuscaArqs->close();
            }

            $stmtDelArqs = @$conn->prepare("DELETE FROM arquivo_paciente WHERE id_paciente = ?");
            if ($stmtDelArqs) {
                $stmtDelArqs->bind_param("i", $id_usuario);
                $stmtDelArqs->execute();
                $stmtDelArqs->close();
            }
        } catch (Throwable $eArq) {}
    }

    // 2. Exclui o usuário
    $sql = "DELETE FROM usuarios WHERE id_usuario = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $stmt->close();

    $conn->commit();

    registrarLog($conn, "Exclusão de usuário: " . $usuario['nome'] . " (" . $usuario['perfil'] . ")");

    $_SESSION['mensagem_sucesso'] =
        "Usuário \"" . $usuario['nome'] . "\" excluído com sucesso!";

} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof mysqli && $conn->connect_errno === 0) {
        @$conn->rollback();
    }
    $_SESSION['mensagem_sucesso'] =
        "Erro ao excluir usuário: " . $e->getMessage();
}

header("Location: " . $urlRetorno);
exit;
