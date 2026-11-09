/**
 * NyesClinic - Utilitários Globais de Interface & Feedback
 */

// Criação do container de Toasts
function garantirToastContainer() {
    var c = document.getElementById('toastContainer');
    if (!c) {
        c = document.createElement('div');
        c.id = 'toastContainer';
        c.className = 'toast-container';
        document.body.appendChild(c);
    }
    return c;
}

window.showToast = function(mensagem, tipo, duracao) {
    tipo = tipo || 'info';
    duracao = duracao || 4000;

    var container = garantirToastContainer();
    var toast = document.createElement('div');
    toast.className = 'toast toast-' + tipo;

    var icones = {
        success: '✓',
        error: '✕',
        warning: '⚠',
        info: 'ℹ'
    };

    var icone = icones[tipo] || '•';

    toast.innerHTML = '<span style="font-size:1.1rem;font-weight:bold;margin-right:4px;">' + icone + '</span>' +
                      '<span style="flex:1;">' + mensagem + '</span>' +
                      '<button type="button" style="background:none;border:none;cursor:pointer;color:#888;font-size:1rem;margin-left:8px;" onclick="this.parentElement.remove()">✕</button>';

    container.appendChild(toast);

    setTimeout(function() {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(10px)';
        setTimeout(function() {
            if (toast.parentElement) toast.remove();
        }, 300);
    }, duracao);
};

// Modal Global Estilizado
window.abrirModal = function(titulo, htmlConteudo, botoesHtml) {
    window.fecharModal();

    var backdrop = document.createElement('div');
    backdrop.id = 'appModalBackdrop';
    backdrop.className = 'modal-backdrop';
    backdrop.onclick = function(e) {
        if (e.target === backdrop) window.fecharModal();
    };

    var modal = document.createElement('div');
    modal.className = 'modal-window';

    modal.innerHTML = 
        '<div class="modal-header">' +
            '<h3>' + titulo + '</h3>' +
            '<button type="button" style="background:none;border:none;font-size:1.4rem;cursor:pointer;color:#888;" onclick="window.fecharModal()">✕</button>' +
        '</div>' +
        '<div class="modal-body">' +
            htmlConteudo +
        '</div>' +
        '<div class="modal-footer">' +
            (botoesHtml || '<button class="btn btn-secondary" onclick="window.fecharModal()">Fechar</button>') +
        '</div>';

    backdrop.appendChild(modal);
    document.body.appendChild(backdrop);
};

window.fecharModal = function() {
    var modal = document.getElementById('appModalBackdrop');
    if (modal) modal.remove();
};

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && document.getElementById('appModalBackdrop')) {
        window.fecharModal();
    }
});

/* Imprime apenas o conteúdo de um elemento, em um iframe isolado
   (evita a página em branco causada pelo modal/visibility no @media print) */
window.imprimirElemento = function(elementId, titulo) {
    var el = document.getElementById(elementId);
    if (!el) return;

    var css =
        'body{font-family:"Segoe UI",Arial,sans-serif;color:#1e293b;margin:0;padding:24px;}' +
        '.print-prescription-card{padding:0;border:none;}' +
        '.print-header{display:flex;justify-content:space-between;align-items:center;border-bottom:2px solid #0f172a;padding-bottom:14px;margin-bottom:20px;}' +
        '.print-clinic-title{font-size:1.5rem;font-weight:800;color:#0f172a;}' +
        '.print-meta{font-size:.85rem;color:#64748b;}' +
        '.print-content-block{margin-bottom:24px;}' +
        '.print-content-block h4{font-size:.95rem;text-transform:uppercase;letter-spacing:.5px;margin:0 0 8px;border-bottom:1px dashed #cbd5e1;padding-bottom:4px;}' +
        '.print-footer{margin-top:60px;display:flex;justify-content:space-between;align-items:flex-end;padding-top:20px;border-top:1px solid #e2e8f0;}' +
        '.print-signature{text-align:center;width:250px;border-top:1px solid #0f172a;padding-top:6px;font-size:.85rem;font-weight:600;}' +
        '@page{size:A4;margin:12mm;}';

    var iframe = document.createElement('iframe');
    iframe.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;';
    document.body.appendChild(iframe);

    var doc = iframe.contentWindow.document;
    doc.open();
    doc.write('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' + (titulo || 'Documento') +
        '</title><style>' + css + '</style></head><body>' + el.outerHTML + '</body></html>');
    doc.close();

    setTimeout(function() {
        iframe.contentWindow.focus();
        iframe.contentWindow.print();
        setTimeout(function() { iframe.remove(); }, 1500);
    }, 250);
};

