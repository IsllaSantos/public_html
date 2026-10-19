<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TechNyes - Sistema Inteligente para Clínicas e Consultórios</title>
    <meta name="description" content="Plataforma moderna para gestão clínica, prontuário eletrônico integrado, agenda médica inteligente, portal do paciente e conformidade LGPD.">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>">
</head>
<body>

<header>
    <div class="header-content">
        <div class="logo">
            <a href="index.php">
                Tech<span>Nyes</span>
            </a>
        </div>

        <nav>
            <a href="index.php" class="active">Início</a>
            <a href="#recursos">Recursos</a>
            <a href="#modulos">Módulos</a>
            <a href="login.php" class="btn btn-primary" style="padding:6px 16px;color:var(--black-dark);">Acessar Sistema →</a>
        </nav>
    </div>
</header>

<section id="homeSection" class="hero">
    <div class="hero-content">
        <div class="hero-text">
            <div class="hero-badge">
                ✨ Gestão Clínica 360° & LGPD
            </div>

            <h1>
                Gestão Inteligente para <span>Clínicas & Médicos</span>
            </h1>

            <p>
                Centralize o prontuário eletrônico, simplifique a recepção com agendamento ágil, ofereça autonomia ao paciente e emita receitas timbradas com total conformidade.
            </p>

            <div class="hero-buttons">
                <button class="btn btn-primary" onclick="window.location.href='login.php'" style="padding:14px 28px;font-size:1rem;">
                    Acessar Plataforma →
                </button>
                <button class="btn btn-secondary" onclick="document.getElementById('recursos').scrollIntoView({behavior:'smooth'})" style="padding:14px 24px;font-size:1rem;">
                    Conhecer Recursos ↓
                </button>
            </div>
        </div>

        <div class="hero-card">
            <h3>⚡ Principais Funcionalidades</h3>

            <div class="feature">
                <span style="font-size:1.3rem;">📅</span>
                <div>
                    <strong>Agenda Médica com Slots Inteligentes</strong>
                    <div style="font-size:0.85rem;color:var(--text-muted);">Defina disponibilidade e evite conflitos de horário</div>
                </div>
            </div>

            <div class="feature">
                <span style="font-size:1.3rem;">📋</span>
                <div>
                    <strong>Prontuário Clínico em Etapas</strong>
                    <div style="font-size:0.85rem;color:var(--text-muted);">Anamnese, sinais vitais, hipótese diagnóstica e conduta</div>
                </div>
            </div>

            <div class="feature">
                <span style="font-size:1.3rem;">💊</span>
                <div>
                    <strong>Receituário e Pedidos de Exames</strong>
                    <div style="font-size:0.85rem;color:var(--text-muted);">Prescrições prontas para impressão e compartilhamento</div>
                </div>
            </div>

            <div class="feature">
                <span style="font-size:1.3rem;">👥</span>
                <div>
                    <strong>Recepção Ágil sem Espera</strong>
                    <div style="font-size:0.85rem;color:var(--text-muted);">Confirmação de presença em 1 clique e fila sincronizada</div>
                </div>
            </div>

            <div class="feature">
                <span style="font-size:1.3rem;">🔒</span>
                <div>
                    <strong>Privacidade e Segurança LGPD</strong>
                    <div style="font-size:0.85rem;color:var(--text-muted);">Controle de acesso por perfil e histórico seguro</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- RECURSOS -->
<section id="recursos" class="container" style="padding:40px 0;">
    <div style="text-align:center;max-width:700px;margin:0 auto 40px auto;">
        <h2 style="font-size:2.2rem;font-weight:800;color:var(--black);margin-bottom:12px;">
            O que o TechNyes oferece para sua equipe?
        </h2>
        <p style="color:var(--text-muted);font-size:1.05rem;">
            Uma solução projetada para eliminar papelada, acelerar o atendimento e melhorar a experiência do paciente.
        </p>
    </div>

    <div class="grid" style="grid-template-columns:repeat(auto-fit, minmax(320px, 1fr));gap:24px;">
        <div class="card">
            <div style="font-size:2rem;margin-bottom:12px;">🩺</div>
            <h3>Para o Médico</h3>
            <p style="color:var(--text-muted);font-size:0.92rem;line-height:1.6;">
                Prontuário com navegação em etapas, cálculo automático de IMC, alertas de sinais vitais, histórico unificado e emissão instantânea de receitas e atestados prontos para impressão.
            </p>
        </div>

        <div class="card">
            <div style="font-size:2rem;margin-bottom:12px;">🛎️</div>
            <h3>Para a Secretária</h3>
            <p style="color:var(--text-muted);font-size:0.92rem;line-height:1.6;">
                Controle de recepção com confirmação de chegada sem recarregar a tela, agendamento de consultas com visualização de horários livres e busca rápida de pacientes.
            </p>
        </div>

        <div class="card">
            <div style="font-size:2rem;margin-bottom:12px;">📱</div>
            <h3>Para o Paciente</h3>
            <p style="color:var(--text-muted);font-size:0.92rem;line-height:1.6;">
                Portal exclusivo para agendar consultas por especialidade, visualizar suas receitas médicas, gerenciar consultas futuras e fazer upload de exames anexados.
            </p>
        </div>
    </div>
</section>

<!-- MÓDULOS / PERFIS -->
<section id="modulos" class="container" style="padding:20px 0 60px 0;">
    <div class="card" style="background:linear-gradient(135deg, var(--black-dark) 0%, var(--black) 100%);color:var(--white);padding:40px;border:none;">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:20px;">
            <div>
                <h3 style="color:var(--white);font-size:1.8rem;margin-bottom:8px;">Pronto para transformar sua clínica?</h3>
                <p style="color:#94a3b8;font-size:1rem;max-width:600px;">
                    Acesse sua conta para começar a atender ou agendar sua consulta hoje mesmo.
                </p>
            </div>
            <button class="btn btn-primary" onclick="window.location.href='login.php'" style="padding:14px 28px;font-size:1rem;">
                Entrar no Sistema →
            </button>
        </div>
    </div>
</section>

<footer style="background:var(--white);border-top:1px solid var(--border);padding:25px 0;text-align:center;color:var(--text-muted);font-size:0.88rem;">
    <div class="container">
        <p>© <?= date('Y') ?> <strong>TechNyes</strong> — Sistema Clínico Integrado. Todos os direitos reservados.</p>
    </div>
</footer>

<script src="assets/js/main.js"></script>
</body>
</html>