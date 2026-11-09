<?php

require_once('config/auth.php');
require_once('config/database.php');

verificarPerfil('admin');

$mensagemSucesso = $_SESSION['mensagem_sucesso'] ?? '';
unset($_SESSION['mensagem_sucesso']);
$isMensagemErro = (
    stripos($mensagemSucesso, 'não') !== false ||
    stripos($mensagemSucesso, 'erro') !== false ||
    stripos($mensagemSucesso, 'inválid') !== false ||
    stripos($mensagemSucesso, 'obrigat') !== false
);

$id_plano = intval($_GET['id'] ?? 0);

if ($id_plano <= 0) {
    $_SESSION['mensagem_sucesso'] = "Plano inválido.";
    header("Location: admin.php?aba=planos");
    exit;
}

$stmt = $conn->prepare("SELECT * FROM planos WHERE id_plano = ?");
$stmt->bind_param("i", $id_plano);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows === 0) {
    $_SESSION['mensagem_sucesso'] = "Plano não encontrado.";
    header("Location: admin.php?aba=planos");
    exit;
}

$plano = $resultado->fetch_assoc();

/* Converte recursos de pipe para linhas */
$recursosTexto = str_replace('|', "\n", $plano['recursos'] ?? '');

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Editar Plano - NyesClinic</title>

<link
    rel="stylesheet"
    href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>"
>

</head>

<body>


<header>

    <div class="logo">
        Nyes<span>Clinic</span>
    </div>

    <nav>

        <button
            onclick="window.location.href='admin.php?aba=planos'"
        >
            Voltar
        </button>

        <button
            onclick="window.location.href='logout.php'"
        >
            Sair
        </button>

    </nav>

</header>


<div class="container">

    <div class="card">

        <h2>
            Editar Plano —

            <span style="color:var(--gold);">
                <?= htmlspecialchars($plano['nome']) ?>
            </span>
        </h2>

        <?php if (!empty($mensagemSucesso)): ?>
            <div style="background:<?= $isMensagemErro ? 'var(--danger-light,#fee2e2)' : '#dcfce7' ?>;border:1px solid <?= $isMensagemErro ? 'rgba(239,68,68,0.3)' : 'rgba(34,197,94,0.3)' ?>;color:<?= $isMensagemErro ? 'var(--danger,#dc2626)' : '#16a34a' ?>;padding:12px 16px;border-radius:var(--radius-md,8px);margin:14px 0;font-size:0.9rem;display:flex;align-items:center;gap:10px;">
                <span style="font-size:1.1rem;font-weight:bold;"><?= $isMensagemErro ? '⚠' : '✓' ?></span>
                <span><?= htmlspecialchars($mensagemSucesso) ?></span>
            </div>
        <?php endif; ?>

        <br>


        <form
            action="api/editar_plano.php"
            method="POST"
        >

            <input
                type="hidden"
                name="id_plano"
                value="<?= $plano['id_plano'] ?>"
            >


            <div class="grid">


                <div class="field-group">

                    <label>
                        Nome do Plano *
                    </label>

                    <input
                        type="text"
                        name="nome"
                        class="field"
                        value="<?= htmlspecialchars($plano['nome']) ?>"
                        required
                    >

                </div>


                <div class="field-group">

                    <label>
                        Valor Mensal (R$) *
                    </label>

                    <input
                        type="number"
                        name="valor"
                        class="field"
                        step="0.01"
                        min="0"
                        value="<?= $plano['valor'] ?>"
                        required
                    >

                </div>


                <div class="field-group">

                    <label>
                        Máx. Médicos
                        <span class="tooltip-icon">*
                            <span class="tooltip-text">Deixe vazio para ilimitado</span>
                        </span>
                    </label>

                    <input
                        type="number"
                        name="max_medicos"
                        class="field"
                        min="1"
                        value="<?= $plano['max_medicos'] ?>"
                    >

                </div>


                <div class="field-group">

                    <label>
                        Armazenamento (GB)
                        <span class="tooltip-icon">*
                            <span class="tooltip-text">Deixe vazio para ilimitado</span>
                        </span>
                    </label>

                    <input
                        type="number"
                        name="max_armazenamento_gb"
                        class="field"
                        min="1"
                        value="<?= $plano['max_armazenamento_gb'] ?>"
                    >

                </div>


                <div class="field-group">

                    <label>
                        Status
                    </label>

                    <select
                        name="status"
                        class="field"
                    >

                        <option
                            value="ativo"
                            <?= $plano['status'] === 'ativo' ? 'selected' : '' ?>
                        >
                            Ativo
                        </option>

                        <option
                            value="inativo"
                            <?= $plano['status'] === 'inativo' ? 'selected' : '' ?>
                        >
                            Inativo
                        </option>

                    </select>

                </div>


                <div class="field-group">

                    <label>
                        <input
                            type="checkbox"
                            name="destaque"
                            <?= $plano['destaque'] ? 'checked' : '' ?>
                        >
                        Plano Recomendado (destaque)
                    </label>

                </div>


            </div>


            <br>


            <div class="field-group">

                <label>
                    Descrição
                </label>

                <input
                    type="text"
                    name="descricao"
                    class="field"
                    value="<?= htmlspecialchars($plano['descricao'] ?? '') ?>"
                >

            </div>


            <br>


            <div class="textarea-group">

                <label>
                    Recursos (um por linha)
                </label>

                <textarea
                    name="recursos"
                    class="large"
                ><?= htmlspecialchars($recursosTexto) ?></textarea>

            </div>


            <br>


            <button
                type="submit"
                class="confirm-btn"
            >
                Salvar Alterações
            </button>


            <button
                type="button"
                class="menu-btn"
                onclick="window.location.href='admin.php?aba=planos'"
            >
                Cancelar
            </button>

        </form>

    </div>

</div>


<script src="assets/js/main.js"></script>
</body>

</html>