window.confirmarModal = function(titulo, mensagem, onConfirm) {
    var botoes = 
        '<button class="btn btn-secondary" onclick="window.fecharModal()">Cancelar</button>' +
        '<button class="btn btn-primary" id="btnModalConfirmar">Confirmar</button>';

    window.abrirModal(titulo, '<p>' + mensagem + '</p>', botoes);

    var btn = document.getElementById('btnModalConfirmar');
    if (btn) {
        btn.onclick = function() {
            window.fecharModal();
            if (typeof onConfirm === 'function') onConfirm();
        };
    }
};

function confirmarSaida() {
    window.confirmarModal('Confirmação', 'Deseja realmente sair do sistema?', function() {
        window.location.href = 'logout.php';
    });
}

/* ==============================
   VISUALIZAÇÃO DE DADOS DO USUÁRIO
============================== */

function escapeHtmlGlobal(str) {
    if (str === null || str === undefined || str === '') return '—';
    var div = document.createElement('div');
    div.textContent = String(str);
    return div.innerHTML;
}

function formatarCpfVisualizacao(cpf) {
    if (!cpf) return '—';
    var num = String(cpf).replace(/\D/g, '');
    if (num.length === 11) {
        return num.replace(/^(\d{3})(\d{3})(\d{3})(\d{2})$/, '$1.$2.$3-$4');
    }
    return escapeHtmlGlobal(cpf);
}

function formatarTelefoneVisualizacao(tel) {
    if (!tel) return '—';
    var num = String(tel).replace(/\D/g, '');
    if (num.length === 11) {
        return num.replace(/^(\d{2})(\d{5})(\d{4})$/, '($1) $2-$3');
    } else if (num.length === 10) {
        return num.replace(/^(\d{2})(\d{4})(\d{4})$/, '($1) $2-$3');
    }
    return escapeHtmlGlobal(tel);
}

function formatarCepVisualizacao(cep) {
    if (!cep) return '—';
    var num = String(cep).replace(/\D/g, '');
    if (num.length === 8) {
        return num.replace(/^(\d{5})(\d{3})$/, '$1-$2');
    }
    return escapeHtmlGlobal(cep);
}

function formatarDataVisualizacao(dataStr, calcularIdade) {
    if (!dataStr || dataStr === '0000-00-00') return '—';
    var partes = String(dataStr).split(' ')[0].split('-');
    if (partes.length !== 3) return escapeHtmlGlobal(dataStr);
    var formatada = partes[2] + '/' + partes[1] + '/' + partes[0];
    if (calcularIdade) {
        var hoje = new Date();
        var nasc = new Date(parseInt(partes[0], 10), parseInt(partes[1], 10) - 1, parseInt(partes[2], 10));
        var idade = hoje.getFullYear() - nasc.getFullYear();
        var m = hoje.getMonth() - nasc.getMonth();
        if (m < 0 || (m === 0 && hoje.getDate() < nasc.getDate())) {
            idade--;
        }
        if (idade >= 0 && idade < 130) {
            formatada += ' (' + idade + ' anos)';
        }
    }
    return formatada;
}

function montarItemDetalhe(label, valor) {
    var valLimpo = (valor === null || valor === undefined || String(valor).trim() === '') ? '—' : valor;
    return '<div class="user-detail-item">' +
               '<span class="detail-label">' + label + '</span>' +
               '<span class="detail-value">' + valLimpo + '</span>' +
           '</div>';
}

