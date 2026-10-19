/* ==============================
   TROCA DE ABAS
============================== */

function showPacienteTab(tab, event) {

    document.querySelectorAll('.paciente-tab')
    .forEach(function(item) {
        item.classList.remove('active');
    });

    document.querySelectorAll('nav button')
    .forEach(function(btn) {
        btn.classList.remove('nav-active');
    });

    var el = document.getElementById(tab);
    if (el) { el.classList.add('active'); }

    if (event && event.target) {
        event.target.classList.add('nav-active');
    }

    /* Carrega minhas consultas quando abrir a aba */
    if (tab === 'minhasConsultas') {
        carregarMinhasConsultas();
    }

    if (tab === 'minhasReceitas') {
        carregarMinhasReceitas();
    }

    if (tab === 'meusArquivos') {
        carregarConsultasParaArquivos();
        carregarArquivos();
    }
}


/* ==============================
   CARREGAR MÉDICOS POR ESPECIALIDADE
============================== */

function carregarMedicosEspecialidade() {

    var especialidade = document.getElementById('agEspecialidade').value;
    var selectMedico = document.getElementById('agMedico');
    var selectData   = document.getElementById('agData');
    var selectHora   = document.getElementById('agHorario');
    var btnAgendar   = document.getElementById('btnAgendar');

    /* Reset cascata */
    selectMedico.innerHTML = '<option value="">Carregando...</option>';
    selectMedico.disabled = true;
    selectData.innerHTML = '<option value="">Selecione o médico primeiro...</option>';
    selectData.disabled = true;
    selectHora.innerHTML = '<option value="">Selecione a data primeiro...</option>';
    selectHora.disabled = true;
    btnAgendar.disabled = true;

    if (!especialidade) {
        selectMedico.innerHTML = '<option value="">Selecione a especialidade primeiro...</option>';
        return;
    }

    fetch('api/paciente_api.php?acao=medicos&especialidade=' + encodeURIComponent(especialidade))
    .then(function(r) { return r.json(); })
    .then(function(medicos) {

        if (medicos.length === 0) {
            selectMedico.innerHTML = '<option value="">Nenhum médico disponível</option>';
            return;
        }

        var html = '<option value="">Selecione o médico...</option>';
        medicos.forEach(function(m) {
            html += '<option value="' + m.id_usuario + '">' +
                    escapeHtml(m.nome) + '</option>';
        });

        selectMedico.innerHTML = html;
        selectMedico.disabled = false;
    })
    .catch(function() {
        selectMedico.innerHTML = '<option value="">Erro ao carregar médicos</option>';
    });
}


/* ==============================
   CARREGAR DATAS DISPONÍVEIS
============================== */

function carregarDatasDisponiveis() {

    var id_medico = document.getElementById('agMedico').value;
    var selectData = document.getElementById('agData');
    var selectHora = document.getElementById('agHorario');
    var btnAgendar = document.getElementById('btnAgendar');

    selectData.innerHTML = '<option value="">Carregando...</option>';
    selectData.disabled = true;
    selectHora.innerHTML = '<option value="">Selecione a data primeiro...</option>';
    selectHora.disabled = true;
    btnAgendar.disabled = true;

    if (!id_medico) {
        selectData.innerHTML = '<option value="">Selecione o médico primeiro...</option>';
        return;
    }

    fetch('api/paciente_api.php?acao=datas&id_medico=' + id_medico)
    .then(function(r) { return r.json(); })
    .then(function(datas) {

        if (datas.length === 0) {
            selectData.innerHTML = '<option value="">Nenhuma data disponível</option>';
            return;
        }

        var diasPT = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];

        var html = '<option value="">Selecione a data...</option>';
        datas.forEach(function(d) {
            var partes = d.split('-');
            var dt = new Date(parseInt(partes[0], 10), parseInt(partes[1], 10) - 1, parseInt(partes[2], 10));
            var diaSemana = diasPT[dt.getDay()];
            var label = partes[2] + '/' + partes[1] + '/' + partes[0] + ' (' + diaSemana + ')';
            html += '<option value="' + d + '">' + label + '</option>';
        });

        selectData.innerHTML = html;
        selectData.disabled = false;
    })
    .catch(function() {
        selectData.innerHTML = '<option value="">Erro ao carregar datas</option>';
    });
}


/* ==============================
   CARREGAR HORÁRIOS DISPONÍVEIS
============================== */

