<?php

/*
 * Pasta de armazenamento dos arquivos dos pacientes.
 * Preferência: FORA do public_html (não é apagada por deploys e não fica exposta na web).
 * Fallback: dentro do public_html (protegida por .htaccess).
 */

function pastasArquivosPacientes() {
    $raiz = dirname(__DIR__);           // public_html
    return [
        dirname($raiz) . '/uploads_pacientes_privado',
        $raiz . '/uploads_pacientes',
    ];
}

/* Retorna a pasta gravável para novos envios (criando se necessário). */
function pastaUploadPacientes() {
    foreach (pastasArquivosPacientes() as $i => $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (is_dir($dir) && is_writable($dir)) {
            if ($i === 1 && !file_exists($dir . '/.htaccess')) {
                @file_put_contents($dir . '/.htaccess', "Require all denied\nDeny from all\n");
            }
            if (!file_exists($dir . '/index.html')) {
                @file_put_contents($dir . '/index.html', '');
            }
            return $dir;
        }
    }
    return null;
}

/* Localiza um arquivo já salvo em qualquer das pastas conhecidas. */
function caminhoArquivoPaciente($nomeArmazenado) {
    $nome = basename($nomeArmazenado);
    foreach (pastasArquivosPacientes() as $dir) {
        if (is_file($dir . '/' . $nome)) {
            return $dir . '/' . $nome;
        }
    }
    return null;
}
