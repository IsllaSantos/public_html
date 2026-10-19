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
   AGENDA — SLOTS DISPONÍVEIS & CHIPS
============================== */

function agendaResetSlots() {
    var sel = document.getElementById('slotHora');
    if (sel) {
        sel.innerHTML = '<option value="">Selecione médico e data primeiro</option>';
        sel.disabled = true;
    }

    var chipsContainer = document.getElementById('slotsChipsContainer');
    if (chipsContainer) {
        chipsContainer.innerHTML = '';
    }

    var dataInput = document.getElementById('slotData');
    if (dataInput) dataInput.value = '';
}

function agendaCarregarSlots() {
    var idMedico = document.getElementById('slotMedico').value;
    var data     = document.getElementById('slotData').value;
    var selHora  = document.getElementById('slotHora');
    var chipsContainer = document.getElementById('slotsChipsContainer');

    var hdnData = document.getElementById('hdnData');
    if (hdnData) hdnData.value = data;

    if (!idMedico || !data) {
        if (selHora) {
            selHora.innerHTML = '<option value="">Selecione médico e data primeiro</option>';
            selHora.disabled = true;
        }
        if (chipsContainer) chipsContainer.innerHTML = '';
        return;
    }

    if (selHora) {
        selHora.innerHTML = '<option value="">Carregando horários...</option>';
        selHora.disabled = true;
    }
    if (chipsContainer) {
        chipsContainer.innerHTML = '<p style="color:#888;font-size:0.85rem;">Carregando horários disponíveis...</p>';
    }

    fetch('api/secretaria_agenda_slots.php?id_medico=' + idMedico + '&data=' + data)
    .then(function(r) { return r.json(); })
    .then(function(slots) {
        if (!slots || !slots.length) {
            if (selHora) {
                selHora.innerHTML = '<option value="">Sem horários disponíveis nesta data</option>';
                selHora.disabled = true;
            }
            if (chipsContainer) {
                chipsContainer.innerHTML = '<p style="color:var(--text-muted);font-size:0.88rem;padding:8px 0;">⚠ Nenhum horário livre encontrado para este médico na data selecionada.</p>';
            }
            return;
        }

        // Popula select nativo (para envio padrão de formulário)
        if (selHora) {
            selHora.innerHTML = '<option value="">Selecione um horário</option>' +
                slots.map(function(h) {
                    return '<option value="' + h + '">' + h + '</option>';
                }).join('');
            selHora.disabled = false;
        }

        // Renderiza Chips Visuais Interativos
        if (chipsContainer) {
            var html = '<div class="slots-wrapper">';
            html += '<div class="slots-period-title">⏱️ Horários Disponíveis:</div>';
            html += '<div class="slots-grid">';

            slots.forEach(function(hora) {
                html += '<div class="slot-chip" data-hora="' + hora + '" onclick="selecionarSlotChip(this, \'' + hora + '\')">' + hora + '</div>';
            });

            html += '</div></div>';
            chipsContainer.innerHTML = html;
        }
    })
    .catch(function() {
        if (selHora) {
            selHora.innerHTML = '<option value="">Erro ao carregar horários</option>';
            selHora.disabled = true;
        }
        if (chipsContainer) {
            chipsContainer.innerHTML = '<p style="color:var(--danger);font-size:0.85rem;">Erro ao carregar horários do servidor.</p>';
        }
    });
}

function selecionarSlotChip(chipEl, hora) {
    document.querySelectorAll('.slot-chip').forEach(function(c) {
        c.classList.remove('selected');
    });
    chipEl.classList.add('selected');

    var sel = document.getElementById('slotHora');
    if (sel) {
        sel.value = hora;
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
   INICIALIZAÇÃO
============================== */

window.onload = function() {
    aplicarMascaraTelefone();
    aplicarMascaraCPF();
    aplicarBuscaCEP();
};