<?php
/*
 * Arquivo de Configuração do Sistema TechNyes / NyesClinic
 * Mantenha este arquivo seguro e com permissões restritas.
 */
return [
    // Banco de Dados
    'host'    => 'db_technyes.mysql.dbaas.com.br',
    'usuario' => 'db_technyes',
    'senha'   => 'wUd@Ea#x3PhiFL',
    'banco'   => 'db_technyes',

    // E-mail via API HTTPS (sem senha de app, sem SMTP)
    'email_provedor'  => 'brevo',   // 'brevo' (recomendado) ou 'resend'
    'email_api_key'   => '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',        // Chave de API gerada no painel do provedor
    'email_remetente' => 'bellla.machadosantos@gmail.com',        // E-mail remetente VERIFICADO no provedor
    'email_nome'      => 'NyesClinic',
];