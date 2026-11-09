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
    stripos($mensagemSucesso, 'já cadastrado') !== false ||
    stripos($mensagemSucesso, 'obrigat') !== false
);

$id_clinica = intval($_GET['id'] ?? 0);

if ($id_clinica <= 0) {
    $_SESSION['mensagem_sucesso'] = "Clínica inválida.";
    header("Location: admin.php?aba=clinicas");
    exit;
}

/* BUSCA A CLÍNICA */
$sql = "
SELECT *
FROM clinica
WHERE id_clinica = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_clinica);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows === 0) {
    $_SESSION['mensagem_sucesso'] = "Clínica não encontrada.";
    header("Location: admin.php?aba=clinicas");
    exit;
}

$clinica = $resultado->fetch_assoc();

$temAssinaturaAtiva = false;
$qa = $conn->prepare("SELECT 1 FROM assinaturas WHERE id_clinica = ? AND status = 'ativa' LIMIT 1");
if ($qa) {
    $qa->bind_param('i', $id_clinica);
    $qa->execute();
    $temAssinaturaAtiva = $qa->get_result()->num_rows > 0;
    $qa->close();
}


/* LISTA DE ESTADOS */

$estados = [
    'AC', 'AL', 'AP', 'AM', 'BA',
    'CE', 'DF', 'ES', 'GO', 'MA',
    'MT', 'MS', 'MG', 'PA', 'PB',
    'PR', 'PE', 'PI', 'RJ', 'RN',
    'RS', 'RO', 'RR', 'SC', 'SP',
    'SE', 'TO'
];

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Editar Clínica - NyesClinic</title>

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
            onclick="window.location.href='admin.php?aba=clinicas'"
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
            Editar Clínica
        </h2>

        <?php if (!empty($mensagemSucesso)): ?>
            <div style="background:<?= $isMensagemErro ? 'var(--danger-light,#fee2e2)' : '#dcfce7' ?>;border:1px solid <?= $isMensagemErro ? 'rgba(239,68,68,0.3)' : 'rgba(34,197,94,0.3)' ?>;color:<?= $isMensagemErro ? 'var(--danger,#dc2626)' : '#16a34a' ?>;padding:12px 16px;border-radius:var(--radius-md,8px);margin:14px 0;font-size:0.9rem;display:flex;align-items:center;gap:10px;">
                <span style="font-size:1.1rem;font-weight:bold;"><?= $isMensagemErro ? '⚠' : '✓' ?></span>
                <span><?= htmlspecialchars($mensagemSucesso) ?></span>
            </div>
        <?php endif; ?>

        <br>


        <form
            action="api/editar_clinica.php"
            method="POST"
        >

            <input
                type="hidden"
                name="id_clinica"
                value="<?= $clinica['id_clinica'] ?>"
            >


            <div class="grid">


                <div class="field-group">

                    <label>
                        Nome da Clínica *
                    </label>

                    <input
                        type="text"
                        name="nome"
                        class="field"
                        value="<?= htmlspecialchars($clinica['nome']) ?>"
                        required
                    >

                </div>


                <div class="field-group">

                    <label>
                        CNPJ *
                    </label>

                    <input
                        type="text"
                        name="cnpj"
                        class="field mask-cnpj"
                        maxlength="18"
                        placeholder="XX.XXX.XXX/XXXX-XX"
                        value="<?= htmlspecialchars($clinica['cnpj']) ?>"
                        required
                    >

                </div>


                <div class="field-group">

                    <label>
                        Telefone
                    </label>

                    <input
                        type="text"
                        name="telefone"
                        class="field mask-telefone"
                        placeholder="(XX) XXXXX-XXXX"
                        value="<?= htmlspecialchars($clinica['telefone'] ?? '') ?>"
                    >

                </div>


                <div class="field-group">

                    <label>
                        E-mail
                    </label>

                    <input
                        type="email"
                        name="email"
                        class="field"
                        value="<?= htmlspecialchars($clinica['email'] ?? '') ?>"
                    >

                </div>


                <div class="field-group">

                    <label>
                        CEP
                    </label>

                    <input
                        type="text"
                        name="cep"
                        class="field mask-cep"
                        maxlength="9"
                        placeholder="XXXXX-XXX"
                        value="<?= htmlspecialchars($clinica['cep'] ?? '') ?>"
                    >

                </div>


                <div class="field-group">

                    <label>
                        Endereço
                    </label>

                    <input
                        type="text"
                        name="endereco"
                        class="field"
                        value="<?= htmlspecialchars($clinica['endereco'] ?? '') ?>"
                    >

                </div>


                <div class="field-group">

                    <label>
                        Número
                    </label>

                    <input
                        type="text"
                        name="numero"
                        class="field"
                        value="<?= htmlspecialchars($clinica['numero'] ?? '') ?>"
                    >

                </div>


                <div class="field-group">

                    <label>
                        Complemento
                    </label>

                    <input
                        type="text"
                        name="complemento"
                        class="field"
                        value="<?= htmlspecialchars($clinica['complemento'] ?? '') ?>"
                    >

                </div>


                <div class="field-group">

                    <label>
                        Bairro
                    </label>

                    <input
                        type="text"
                        name="bairro"
                        class="field"
                        value="<?= htmlspecialchars($clinica['bairro'] ?? '') ?>"
                    >

                </div>


                <div class="field-group">

                    <label>
                        Cidade
                    </label>

                    <input
                        type="text"
                        name="cidade"
                        class="field"
                        value="<?= htmlspecialchars($clinica['cidade'] ?? '') ?>"
                    >

                </div>


                <div class="field-group">

                    <label>
                        Estado
                    </label>

                    <select
                        name="estado"
                        class="field"
                    >

                        <option value="">
                            Selecione
                        </option>

                        <?php foreach ($estados as $uf): ?>

                            <option
                                value="<?= $uf ?>"
                                <?= ($clinica['estado'] ?? '') === $uf ? 'selected' : '' ?>
                            >

                                <?= $uf ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

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
                            value="ativa"
                            <?= $clinica['status'] === 'ativa' ? 'selected' : '' ?>
                        >
                            Ativa
                        </option>

                        <option
                            value="inativa"
                            <?= $clinica['status'] === 'inativa' ? 'selected' : '' ?>
                        >
                            Inativa
                        </option>

                    </select>

                </div>

                <div class="field-group" id="grupo-cancelar-assinatura" style="display:none;">
                    <label>
                        <input type="checkbox" name="cancelar_assinaturas" value="1" checked>
                        Cancelar também a assinatura ativa desta clínica
                    </label>
                    <small style="color:#888;">Esta clínica possui assinatura ativa. Ao inativar a clínica ela deixa de contar no financeiro; marque para cancelar a assinatura também.</small>
                </div>

                <script>
                (function () {
                    var temAssinatura = <?= $temAssinaturaAtiva ? 'true' : 'false' ?>;
                    var sel = document.querySelector('select[name="status"]');
                    var grupo = document.getElementById('grupo-cancelar-assinatura');
                    function atualizar() {
                        grupo.style.display = (temAssinatura && sel.value === 'inativa') ? '' : 'none';
                    }
                    sel.addEventListener('change', atualizar);
                    atualizar();
                })();
                </script>


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
                onclick="window.location.href='admin.php?aba=clinicas'"
            >
                Cancelar
            </button>

        </form>

    </div>

</div>


<!-- JS para máscaras e utilitários -->
<script src="assets/js/main.js"></script>
<script src="assets/js/admin.js"></script>

</body>

</html>
