<?php

require_once('config/auth.php');
require_once('config/database.php');

verificarPerfil('admin');


$id_clinica = intval($_GET['id'] ?? 0);

if ($id_clinica <= 0) {
    header("Location: admin.php");
    exit;
}


/* BUSCA A CLÍNICA */

$sql = "
SELECT *
FROM clinica
WHERE id_clinica = ?
";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "i",
    $id_clinica
);

$stmt->execute();

$resultado = $stmt->get_result();

if ($resultado->num_rows === 0) {
    die("Clínica não encontrada.");
}

$clinica = $resultado->fetch_assoc();

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Editar Clínica - TechNyes</title>

<link
    rel="stylesheet"
    href="assets/css/style.css"
>

</head>

<body>


<header>

    <div class="logo">
        Tech<span>Nyes</span>
    </div>

    <nav>

        <button
            onclick="window.location.href='admin.php'"
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
                        class="field"
                        maxlength="14"
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
                        class="field"
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
                        class="field"
                        maxlength="8"
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

                        <?php

                        $estados = [
                            'SP', 'RJ', 'MG', 'PR',
                            'SC', 'RS', 'BA', 'PE',
                            'GO', 'DF'
                        ];

                        foreach ($estados as $estado):

                        ?>

                            <option
                                value="<?= $estado ?>"
                                <?= $clinica['estado'] === $estado ? 'selected' : '' ?>
                            >

                                <?= $estado ?>

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
                onclick="window.location.href='admin.php'"
            >
                Cancelar
            </button>

        </form>

    </div>

</div>

</body>

</html>