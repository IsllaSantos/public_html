/* ==============================
   TROCA DE ABAS
============================== */

function showAdminTab(tab, event) {

    /* Esconde todas */
    document.querySelectorAll('.admin-tab')
    .forEach(function(item) {
        item.style.display = 'none';
    });

    /* Mostra a selecionada */
    var el = document.getElementById(tab);
    if (el) {
        el.style.display = 'block';
    }

    /* Destaca o botão ativo */
    document.querySelectorAll('header nav button, nav button')
    .forEach(function(btn) {
        btn.classList.remove('active', 'nav-active');
        var oc = btn.getAttribute('onclick') || '';
        if (oc.indexOf("showAdminTab('" + tab + "'") !== -1) {
            btn.classList.add('active');
        }
    });

    if (tab === 'usuarios') {
        prevenirAutofillNovoUsuario();
    }
}


/* ==============================
   INIT — RESPEITA ?aba= DA URL
============================== */

window.onload = function() {

    /* Lê o parâmetro ?aba= */
    var params = new URLSearchParams(window.location.search);
    var abaInicial = params.get('aba') || 'dashboard';

    /* Esconde todas */
    document.querySelectorAll('.admin-tab')
    .forEach(function(item) {
        item.style.display = 'none';
    });

    /* Mostra a aba correta */
    var el = document.getElementById(abaInicial);
    if (el) {
        el.style.display = 'block';
    } else {
        document.getElementById('dashboard')
        .style.display = 'block';
        abaInicial = 'dashboard';
    }

    /* Destaca o botão correto no nav */
    document.querySelectorAll('header nav button, nav button')
    .forEach(function(btn) {
        btn.classList.remove('active', 'nav-active');
        var oc = btn.getAttribute('onclick') || '';
        if (oc.indexOf("showAdminTab('" + abaInicial + "'") !== -1) {
            btn.classList.add('active');
        }
    });

    /* Aplica máscaras */
    aplicarMascaraCNPJ();
    aplicarMascaraTelefone();
    aplicarMascaraCPF();
    aplicarBuscaCEP();

    /* Campos condicionais do perfil */
    alternarCamposPerfil();

    /* Previne autofill com credenciais salvas no formulário de novo usuário */
    prevenirAutofillNovoUsuario();
};


/* ==============================
   PREVENÇÃO DE AUTOFILL (NOVO USUÁRIO)
============================== */

function prevenirAutofillNovoUsuario() {
    var form = document.querySelector('form[action="api/cadastro_usuario.php"]');
    if (!form) return;

    var email = form.querySelector('input[name="email"]');
    var senha = form.querySelector('input[name="senha"]');

    function limpar() {
        if (email && !email.dataset.userInteracted) email.value = '';
        if (senha && !senha.dataset.userInteracted) senha.value = '';
    }

    [email, senha].forEach(function(input) {
        if (!input || input.dataset.autofillBound) return;
        input.dataset.autofillBound = 'true';
        ['input', 'focus', 'keydown'].forEach(function(evt) {
            input.addEventListener(evt, function() {
                input.dataset.userInteracted = 'true';
            });
        });
    });

    limpar();
    setTimeout(limpar, 60);
    setTimeout(limpar, 200);
    setTimeout(limpar, 500);
}


/* ==============================
   MAPEAMENTO ABA -> LABEL
============================== */

function getTabLabel(tab) {

    var map = {
        'dashboard':       'dashboard',
        'clinicas':        'clínicas',
        'usuarios':        'usuários',
        'planos':          'planos',
        'financeiro':      'financeiro',
        'especialidades':  'especialidades',
        'logs':            'logs'
    };

    return map[tab] || tab;
}


/* ==============================
   MÁSCARA DE CNPJ
   XX.XXX.XXX/XXXX-XX
============================== */