function carregarHorariosDisponiveis() {

    var id_medico = document.getElementById('agMedico').value;
    var data      = document.getElementById('agData').value;
    var selectHora = document.getElementById('agHorario');
    var btnAgendar = document.getElementById('btnAgendar');

    selectHora.innerHTML = '<option value="">Carregando...</option>';
    selectHora.disabled = true;
    btnAgendar.disabled = true;

    if (!id_medico || !data) {
        selectHora.innerHTML = '<option value="">Selecione a data primeiro...</option>';
        return;
    }

    fetch('api/paciente_api.php?acao=horarios&id_medico=' + id_medico + '&data=' + data)
    .then(function(r) { return r.json(); })
    .then(function(slots) {

        if (slots.length === 0) {
            selectHora.innerHTML = '<option value="">Nenhum horário disponível</option>';
            return;
        }

        var html = '<option value="">Selecione o horário...</option>';
        slots.forEach(function(h) {
            html += '<option value="' + h + '">' + h + '</option>';
        });

        selectHora.innerHTML = html;
        selectHora.disabled = false;

        /* Habilitar botão quando selecionar horário */
        selectHora.onchange = function() {
            btnAgendar.disabled = !selectHora.value;
        };
    })
    .catch(function() {
        selectHora.innerHTML = '<option value="">Erro ao carregar horários</option>';
    });
}


/* ==============================
   AGENDAR CONSULTA
============================== */

function agendarConsulta() {

    var id_medico = document.getElementById('agMedico').value;
    var data      = document.getElementById('agData').value;
    var hora      = document.getElementById('agHorario').value;
    var motivo    = document.getElementById('agMotivo').value;

    if (!id_medico || !data || !hora) {
        alert('Preencha todos os campos obrigatórios.');
        return;
    }

    if (!confirm('Confirmar agendamento para ' + formatarDataBR(data) + ' às ' + hora + '?')) {
        return;
    }

    var form = new FormData();
    form.append('id_medico', id_medico);
    form.append('data', data);
    form.append('hora', hora);
    form.append('motivo', motivo);

    fetch('api/paciente_api.php?acao=agendar', { method: 'POST', body: form })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.sucesso) {
            alert(res.mensagem);
            /* Reset form */
            document.getElementById('agEspecialidade').value = '';
            document.getElementById('agMedico').innerHTML = '<option value="">Selecione a especialidade primeiro...</option>';
            document.getElementById('agMedico').disabled = true;
            document.getElementById('agData').innerHTML = '<option value="">Selecione o médico primeiro...</option>';
            document.getElementById('agData').disabled = true;
            document.getElementById('agHorario').innerHTML = '<option value="">Selecione a data primeiro...</option>';
            document.getElementById('agHorario').disabled = true;
            document.getElementById('agMotivo').value = '';
            document.getElementById('btnAgendar').disabled = true;
        } else {
            alert('Erro: ' + (res.erro || 'Desconhecido'));
        }
    })
    .catch(function() { alert('Erro de comunicação com o servidor.'); });
}


/* ==============================
   MINHAS CONSULTAS
============================== */

function carregarMinhasConsultas() {

    var tbody = document.getElementById('tbodyConsultas');
    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#888;">Carregando...</td></tr>';

    fetch('api/paciente_api.php?acao=minhas_consultas')
    .then(function(r) { return r.json(); })
    .then(function(consultas) {

        if (consultas.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#888;">Nenhuma consulta encontrada.</td></tr>';
            return;
        }

        var html = '';
        consultas.forEach(function(c) {

            var statusMap = {
                'agendada':                { texto: 'Agendada', classe: 'status pending' },
                'aguardando_atendimento':  { texto: 'Aguardando', classe: 'status status-aguardando' },
                'em_atendimento':          { texto: 'Em Atendimento', classe: 'status status-em-atendimento' },
                'finalizada':              { texto: 'Finalizada', classe: 'status confirmed' },
                'cancelada':               { texto: 'Cancelada', classe: 'status status-cancelada' }
            };

            var info = statusMap[c.status] || { texto: c.status, classe: 'status' };

            var acoesHtml = '<span style="color:#888;font-size:0.85rem;">—</span>';
            if (c.status === 'agendada') {
                acoesHtml = '<button type="button" class="menu-btn" style="background:#ef4444;font-size:0.8rem;padding:4px 10px;" onclick="cancelarMinhaConsulta(' + c.id_consulta + ')">Cancelar</button>';
            }

            html += '<tr>';
            html += '<td>' + formatarDataBR(c.data_consulta) + '</td>';
            html += '<td>' + (c.hora_consulta ? c.hora_consulta.substring(0, 5) : '—') + '</td>';
            html += '<td><strong>' + escapeHtml(c.nome_medico) + '</strong></td>';
            html += '<td>' + escapeHtml(c.especialidade || '—') + '</td>';
            html += '<td>' + escapeHtml(c.motivo_consulta || '—') + '</td>';
            html += '<td><span class="' + info.classe + '">' + info.texto + '</span></td>';
            html += '<td>' + acoesHtml + '</td>';
            html += '</tr>';
        });

        tbody.innerHTML = html;
    })
    .catch(function() {
        tbody.innerHTML = '<tr><td colspan="7" style="color:red;">Erro ao carregar consultas.</td></tr>';
    });
}

