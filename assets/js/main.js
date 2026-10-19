/**
 * TechNyes - Utilitários Globais de Interface & Feedback
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