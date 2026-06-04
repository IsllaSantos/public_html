function validarLogin() {

    const email =
    document.getElementById('email').value;

    const senha =
    document.getElementById('senha').value;

    if(email.trim() === ''){

        alert('Informe o e-mail');
        return false;
    }

    if(senha.trim() === ''){

        alert('Informe a senha');
        return false;
    }

    return true;
}