function cancelarMinhaConsulta(idConsulta) {
    if (!idConsulta) return;

    if (!confirm('Deseja realmente cancelar este agendamento?')) {
        return;
    }

    var form = new FormData();
    form.append('id_consulta', idConsulta);

    fetch('api/paciente_api.php?acao=cancelar', { method: 'POST', body: form })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.sucesso) {
            if (window.showToast) window.showToast(res.mensagem, 'success');
            else alert(res.mensagem);
            carregarMinhasConsultas();
        } else {
            if (window.showToast) window.showToast(res.erro || 'Erro ao cancelar', 'error');
            else alert(res.erro || 'Erro ao cancelar');
        }
    })
    .catch(function() {
        if (window.showToast) window.showToast('Erro de comunicação com o servidor.', 'error');
        else alert('Erro de comunicação com o servidor.');
    });
}


/* ==============================
   MINHAS RECEITAS & DOCUMENTOS
============================== */

function carregarMinhasReceitas() {
    var container = document.getElementById('containerReceitasPaciente');
    if (!container) return;

    container.innerHTML = '<p style="color:#888;text-align:center;padding:30px;">Buscando suas prescrições...</p>';

    fetch('api/paciente_api.php?acao=minhas_receitas')
    .then(function(r) { return r.json(); })
    .then(function(receitas) {
        if (!receitas || !receitas.length) {
            container.innerHTML = '<div style="text-align:center;padding:40px;color:var(--text-muted);"><div style="font-size:2.5rem;margin-bottom:12px;">📄</div><p>Você ainda não possui receitas ou pedidos de exames disponibilizados em consultas concluídas.</p></div>';
            return;
        }

        var html = '<div class="grid" style="grid-template-columns:repeat(auto-fit, minmax(360px, 1fr));gap:20px;">';
        receitas.forEach(function(rec) {
            html += '<div class="card" style="margin-bottom:0;border-top:3px solid var(--gold);">';
            html += '<div class="top-bar" style="margin-bottom:12px;">';
            html += '<div><strong>Dr(a). ' + escapeHtml(rec.nome_medico) + '</strong><div style="font-size:0.8rem;color:var(--text-muted);">' + escapeHtml(rec.especialidade || 'Médico') + ' • CRM: ' + escapeHtml(rec.crm_medico || '—') + '-' + escapeHtml(rec.uf_medico || '') + '</div></div>';
            html += '<span class="status confirmed">Consulta: ' + formatarDataBR(rec.data_consulta) + '</span>';
            html += '</div>';

            if (rec.medicamentos) {
                html += '<div style="margin-bottom:14px;">';
                html += '<strong style="font-size:0.85rem;color:var(--gold-dark);display:block;margin-bottom:4px;">💊 Medicamentos Prescritos:</strong>';
                html += '<div style="background:#f8fafc;padding:10px 14px;border-radius:8px;font-size:0.88rem;white-space:pre-wrap;border:1px solid var(--border);line-height:1.6;">' + escapeHtml(rec.medicamentos) + '</div>';
                html += '</div>';
            }

            if (rec.exames) {
                html += '<div style="margin-bottom:14px;">';
                html += '<strong style="font-size:0.85rem;color:var(--info);display:block;margin-bottom:4px;">🔬 Exames Solicitados:</strong>';
                html += '<div style="background:#f8fafc;padding:10px 14px;border-radius:8px;font-size:0.88rem;white-space:pre-wrap;border:1px solid var(--border);line-height:1.6;">' + escapeHtml(rec.exames) + '</div>';
                html += '</div>';
            }

            var recJson = JSON.stringify(rec).replace(/"/g, '&quot;');
            html += '<button type="button" class="btn btn-secondary" style="width:100%;font-size:0.86rem;padding:9px;margin-top:6px;" onclick="imprimirReceitaPaciente(' + recJson + ')">🖨️ Visualizar / Imprimir Documento</button>';
            html += '</div>';
        });
        html += '</div>';

        container.innerHTML = html;
    })
    .catch(function() {
        container.innerHTML = '<p style="color:var(--danger);text-align:center;padding:20px;">Erro ao carregar suas prescrições.</p>';
    });
}

function imprimirReceitaPaciente(rec) {
    var html = '<div class="printable-area print-prescription-card">' +
        '<div class="print-header">' +
            '<div>' +
                '<div class="print-clinic-title">TechNyes Clínicas</div>' +
                '<div class="print-meta">Receituário e Pedidos de Exames</div>' +
            '</div>' +
            '<div style="text-align:right;">' +
                '<div class="print-meta">Data da Consulta: ' + formatarDataBR(rec.data_consulta) + '</div>' +
            '</div>' +
        '</div>' +
        '<div class="print-content-block" style="background:#f8fafc;padding:12px;border-radius:6px;margin-bottom:18px;">' +
            '<strong>Médico Responsável:</strong> Dr(a). ' + escapeHtml(rec.nome_medico) + ' (' + escapeHtml(rec.especialidade || 'Médico') + ' - CRM: ' + escapeHtml(rec.crm_medico || '') + '-' + escapeHtml(rec.uf_medico || '') + ')' +
        '</div>';

    if (rec.medicamentos) {
        html += '<div class="print-content-block">' +
            '<h4>💊 Prescrição Medicamentosa:</h4>' +
            '<div style="white-space:pre-wrap;font-size:0.95rem;line-height:1.7;">' + escapeHtml(rec.medicamentos) + '</div>' +
        '</div>';
    }

    if (rec.exames) {
        html += '<div class="print-content-block">' +
            '<h4>🔬 Solicitação de Exames:</h4>' +
            '<div style="white-space:pre-wrap;font-size:0.95rem;line-height:1.7;">' + escapeHtml(rec.exames) + '</div>' +
        '</div>';
    }

    html += '<div class="print-footer">' +
        '<div class="print-meta">Documento emitido eletronicamente via TechNyes.</div>' +
        '<div class="print-signature">Assinatura / Carimbo Médico</div>' +
    '</div></div>';

    var botoes = '<button class="btn btn-secondary" onclick="window.fecharModal()">Fechar</button>' +
                 '<button class="btn btn-primary" onclick="window.print()">🖨️ Imprimir (A4)</button>';

    window.abrirModal('Prescrição Médica', html, botoes);
}


/* ==============================
   MEUS ARQUIVOS
============================== */

function carregarConsultasParaArquivos() {
    var sel = document.getElementById('arqConsulta');
    fetch('api/paciente_arquivos.php?acao=consultas')
    .then(function(r) { return r.json(); })
    .then(function(consultas) {
        if (!Array.isArray(consultas)) {
            throw new Error(consultas && consultas.erro ? consultas.erro : 'Resposta inválida');
        }
        var atual = sel.value;
        var html = '<option value="">Não vincular</option>';
        consultas.forEach(function(c) {
            html += '<option value="' + c.id_consulta + '">' + formatarDataBR(c.data_consulta) +
                    (c.hora_consulta ? ' ' + c.hora_consulta.substring(0, 5) : '') +
                    ' — ' + escapeHtml(c.nome_medico || 'Médico') + '</option>';
        });
        if (!consultas.length) {
            html = '<option value="">Nenhuma consulta encontrada</option>';
        }
        sel.innerHTML = html;
        sel.value = atual;
    })
    .catch(function(e) {
        sel.innerHTML = '<option value="">Erro ao carregar consultas</option>';
        console.error('Consultas para vínculo:', e);
    });
}

function formatarTamanho(b) {
    b = parseInt(b, 10) || 0;
    if (b < 1024) return b + ' B';
    if (b < 1048576) return (b / 1024).toFixed(1) + ' KB';
    return (b / 1048576).toFixed(1) + ' MB';
}

var arquivosCache = [];

function carregarArquivos() {
    var tbody = document.getElementById('tbodyArquivos');

    fetch('api/paciente_arquivos.php?acao=listar')
    .then(function(r) { return r.json(); })
    .then(function(lista) {
        arquivosCache = lista;

        if (!lista.length) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#888;">Nenhum arquivo enviado.</td></tr>';
            return;
        }

        var html = '';
        lista.forEach(function(a) {
            var consulta = a.id_consulta
                ? formatarDataBR(a.data_consulta) + ' — ' + escapeHtml(a.nome_medico || '')
                : '<span style="color:#888;">Não vinculado</span>';
            var enviado = a.criado_em ? formatarDataBR(a.criado_em.substring(0, 10)) : '—';

            html += '<tr>';
            html += '<td>' + escapeHtml(a.titulo) + '</td>';
            html += '<td><a href="api/arquivo_download.php?id=' + a.id_arquivo + '" target="_blank" rel="noopener">' +
                    escapeHtml(a.nome_original) + '</a> <small style="color:#888;">(' + formatarTamanho(a.tamanho) + ')</small></td>';
            html += '<td>' + consulta + '</td>';
            html += '<td>' + enviado + '</td>';
            html += '<td>';
            html += '<button class="menu-btn" onclick="editarArquivo(' + a.id_arquivo + ')">Editar / Compartilhar</button> ';
            html += '<button class="menu-btn" style="background:#c0392b;" onclick="excluirArquivo(' + a.id_arquivo + ')">Excluir</button>';
            html += '</td>';
            html += '</tr>';
        });
        tbody.innerHTML = html;
    })
    .catch(function() {
        tbody.innerHTML = '<tr><td colspan="5" style="color:red;">Erro ao carregar arquivos.</td></tr>';
    });
}