function aplicarMascaraCNPJ() {

    var campos = document.querySelectorAll('.mask-cnpj');

    campos.forEach(function(campo) {

        campo.addEventListener('input', function(e) {

            var v = e.target.value
                .replace(/\D/g, '')
                .substring(0, 14);

            if (v.length > 12) {
                v = v.replace(
                    /^(\d{2})(\d{3})(\d{3})(\d{4})(\d)/,
                    '$1.$2.$3/$4-$5'
                );

            } else if (v.length > 8) {
                v = v.replace(
                    /^(\d{2})(\d{3})(\d{3})(\d)/,
                    '$1.$2.$3/$4'
                );

            } else if (v.length > 5) {
                v = v.replace(
                    /^(\d{2})(\d{3})(\d)/,
                    '$1.$2.$3'
                );

            } else if (v.length > 2) {
                v = v.replace(
                    /^(\d{2})(\d)/,
                    '$1.$2'
                );
            }

            e.target.value = v;
        });
    });
}


/* ==============================
   MÁSCARA DE TELEFONE
   (XX) XXXXX-XXXX
============================== */

function aplicarMascaraTelefone() {

    var campos = document.querySelectorAll('.mask-telefone');

    campos.forEach(function(campo) {

        campo.addEventListener('input', function(e) {

            var v = e.target.value
                .replace(/\D/g, '')
                .substring(0, 11);

            if (v.length > 6) {
                v = v.replace(
                    /^(\d{2})(\d{5})(\d)/,
                    '($1) $2-$3'
                );

            } else if (v.length > 2) {
                v = v.replace(
                    /^(\d{2})(\d)/,
                    '($1) $2'
                );
            }

            e.target.value = v;
        });
    });
}


/* ==============================
   BUSCA CEP — VIA CEP
============================== */

function aplicarBuscaCEP() {

    var campos = document.querySelectorAll('.mask-cep');

    campos.forEach(function(campo) {

        campo.addEventListener('blur', function(e) {

            var cep = e.target.value.replace(/\D/g, '');

            if (cep.length !== 8) return;

            /* Encontra o form pai */
            var form = e.target.closest('form');
            if (!form) return;

            fetch('https://viacep.com.br/ws/' + cep + '/json/')
            .then(function(resp) {
                return resp.json();
            })
            .then(function(data) {

                if (data.erro) return;

                var endereco = form.querySelector('[name="endereco"]');
                var bairro   = form.querySelector('[name="bairro"]');
                var cidade   = form.querySelector('[name="cidade"]');
                var estado   = form.querySelector('[name="estado"]');

                if (endereco) endereco.value = data.logradouro || '';
                if (bairro)   bairro.value   = data.bairro || '';
                if (cidade)   cidade.value   = data.localidade || '';
                if (estado)   estado.value   = data.uf || '';
            })
            .catch(function() {
                /* Silencioso se falhar */
            });
        });

        /* Máscara de CEP: XXXXX-XXX */
        campo.addEventListener('input', function(e) {

            var v = e.target.value
                .replace(/\D/g, '')
                .substring(0, 8);

            if (v.length > 5) {
                v = v.replace(
                    /^(\d{5})(\d)/,
                    '$1-$2'
                );
            }

            e.target.value = v;
        });
    });
}


/* ==============================
   CONFIRMAÇÃO DE EXCLUSÃO
============================== */

function confirmarExclusao(id, nome) {

    var confirmou = confirm(
        'Tem certeza que deseja excluir a clínica "' +
        nome +
        '"?\n\nEsta ação não pode ser desfeita.'
    );

    if (confirmou) {

        var form = document.createElement('form');
        form.method = 'POST';
        form.action = 'api/excluir_clinica.php';

        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'id_clinica';
        input.value = id;

        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
    }
}


/* ==============================
   CONFIRMAÇÃO DE EXCLUSÃO
   DE USUÁRIO
============================== */

function confirmarExclusaoUsuario(id, nome) {

    var confirmou = confirm(
        'Tem certeza que deseja excluir o usuário "' +
        nome +
        '"?\n\nEsta ação não pode ser desfeita.'
    );

    if (confirmou) {

        var form = document.createElement('form');
        form.method = 'POST';
        form.action = 'api/excluir_usuario.php';

        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'id_usuario';
        input.value = id;

        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
    }
}


/* ==============================
   CAMPOS CONDICIONAIS POR PERFIL
============================== */

