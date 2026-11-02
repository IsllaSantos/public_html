<?php

require_once('../config/auth.php');
require_once('../config/database.php');

verificarPerfil('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../admin.php?aba=clinicas");
    exit;
}


/* RECEBE O ID */

$id_clinica = intval($_POST['id_clinica'] ?? 0);

if ($id_clinica <= 0) {
    $_SESSION['mensagem_sucesso'] = "Clínica inválida.";
    header("Location: ../admin.php?aba=clinicas");
    exit;
}


try {
    /* VERIFICA SE A CLÍNICA EXISTE */
    $sql = "SELECT id_clinica, nome FROM clinica WHERE id_clinica = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_clinica);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 0) {
        $stmt->close();
        $_SESSION['mensagem_sucesso'] = "Clínica não encontrada.";
        header("Location: ../admin.php?aba=clinicas");
        exit;
    }

    $clinica = $resultado->fetch_assoc();
    $nomeClinica = $clinica['nome'];
    $stmt->close();


    /* VERIFICA SE HÁ USUÁRIOS VINCULADOS */
    $sql = "SELECT COUNT(*) AS total FROM usuarios WHERE id_clinica = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_clinica);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row && $row['total'] > 0) {
        $_SESSION['mensagem_sucesso'] =
            "Não é possível excluir a clínica \"" . $nomeClinica . "\": existem " .
            $row['total'] .
            " usuário(s) vinculado(s) a ela.";

        header("Location: ../admin.php?aba=clinicas");
        exit;
    }


    /* EXCLUI AS ASSINATURAS E A CLÍNICA DENTRO DE UMA TRANSAÇÃO */
    $conn->begin_transaction();

    // 1. Remove assinaturas vinculadas (evita violação da chave estrangeira fk_assinatura_clinica)
    $stmtAssinaturas = $conn->prepare("DELETE FROM assinaturas WHERE id_clinica = ?");
    $stmtAssinaturas->bind_param("i", $id_clinica);
    $stmtAssinaturas->execute();
    $stmtAssinaturas->close();

    // 2. Remove a clínica
    $stmtClinica = $conn->prepare("DELETE FROM clinica WHERE id_clinica = ?");
    $stmtClinica->bind_param("i", $id_clinica);
    $stmtClinica->execute();
    $stmtClinica->close();

    $conn->commit();

    $_SESSION['mensagem_sucesso'] = "Clínica \"" . $nomeClinica . "\" excluída com sucesso!";

} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof mysqli && $conn->connect_errno === 0) {
        @$conn->rollback();
    }
    $_SESSION['mensagem_sucesso'] = "Erro ao excluir clínica: " . $e->getMessage();
}

header("Location: ../admin.php?aba=clinicas");
exit;
