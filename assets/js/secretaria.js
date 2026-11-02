/* ==============================
   TROCA DE ABAS — SECRETARIA
============================== */

function showSecretariaTab(tabId) {
    // Cada aba busca apenas os próprios dados no servidor; ao trocar, recarrega com ?aba=
    var atual = new URLSearchParams(window.location.search).get('aba') || 'dashboard';
    if (atual === tabId) return;

    var navBtns = document.querySelectorAll('header nav button');
    navBtns.forEach(function(b) {
        b.classList.remove('active');
        var oc = b.getAttribute('onclick') || '';
        if (oc.indexOf("showSecretariaTab('" + tabId + "')") !== -1) {
            b.classList.add('active');
        }
    });

    window.location.href = 'secretaria.php?aba=' + encodeURIComponent(tabId);
}


/* ==============================
   AÇÕES RÁPIDAS ASSÍNCRONAS (AJAX)
============================== */

function alterarStatusConsulta(idConsulta, novoStatus, btnElement, aba) {
    if (!idConsulta || !novoStatus) return;

    if (novoStatus === 'cancelada') {
        if (!confirm('Tem certeza que deseja cancelar esta consulta?')) {
            return;
        }
    }

    var originalText = btnElement ? btnElement.innerHTML : '';
    if (btnElement) {
        btnElement.disabled = true;
        btnElement.innerHTML = 'Processando...';
    }

    var formData = new FormData();
    formData.append('id_consulta', idConsulta);
    formData.append('status', novoStatus);
    formData.append('ajax', '1');
    formData.append('aba', aba || 'dashboard');

    fetch('api/secretaria_status_consulta.php', {
        method: 'POST',
        headers: { 'Accept': 'application/json' },
        body: formData
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.sucesso) {
            if (window.showToast) {
                window.showToast(data.mensagem, 'success');
            }

            // Atualiza linha na tabela sem recarregar tela
            var row = btnElement ? btnElement.closest('tr') : null;
            if (row) {
                // Coluna de status
                var statusCell = row.querySelector('td:nth-last-child(2)');
                var actionsCell = row.querySelector('td:last-child');

                if (statusCell) {
                    if (novoStatus === 'aguardando_atendimento') {
                        statusCell.innerHTML = '<span class="status status-aguardando">Aguardando Atendimento</span>';
                    } else if (novoStatus === 'cancelada') {
                        statusCell.innerHTML = '<span class="status status-cancelada">Cancelada</span>';
                    }
                }

                if (actionsCell) {
                    actionsCell.innerHTML = '<span style="color:#888;font-size:0.85rem;">—</span>';
                }
            }

            // Atualiza contadores numéricos do dashboard se existirem
            atualizarContadoresDashboard(novoStatus);

        } else {
            if (window.showToast) {
                window.showToast(data.erro || 'Não foi possível atualizar o status.', 'error');
            } else {
                alert(data.erro || 'Erro ao atualizar.');
            }
            if (btnElement) {
                btnElement.disabled = false;
                btnElement.innerHTML = originalText;
            }
        }
    })
    .catch(function(err) {
        console.error(err);
        if (window.showToast) {
            window.showToast('Erro de comunicação com o servidor.', 'error');
        }
        if (btnElement) {
            btnElement.disabled = false;
            btnElement.innerHTML = originalText;
        }
    });
}

function atualizarContadoresDashboard(novoStatus) {
    var cards = document.querySelectorAll('.stats-row .card div[style*="font-size:2rem"]');
    if (cards.length >= 4) {
        // cards[1] = Pendentes, cards[3] = Aguardando
        var pendentesEl = cards[1];
        var aguardandoEl = cards[3];

        if (pendentesEl) {
            var valP = parseInt(pendentesEl.textContent.trim(), 10) || 0;
            if (valP > 0) pendentesEl.textContent = valP - 1;
        }

        if (novoStatus === 'aguardando_atendimento' && aguardandoEl) {
            var valA = parseInt(aguardandoEl.textContent.trim(), 10) || 0;
            aguardandoEl.textContent = valA + 1;
        }
    }
}


