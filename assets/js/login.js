function validarLogin() {
    var email = document.getElementById('email').value.trim();
    var senha = document.getElementById('senha').value.trim();

    if (email === '') {
        if (window.showToast) {
            window.showToast('Por favor, informe seu e-mail.', 'warning');
        } else {
            alert('Informe o e-mail');
        }
        document.getElementById('email').focus();
        return false;
    }

    if (senha === '') {
        if (window.showToast) {
            window.showToast('Por favor, digite sua senha.', 'warning');
        } else {
            alert('Informe a senha');
        }
        document.getElementById('senha').focus();
        return false;
    }

    return true;
}

function toggleVisibilidadeSenha() {
    var campo = document.getElementById('senha');
    var btn = document.getElementById('btnToggleSenha');
    if (!campo) return;

    if (campo.type === 'password') {
        campo.type = 'text';
        btn.textContent = '🔒';
    } else {
        campo.type = 'password';
        btn.textContent = '👁';
    }
}