function alternarCamposPerfil() {

    var select = document.getElementById('select-perfil');
    if (!select) return;

    var perfil = select.value;

    /* Seções condicionais */
    var clinica  = document.querySelectorAll('.campos-clinica');
    var pessoais = document.getElementById('campos-pessoais');
    var medico   = document.getElementById('campos-medico');
    var paciente = document.getElementById('campos-paciente');

    /* Esconde tudo */
    clinica.forEach(function(el) {
        el.style.display = 'none';
    });

    if (pessoais) pessoais.style.display = 'none';
    if (medico)   medico.style.display   = 'none';
    if (paciente) paciente.style.display = 'none';

    /* Mostra de acordo com o perfil */

    if (perfil === 'secretaria') {

        clinica.forEach(function(el) {
            el.style.display = '';
        });
        if (pessoais) pessoais.style.display = 'block';
    }

    if (perfil === 'medico') {

        clinica.forEach(function(el) {
            el.style.display = '';
        });
        if (pessoais) pessoais.style.display = 'block';
        if (medico)   medico.style.display   = 'block';
    }

    if (perfil === 'paciente') {

        clinica.forEach(function(el) {
            el.style.display = '';
        });
        if (pessoais) pessoais.style.display = 'block';
        if (paciente) paciente.style.display = 'block';
    }

    /* Dynamic required validation for medico specific fields */
    var selectEspec = document.getElementById('select-especialidade');
    if (selectEspec) {
        selectEspec.required = (perfil === 'medico');
    }

    /* Re-aplica máscaras nos campos novos */
    aplicarMascaraTelefone();
    aplicarMascaraCPF();
    aplicarBuscaCEP();
}


/* ==============================
   MÁSCARA DE CPF
   XXX.XXX.XXX-XX
============================== */

function aplicarMascaraCPF() {

    var campos = document.querySelectorAll('.mask-cpf');

    campos.forEach(function(campo) {

        /* Evita duplicar listener */
        if (campo.dataset.maskApplied) return;
        campo.dataset.maskApplied = 'true';

        campo.addEventListener('input', function(e) {

            var v = e.target.value
                .replace(/\D/g, '')
                .substring(0, 11);

            if (v.length > 9) {
                v = v.replace(
                    /^(\d{3})(\d{3})(\d{3})(\d)/,
                    '$1.$2.$3-$4'
                );

            } else if (v.length > 6) {
                v = v.replace(
                    /^(\d{3})(\d{3})(\d)/,
                    '$1.$2.$3'
                );

            } else if (v.length > 3) {
                v = v.replace(
                    /^(\d{3})(\d)/,
                    '$1.$2'
                );
            }

            e.target.value = v;
        });
    });
}


/* ==============================
   CONFIRMAÇÃO DE EXCLUSÃO
   DE PLANO
============================== */

function confirmarExclusaoPlano(id, nome) {

    var confirmou = confirm(
        'Tem certeza que deseja excluir o plano "' +
        nome +
        '"?\n\nEsta ação não pode ser desfeita.'
    );

    if (confirmou) {

        var form = document.createElement('form');
        form.method = 'POST';
        form.action = 'api/excluir_plano.php';

        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'id_plano';
        input.value = id;

        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
    }
}


/* ==============================
   AUTO-PREENCHER VALOR DO PLANO
   NA ASSINATURA
============================== */

function preencherValorPlano() {

    var select = document.getElementById('select-plano-assinatura');
    var campo = document.getElementById('valor-assinatura');

    if (!select || !campo) return;

    var opcao = select.options[select.selectedIndex];
    var valor = opcao.getAttribute('data-valor');

    if (valor) {
        campo.value = valor;
    } else {
        campo.value = '';
    }
}


/* ==============================
   CONFIRMAÇÃO DE EXCLUSÃO
   DE ESPECIALIDADE
============================== */

function confirmarExclusaoEspecialidade(id, nome) {

    var confirmou = confirm(
        'Tem certeza que deseja excluir a especialidade "' +
        nome +
        '"?\n\nEsta ação não pode ser desfeita.'
    );

    if (confirmou) {

        var form = document.createElement('form');
        form.method = 'POST';
        form.action = 'api/excluir_especialidade.php';

        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'id_especialidade';
        input.value = id;

        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
    }
}