/* ==============================
   FILTRO RÁPIDO DE TABELAS
============================== */

function filtrarTabelaSecretaria(inputId, tbodyId) {
    var termo = document.getElementById(inputId).value.toLowerCase().trim();
    var linhas = document.querySelectorAll('#' + tbodyId + ' tr');

    linhas.forEach(function(linha) {
        var textoLinha = linha.textContent.toLowerCase();
        linha.style.display = (textoLinha.indexOf(termo) !== -1) ? '' : 'none';
    });
}

function filtrarSelectPacientes(inputElement) {
    var termo = inputElement.value.toLowerCase().trim();
    var select = document.getElementById('selectPacienteAgendamento');
    if (!select) return;

    for (var i = 0; i < select.options.length; i++) {
        var opt = select.options[i];
        if (opt.value === '') continue;
        var match = opt.text.toLowerCase().indexOf(termo) !== -1;
        opt.style.display = match ? '' : 'none';
    }
}


/* ==============================
   MÁSCARA DE TELEFONE
============================== */

function aplicarMascaraTelefone() {
    var campos = document.querySelectorAll('.mask-telefone');
    for (var i = 0; i < campos.length; i++) {
        (function(campo) {
            if (campo.dataset.maskApplied) return;
            campo.dataset.maskApplied = 'true';

            campo.addEventListener('input', function(e) {
                var v = e.target.value.replace(/\D/g, '').substring(0, 11);
                if (v.length > 6) {
                    v = v.replace(/^(\d{2})(\d{5})(\d)/, '($1) $2-$3');
                } else if (v.length > 2) {
                    v = v.replace(/^(\d{2})(\d)/, '($1) $2');
                }
                e.target.value = v;
            });
        })(campos[i]);
    }
}


/* ==============================
   MÁSCARA DE CPF
============================== */

function aplicarMascaraCPF() {
    var campos = document.querySelectorAll('.mask-cpf');
    for (var i = 0; i < campos.length; i++) {
        (function(campo) {
            if (campo.dataset.maskApplied) return;
            campo.dataset.maskApplied = 'true';

            campo.addEventListener('input', function(e) {
                var v = e.target.value.replace(/\D/g, '').substring(0, 11);
                if (v.length > 9) {
                    v = v.replace(/^(\d{3})(\d{3})(\d{3})(\d)/, '$1.$2.$3-$4');
                } else if (v.length > 6) {
                    v = v.replace(/^(\d{3})(\d{3})(\d)/, '$1.$2.$3');
                } else if (v.length > 3) {
                    v = v.replace(/^(\d{3})(\d)/, '$1.$2');
                }
                e.target.value = v;
            });
        })(campos[i]);
    }
}


/* ==============================
   AGENDAMENTO — CASCATA INTUITIVA (ESTILO PACIENTE)
============================== */

