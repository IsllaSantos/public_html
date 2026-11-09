<?php
require_once __DIR__ . '/config/seguranca.php';
require_once __DIR__ . '/config/email.php';
$contato = configNyes('email_contato', '');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Política de Privacidade - NyesClinic</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>">
    <style>
        .politica h2 { font-size:1.15rem; margin:26px 0 8px; color:var(--black); }
        .politica p, .politica li { color:var(--text-muted); line-height:1.7; font-size:0.95rem; }
        .politica ul { padding-left:22px; }
    </style>
</head>
<body>
<header>
    <div class="header-content">
        <div class="logo"><a href="index.php">Nyes<span>Clinic</span></a></div>
        <nav>
            <a href="index.php">Início</a>
            <a href="login.php" class="btn btn-primary" style="padding:6px 16px;color:var(--black-dark);">Acessar Sistema →</a>
        </nav>
    </div>
</header>

<main class="container politica" style="padding:40px 0 60px 0;max-width:860px;">
    <h1 style="font-size:2rem;font-weight:800;">Política de Privacidade</h1>
    <p>Versão <?= htmlspecialchars(LGPD_VERSAO) ?> — em conformidade com a Lei Geral de Proteção de Dados (Lei nº 13.709/2018).</p>

    <h2>1. Quem somos e papéis</h2>
    <p>O NyesClinic é um sistema de gestão clínica desenvolvido pela TechNyes. A <strong>clínica</strong> que utiliza o sistema é a controladora dos dados dos seus pacientes; a TechNyes atua como operadora, fornecendo a plataforma e os meios técnicos de armazenamento.</p>

    <h2>2. Dados que tratamos</h2>
    <ul>
        <li><strong>Dados cadastrais:</strong> nome, e-mail, telefone/celular, documentos, data de nascimento, endereço e convênio.</li>
        <li><strong>Dados de saúde (sensíveis):</strong> anamnese, sinais vitais, hipótese diagnóstica, conduta, receitas, adendos e arquivos de exames enviados.</li>
        <li><strong>Dados de acesso:</strong> senha (armazenada apenas em formato criptografado/hash), registros de login e logs de ações no sistema.</li>
    </ul>

    <h2>3. Para que usamos</h2>
    <ul>
        <li>Agendar consultas e gerir a agenda dos profissionais.</li>
        <li>Registrar o atendimento (prontuário) e emitir receitas.</li>
        <li>Permitir que o paciente acompanhe suas consultas, receitas e envie exames.</li>
        <li>Enviar mensagens de serviço, como lembrete de consulta e redefinição de senha, por e-mail.</li>
        <li>Garantir a segurança do sistema e a auditoria das ações.</li>
    </ul>

    <h2>4. Base legal</h2>
    <p>Os dados de saúde são tratados para a <strong>tutela da saúde</strong>, em procedimentos realizados por profissionais de saúde (art. 11, II, "f", da LGPD), e, quando aplicável, com o seu <strong>consentimento</strong>, registrado no primeiro acesso ao sistema.</p>

    <h2>5. Compartilhamento</h2>
    <p>Os dados não são vendidos. O acesso é restrito por perfil: o paciente vê apenas os seus dados; médicos e secretaria acessam apenas dados da própria clínica, conforme sua função. Pode haver compartilhamento por obrigação legal ou determinação de autoridade competente.</p>

    <h2>6. Segurança</h2>
    <ul>
        <li>Controle de acesso por perfil de usuário.</li>
        <li>Senhas armazenadas com hash; recuperação por link temporário de uso único.</li>
        <li>Bloqueio temporário após várias tentativas de login incorretas.</li>
        <li>Sessões com cookies protegidos e expiração por inatividade.</li>
        <li>Proteção contra requisições de origem não autorizada (CSRF).</li>
        <li>Registro de ações no sistema (logs).</li>
    </ul>

    <h2>7. Por quanto tempo guardamos</h2>
    <p>Prontuários são mantidos pelo prazo legal aplicável (mínimo de 20 anos, conforme a Lei nº 13.787/2018 e normas do CFM). Demais dados são mantidos enquanto a conta estiver ativa ou conforme exigência legal.</p>

    <h2>8. Seus direitos</h2>
    <p>Você pode solicitar: confirmação de tratamento, acesso, correção, anonimização ou eliminação (quando cabível), portabilidade, informação sobre compartilhamento e revogação do consentimento (art. 18 da LGPD). Dados de prontuário podem ter retenção obrigatória por lei.</p>

    <h2>9. Contato</h2>
    <p>
        Para exercer seus direitos, procure a administração da sua clínica
        <?php if ($contato): ?>ou escreva para <strong><?= htmlspecialchars($contato) ?></strong><?php endif; ?>.
    </p>

    <p style="margin-top:30px;"><a class="btn btn-secondary" href="index.php">← Voltar</a></p>
</main>
</body>
</html>