function renderizarModalUsuario(u, contextoPainel) {
    var labelsPerfil = {
        admin: 'Administrador',
        secretaria: 'Secretária',
        medico: 'Médico',
        paciente: 'Paciente'
    };
    var labelPerfil = labelsPerfil[u.perfil] || escapeHtmlGlobal(u.perfil);
    var inicial = (u.nome && u.nome.trim().length > 0) ? u.nome.trim().charAt(0).toUpperCase() : 'U';
    var statusBadge = (u.status === 'ativo')
        ? '<span class="status confirmed">Ativo</span>'
        : '<span class="status">Inativo</span>';

    var clinicaNome = u.nome_clinica ? escapeHtmlGlobal(u.nome_clinica) : (u.perfil === 'admin' ? 'Admin Master' : '—');
    var sexoLabel = u.sexo === 'M' ? 'Masculino' : (u.sexo === 'F' ? 'Feminino' : '—');

    var html = '';

    /* Cabeçalho do Usuário */
    html += '<div class="user-detail-header">' +
                '<div class="user-detail-avatar">' + escapeHtmlGlobal(inicial) + '</div>' +
                '<div style="flex:1;min-width:0;">' +
                    '<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">' +
                        '<h4 style="margin:0;font-size:1.15rem;color:var(--black);">' + escapeHtmlGlobal(u.nome) + '</h4>' +
                        statusBadge +
                    '</div>' +
                    '<div style="font-size:0.85rem;color:var(--text-muted);margin-top:4px;">' +
                        'Perfil: <strong>' + labelPerfil + '</strong>' +
                        (clinicaNome !== '—' ? ' &bull; Clínica: <strong>' + clinicaNome + '</strong>' : '') +
                        (u.data_cadastro ? ' &bull; Cadastrado em: ' + formatarDataVisualizacao(u.data_cadastro, false) : '') +
                    '</div>' +
                '</div>' +
            '</div>';

    /* Dados de Acesso e Contato */
    html += '<div class="user-detail-section">' +
                '<div class="user-detail-section-title">Dados Gerais e Contato</div>' +
                '<div class="user-detail-grid">' +
                    montarItemDetalhe('E-mail', escapeHtmlGlobal(u.email)) +
                    montarItemDetalhe('CPF', formatarCpfVisualizacao(u.cpf)) +
                    montarItemDetalhe('RG', escapeHtmlGlobal(u.rg)) +
                    montarItemDetalhe('Data de Nascimento', formatarDataVisualizacao(u.data_nascimento, true)) +
                    montarItemDetalhe('Sexo', sexoLabel) +
                    montarItemDetalhe('Telefone', formatarTelefoneVisualizacao(u.telefone)) +
                    montarItemDetalhe('Celular', formatarTelefoneVisualizacao(u.celular)) +
                '</div>' +
            '</div>';

    /* Dados Profissionais (Médico) */
    if (u.perfil === 'medico') {
        var crmCompleto = u.crm ? (escapeHtmlGlobal(u.crm) + (u.uf_crm ? '/' + escapeHtmlGlobal(u.uf_crm) : '')) : '—';
        var horarioAtd = (u.horario_inicio || u.horario_fim)
            ? ((u.horario_inicio ? escapeHtmlGlobal(u.horario_inicio.substring(0, 5)) : '—') + ' às ' + (u.horario_fim ? escapeHtmlGlobal(u.horario_fim.substring(0, 5)) : '—'))
            : '—';
        var duracao = u.duracao_consulta ? (escapeHtmlGlobal(u.duracao_consulta) + ' min') : '30 min';

        html += '<div class="user-detail-section">' +
                    '<div class="user-detail-section-title">Dados Profissionais (Médico)</div>' +
                    '<div class="user-detail-grid">' +
                        montarItemDetalhe('CRM', crmCompleto) +
                        montarItemDetalhe('Especialidade', escapeHtmlGlobal(u.especialidade)) +
                        montarItemDetalhe('Horário Padrão', horarioAtd) +
                        montarItemDetalhe('Duração da Consulta', duracao) +
                    '</div>' +
                '</div>';
    }

    /* Endereço, Convênio e Emergência (Paciente) */
    if (u.perfil === 'paciente') {
        var endCompleto = u.endereco ? escapeHtmlGlobal(u.endereco) : '';
        if (u.numero) endCompleto += (endCompleto ? ', ' : '') + escapeHtmlGlobal(u.numero);
        if (u.complemento) endCompleto += (endCompleto ? ' (' + escapeHtmlGlobal(u.complemento) + ')' : escapeHtmlGlobal(u.complemento));

        var cidadeUf = u.cidade ? escapeHtmlGlobal(u.cidade) : '';
        if (u.estado) cidadeUf += (cidadeUf ? ' - ' : '') + escapeHtmlGlobal(u.estado);

        html += '<div class="user-detail-section">' +
                    '<div class="user-detail-section-title">Endereço</div>' +
                    '<div class="user-detail-grid">' +
                        montarItemDetalhe('CEP', formatarCepVisualizacao(u.cep)) +
                        montarItemDetalhe('Logradouro / Nº', endCompleto || '—') +
                        montarItemDetalhe('Bairro', escapeHtmlGlobal(u.bairro)) +
                        montarItemDetalhe('Cidade / UF', cidadeUf || '—') +
                    '</div>' +
                '</div>';

        html += '<div class="user-detail-section">' +
                    '<div class="user-detail-section-title">Convênio e Contato de Emergência</div>' +
                    '<div class="user-detail-grid">' +
                        montarItemDetalhe('Convênio', escapeHtmlGlobal(u.convenio)) +
                        montarItemDetalhe('Nº da Carteirinha', escapeHtmlGlobal(u.numero_carteirinha)) +
                        montarItemDetalhe('Contato de Emergência', escapeHtmlGlobal(u.contato_emergencia_nome)) +
                        montarItemDetalhe('Telefone de Emergência', formatarTelefoneVisualizacao(u.contato_emergencia_telefone)) +
                    '</div>' +
                '</div>';
    }

    /* Botões do rodapé */
    var urlEditar = 'editar_usuario.php?id=' + encodeURIComponent(u.id_usuario);
    if (contextoPainel === 'secretaria') {
        var abaRef = (u.perfil === 'paciente') ? 'pacientes' : 'medicos';
        urlEditar = 'editar_usuario.php?aba=' + abaRef + '&id=' + encodeURIComponent(u.id_usuario);
    }

    var botoes = '<button type="button" class="btn btn-secondary" onclick="window.fecharModal()">Fechar</button>' +
                 '<button type="button" class="btn btn-primary" onclick="window.location.href=\'' + urlEditar + '\'">✎ Editar Dados</button>';

    window.abrirModal('Dados do Usuário', html, botoes);
}