function salvarArquivo() {
    var id       = document.getElementById('arqId').value;
    var titulo   = document.getElementById('arqTitulo').value.trim();
    var consulta = document.getElementById('arqConsulta').value;
    var input    = document.getElementById('arqArquivo');

    if (!titulo) { alert('Informe o título do arquivo.'); return; }
    if (!id && !input.files.length) { alert('Selecione um arquivo.'); return; }

    var form = new FormData();
    form.append('titulo', titulo);
    form.append('id_consulta', consulta);
    if (id) {
        form.append('id_arquivo', id);
    } else {
        form.append('arquivo', input.files[0]);
    }

    var btn = document.getElementById('btnArqSalvar');
    btn.disabled = true;

    fetch('api/paciente_arquivos.php?acao=' + (id ? 'editar' : 'enviar'), { method: 'POST', body: form })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        btn.disabled = false;
        if (res.sucesso) {
            alert(res.mensagem);
            cancelarEdicaoArquivo();
            carregarArquivos();
        } else {
            alert('Erro: ' + (res.erro || 'Desconhecido'));
        }
    })
    .catch(function() {
        btn.disabled = false;
        alert('Erro de comunicação com o servidor.');
    });
}

function editarArquivo(id) {
    var a = arquivosCache.filter(function(x) { return parseInt(x.id_arquivo, 10) === id; })[0];
    if (!a) return;

    document.getElementById('arqId').value = a.id_arquivo;
    document.getElementById('arqTitulo').value = a.titulo;
    document.getElementById('arqConsulta').value = a.id_consulta || '';
    document.getElementById('arqGrupoArquivo').style.display = 'none';
    document.getElementById('arqTituloForm').textContent = '✏️ Editar Arquivo';
    document.getElementById('btnArqSalvar').textContent = '💾 Salvar alterações';
    document.getElementById('btnArqCancelar').style.display = '';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function cancelarEdicaoArquivo() {
    document.getElementById('arqId').value = '';
    document.getElementById('arqTitulo').value = '';
    document.getElementById('arqArquivo').value = '';
    document.getElementById('arqConsulta').value = '';
    document.getElementById('arqGrupoArquivo').style.display = '';
    document.getElementById('arqTituloForm').textContent = '📎 Enviar Arquivo';
    document.getElementById('btnArqSalvar').textContent = '⬆ Enviar';
    document.getElementById('btnArqCancelar').style.display = 'none';
}

function excluirArquivo(id) {
    if (!confirm('Excluir este arquivo definitivamente?')) return;

    var form = new FormData();
    form.append('id_arquivo', id);

    fetch('api/paciente_arquivos.php?acao=excluir', { method: 'POST', body: form })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.sucesso) { carregarArquivos(); }
        else { alert('Erro: ' + (res.erro || 'Desconhecido')); }
    })
    .catch(function() { alert('Erro de comunicação com o servidor.'); });
}


/* ==============================
   UTILITÁRIOS
============================== */

function formatarDataBR(iso) {
    if (!iso) return '—';
    var p = iso.split('-');
    return p[2] + '/' + p[1] + '/' + p[0];
}

function escapeHtml(text) {
    if (!text) return '';
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}