function escapeHtml(text) {
    if (!text) return '';
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function secretariaCarregarMedicos() {
    var espEl = document.getElementById('agEspecialidade');
    var especialidade = espEl ? espEl.value : '';
    var selectMedico = document.getElementById('agMedico');
    var selectData   = document.getElementById('agData');
    var selectHora   = document.getElementById('agHorario');
    var btnAgendar   = document.getElementById('btnAgendarSecretaria');
    var chipsContainer = document.getElementById('slotsChipsContainer');

    if (!selectMedico) return;

    selectMedico.innerHTML = '<option value="">Carregando médicos...</option>';
    selectMedico.disabled = true;

    if (selectData) {
        selectData.innerHTML = '<option value="">Selecione o médico primeiro...</option>';
        selectData.disabled = true;
    }
    if (selectHora) {
        selectHora.innerHTML = '<option value="">Selecione a data primeiro...</option>';
        selectHora.disabled = true;
    }
    if (btnAgendar) btnAgendar.disabled = true;
    if (chipsContainer) chipsContainer.innerHTML = '';

    fetch('api/secretaria_agenda_slots.php?acao=medicos&especialidade=' + encodeURIComponent(especialidade))
    .then(function(r) { return r.json(); })
    .then(function(medicos) {
        if (!medicos || medicos.length === 0) {
            selectMedico.innerHTML = '<option value="">Nenhum médico disponível nesta especialidade</option>';
            return;
        }

        var html = '<option value="">Selecione o médico...</option>';
        medicos.forEach(function(m) {
            html += '<option value="' + m.id_usuario + '">' +
                    escapeHtml(m.nome) + (m.especialidade ? ' — ' + escapeHtml(m.especialidade) : '') + '</option>';
        });

        selectMedico.innerHTML = html;
        selectMedico.disabled = false;
    })
    .catch(function() {
        selectMedico.innerHTML = '<option value="">Erro ao carregar médicos</option>';
    });
}

function secretariaCarregarDatas() {
    var medEl = document.getElementById('agMedico');
    var id_medico = medEl ? medEl.value : '';
    var selectData = document.getElementById('agData');
    var selectHora = document.getElementById('agHorario');
    var btnAgendar = document.getElementById('btnAgendarSecretaria');
    var chipsContainer = document.getElementById('slotsChipsContainer');

    if (selectData) {
        selectData.innerHTML = '<option value="">Carregando datas...</option>';
        selectData.disabled = true;
    }
    if (selectHora) {
        selectHora.innerHTML = '<option value="">Selecione a data primeiro...</option>';
        selectHora.disabled = true;
    }
    if (btnAgendar) btnAgendar.disabled = true;
    if (chipsContainer) chipsContainer.innerHTML = '';

    if (!id_medico) {
        if (selectData) selectData.innerHTML = '<option value="">Selecione o médico primeiro...</option>';
        return;
    }

    fetch('api/secretaria_agenda_slots.php?acao=datas&id_medico=' + id_medico)
    .then(function(r) { return r.json(); })
    .then(function(datas) {
        if (!datas || datas.length === 0) {
            if (selectData) {
                selectData.innerHTML = '<option value="">Nenhuma data disponível para este médico</option>';
                selectData.disabled = true;
            }
            if (chipsContainer) {
                chipsContainer.innerHTML = '<p style="color:var(--text-muted);font-size:0.88rem;padding:6px 0;">⚠ Este médico não possui horários cadastrados em "Minha Agenda".</p>';
            }
            return;
        }

        var diasPT = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
        var html = '<option value="">Selecione a data...</option>';
        datas.forEach(function(d) {
            var partes = d.split('-');
            var dt = new Date(parseInt(partes[0], 10), parseInt(partes[1], 10) - 1, parseInt(partes[2], 10));
            var diaSemana = diasPT[dt.getDay()];
            var label = partes[2] + '/' + partes[1] + '/' + partes[0] + ' (' + diaSemana + ')';
            html += '<option value="' + d + '">' + label + '</option>';
        });

        if (selectData) {
            selectData.innerHTML = html;
            selectData.disabled = false;
        }
    })
    .catch(function() {
        if (selectData) selectData.innerHTML = '<option value="">Erro ao carregar datas</option>';
    });
}

function secretariaCarregarHorarios() {
    var medEl = document.getElementById('agMedico');
    var dataEl = document.getElementById('agData');
    var id_medico = medEl ? medEl.value : '';
    var data      = dataEl ? dataEl.value : '';
    var selectHora = document.getElementById('agHorario');
    var btnAgendar = document.getElementById('btnAgendarSecretaria');
    var chipsContainer = document.getElementById('slotsChipsContainer');

    if (selectHora) {
        selectHora.innerHTML = '<option value="">Carregando horários...</option>';
        selectHora.disabled = true;
    }
    if (btnAgendar) btnAgendar.disabled = true;
    if (chipsContainer) {
        chipsContainer.innerHTML = '<p style="color:#888;font-size:0.85rem;">Carregando horários disponíveis...</p>';
    }

    if (!id_medico || !data) {
        if (selectHora) selectHora.innerHTML = '<option value="">Selecione a data primeiro...</option>';
        if (chipsContainer) chipsContainer.innerHTML = '';
        return;
    }

    fetch('api/secretaria_agenda_slots.php?id_medico=' + id_medico + '&data=' + encodeURIComponent(data))
    .then(function(r) { return r.json(); })
    .then(function(slots) {
        if (!slots || slots.length === 0) {
            if (selectHora) {
                selectHora.innerHTML = '<option value="">Nenhum horário disponível nesta data</option>';
            }
            if (chipsContainer) {
                chipsContainer.innerHTML = '<p style="color:var(--text-muted);font-size:0.88rem;padding:6px 0;">⚠ Nenhum horário livre para este médico na data selecionada.</p>';
            }
            return;
        }

        var html = '<option value="">Selecione o horário...</option>';
        slots.forEach(function(h) {
            html += '<option value="' + h + '">' + h + '</option>';
        });

        if (selectHora) {
            selectHora.innerHTML = html;
            selectHora.disabled = false;

            selectHora.onchange = function() {
                var val = selectHora.value;
                if (btnAgendar) btnAgendar.disabled = !val;
                document.querySelectorAll('.slot-chip').forEach(function(c) {
                    c.classList.toggle('selected', c.dataset.hora === val);
                });
            };
        }

        if (chipsContainer) {
            var chipsHtml = '<div class="slots-wrapper" style="margin-top:6px;">';
            chipsHtml += '<div class="slots-period-title" style="font-weight:600;margin-bottom:8px;font-size:0.9rem;">⏱️ Horários Disponíveis:</div>';
            chipsHtml += '<div class="slots-grid" style="display:flex;flex-wrap:wrap;gap:8px;">';
            slots.forEach(function(hora) {
                chipsHtml += '<div class="slot-chip" data-hora="' + hora + '" onclick="selecionarSlotChip(this, \'' + hora + '\')">' + hora + '</div>';
            });
            chipsHtml += '</div></div>';
            chipsContainer.innerHTML = chipsHtml;
        }
    })
    .catch(function() {
        if (selectHora) selectHora.innerHTML = '<option value="">Erro ao carregar horários</option>';
        if (chipsContainer) chipsContainer.innerHTML = '<p style="color:var(--danger);font-size:0.85rem;">Erro ao carregar horários.</p>';
    });
}

function selecionarSlotChip(chipEl, hora) {
    document.querySelectorAll('.slot-chip').forEach(function(c) {
        c.classList.remove('selected');
    });
    chipEl.classList.add('selected');

    var sel = document.getElementById('agHorario');
    if (sel) {
        sel.value = hora;
        var btnAgendar = document.getElementById('btnAgendarSecretaria');
        if (btnAgendar) btnAgendar.disabled = !hora;
    }
}



/* ==============================
   BUSCA CEP — VIA CEP
============================== */

function aplicarBuscaCEP() {
    var campos = document.querySelectorAll('.mask-cep');

    campos.forEach(function(campo) {
        if (campo.dataset.cepApplied) return;
        campo.dataset.cepApplied = 'true';

        campo.addEventListener('blur', function(e) {
            var cep = e.target.value.replace(/\D/g, '');
            if (cep.length !== 8) return;

            var form = e.target.closest('form');
            if (!form) return;

            fetch('https://viacep.com.br/ws/' + cep + '/json/')
            .then(function(resp) { return resp.json(); })
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
            .catch(function() {});
        });

        campo.addEventListener('input', function(e) {
            var v = e.target.value.replace(/\D/g, '').substring(0, 8);
            if (v.length > 5) {
                v = v.replace(/^(\d{5})(\d)/, '$1-$2');
            }
            e.target.value = v;
        });
    });
}

/* ==============================
   PREVENÇÃO DE AUTOFILL (NOVOS CADASTROS)
============================== */

function prevenirAutofillCadastrosSecretaria() {
    ['api/secretaria_cadastro_medico.php', 'api/secretaria_cadastro_paciente.php'].forEach(function(action) {
        var form = document.querySelector('form[action="' + action + '"]');
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
    });
}


/* ==============================
   INICIALIZAÇÃO
============================== */

window.onload = function() {
    aplicarMascaraTelefone();
    aplicarMascaraCPF();
    aplicarBuscaCEP();
    prevenirAutofillCadastrosSecretaria();
};