window.visualizarDadosUsuario = function(idUsuario, contextoPainel) {
    if (!idUsuario) return;
    var painel = contextoPainel || (window.location.pathname.indexOf('secretaria.php') !== -1 ? 'secretaria' : 'admin');

    if (window.usuariosDadosMap && window.usuariosDadosMap[idUsuario]) {
        renderizarModalUsuario(window.usuariosDadosMap[idUsuario], painel);
        return;
    }

    window.abrirModal('Dados do Usuário', '<p style="color:var(--text-muted);text-align:center;padding:20px 0;">Carregando informações do usuário...</p>');

    fetch('api/visualizar_usuario.php?id=' + encodeURIComponent(idUsuario), {
        headers: { 'Accept': 'application/json' }
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data && data.sucesso && data.usuario) {
            window.usuariosDadosMap = window.usuariosDadosMap || {};
            window.usuariosDadosMap[idUsuario] = data.usuario;
            renderizarModalUsuario(data.usuario, painel);
        } else {
            window.abrirModal('Aviso', '<p style="color:var(--danger);">' + escapeHtmlGlobal((data && data.erro) || 'Não foi possível carregar os dados do usuário.') + '</p>');
        }
    })
    .catch(function() {
        window.abrirModal('Erro', '<p style="color:var(--danger);">Erro de comunicação ao buscar os dados do usuário.</p>');
    });
};