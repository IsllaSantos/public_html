/* ==============================
   TROCA DE TELAS
============================== */

function showScreen(screen, event) {

    var alvo = document.getElementById(screen);
    if (!alvo) { return; }

    document.querySelectorAll('.screen')
    .forEach(function(item) { item.classList.remove('active'); });

    alvo.classList.add('active');

    /* Destaca o botão do menu correspondente à tela (pelo destino do onclick) */
    document.querySelectorAll('header nav button')
    .forEach(function(btn) {
        var oc = btn.getAttribute('onclick') || '';
        btn.classList.toggle('active', oc.indexOf("showScreen('" + screen + "'") !== -1);
    });

    if (screen === 'minhaAgenda') {
        try { carregarAgenda(); } catch (e) { console.error(e); }
    }

    window.scrollTo(0, 0);
}


/* ==============================
   PRONTUÁRIO — ABAS INTERNAS
============================== */

function showProntuario(tab, event) {

    document.querySelectorAll('.prontuario-content')
    .forEach(function(item) { item.classList.remove('active'); });

    document.querySelectorAll('.prontuario-btn')
    .forEach(function(btn) {
        btn.classList.remove('active');
        if (btn.getAttribute('data-tab') === tab) {
            btn.classList.add('active');
        }
    });

    // Sincroniza Stepper caso exista
    document.querySelectorAll('#prontuarioStepper .step-item, .step-item')
    .forEach(function(step) {
        step.classList.remove('active');
        if (step.getAttribute('data-tab') === tab) {
            step.classList.add('active');
        }
    });

    var el = document.getElementById(tab);
    if (el) { el.classList.add('active'); }

    if (event && event.target) {
        var btnTarget = event.target.closest('.prontuario-btn');
        if (btnTarget) btnTarget.classList.add('active');
        var stepTarget = event.target.closest('.step-item');
        if (stepTarget) stepTarget.classList.add('active');
    }
}


/* ==============================
   BUSCA DE PACIENTES
============================== */

function filtrarPacientes() {
    var termo = document.getElementById('buscaPaciente').value.toLowerCase();
    var linhas = document.querySelectorAll('#tbodyPacientes tr');
    linhas.forEach(function(tr) {
        var nome = tr.cells[0] ? tr.cells[0].textContent.toLowerCase() : '';
        var cpf  = tr.cells[1] ? tr.cells[1].textContent.toLowerCase() : '';
        tr.style.display = (nome.includes(termo) || cpf.includes(termo)) ? '' : 'none';
    });
}


/* ==============================
   INICIAR ATENDIMENTO & VER DETALHES
============================== */

var consultaAtualId = 0;
var pacienteAtualId = 0;
var modoLeitura = false;
var consultaAtualFinalizada = false;

function verDetalhes(idConsulta, idPaciente) {
    iniciarAtendimento(idConsulta, idPaciente, 'abaHistorico');
}

/* Alterna entre modo visualização (só histórico) e modo preenchimento (abas + barra de ações) */
function aplicarModoAtendimento(leitura) {
    modoLeitura = leitura;
    var seletores = ['.prontuario-tabs', '.stepper-container', '#prontuarioTabs', '#formProntuario', '#abaReceita', '#abaPosAtendimento', '#barraAcoes'];
    seletores.forEach(function(sel) {
        var el = document.querySelector('#atendimento ' + sel);
        if (el) el.style.display = leitura ? 'none' : '';
    });
    if (leitura) {
        var h = document.getElementById('abaHistorico');
        document.querySelectorAll('.prontuario-content').forEach(function(i) { i.classList.remove('active'); });
        if (h) h.classList.add('active');
    }
}

function preencherConsulta(idConsulta, finalizada) {
    if (finalizada) return;
    iniciarAtendimento(idConsulta, pacienteAtualId, 'abaAnamnese', true);
}

function iniciarNovoProntuario() {
    if (modoLeitura) {
        preencherConsulta(consultaAtualId);
        return;
    }
    showProntuario('abaAnamnese');
    var tabs = document.querySelectorAll('.prontuario-btn');
    tabs.forEach(function(btn, i) {
        if (i === 0) btn.classList.add('active');
        else btn.classList.remove('active');
    });
    var queixa = document.getElementById('prontQueixa');
    if (queixa) {
        queixa.focus();
    }
}

function iniciarAtendimento(idConsulta, idPaciente, tabInicial, forcarEdicao) {

    consultaAtualId = idConsulta;
    pacienteAtualId = idPaciente;

    /* Mostra a tela de atendimento */
    document.querySelectorAll('.screen')
    .forEach(function(item) { item.classList.remove('active'); });
    document.getElementById('atendimento').classList.add('active');

    document.querySelectorAll('nav button')
    .forEach(function(btn) { btn.classList.remove('active'); });

    /* Loading */
    document.getElementById('atendimentoLoading').style.display = 'block';
    document.getElementById('atendimentoConteudo').style.display = 'none';

    var url = 'api/medico_paciente_dados.php?id_paciente=' + idPaciente;
    if (idConsulta > 0) {
        url += '&id_consulta=' + idConsulta;
        /* Só marca "em atendimento" quando o médico realmente vai preencher */
        if (tabInicial !== 'abaHistorico') url += '&iniciar=1';
    }

    fetch(url)
    .then(function(r) {
        return r.text().then(function(text) {
            var dados;
            try {
                dados = JSON.parse(text);
            } catch (e) {
                var firstBrace = text.indexOf('{');
                var lastBrace  = text.lastIndexOf('}');
                if (firstBrace !== -1 && lastBrace > firstBrace) {
                    try {
                        dados = JSON.parse(text.substring(firstBrace, lastBrace + 1));
                    } catch (e2) {
                        console.error('Falha ao extrair JSON do servidor:', text);
                        throw new Error('Formato de resposta inválido.');
                    }
                } else {
                    console.error('Resposta do servidor não-JSON:', text);
                    if (!r.ok) {
                        throw new Error('Erro no servidor (código ' + r.status + ').');
                    }
                    throw new Error('Servidor retornou resposta em formato não-JSON.');
                }
            }
            return dados;
        });
    })
    .then(function(dados) {

        if (dados.erro) {
            alert(dados.erro);
            document.getElementById('atendimentoLoading').innerHTML =
                '<p style="color:red;">' + escapeHtml(dados.erro) + '</p>';
            return;
        }

        preencherDadosPaciente(dados.paciente);
        preencherConsultaAtual(dados.consulta_atual);
        preencherFormProntuario(dados.prontuario, dados.consulta_atual);
        preencherFormReceita(dados.receita, dados.exames);
        consultaAtualFinalizada = Boolean(dados.consulta_atual && ['finalizada','cancelada'].indexOf(dados.consulta_atual.status) !== -1);
        preencherHistorico(dados.historico);
        preencherAnexos(dados.anexos);

        /* Sincronizar hidden inputs */
        document.getElementById('prontIdConsulta').value = consultaAtualId;
        document.getElementById('receitaIdConsulta').value = consultaAtualId;
        document.getElementById('posIdConsulta').value = consultaAtualId;

        /* Esconde loading, mostra conteúdo */
        document.getElementById('atendimentoLoading').style.display = 'none';
        document.getElementById('atendimentoConteudo').style.display = 'block';

        /* Consulta finalizada: somente visualização (histórico), nunca edição */
        var consultaFinalizada = Boolean(dados.consulta_atual && dados.consulta_atual.status === 'finalizada');
        if (consultaFinalizada) {
            tabInicial = 'abaHistorico';
            forcarEdicao = false;
        }

        /* Definir aba inicial (Anamnese ou Histórico) */
        var targetTab = tabInicial || 'abaAnamnese';
        showProntuario(targetTab);
        var tabs = document.querySelectorAll('.prontuario-btn');
        tabs.forEach(function(btn) {
            if (btn.getAttribute('onclick') && btn.getAttribute('onclick').includes(targetTab)) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });

        /* Controlar readonly se finalizada (exceto se forcarEdicao for true ou se estiver sem prontuário) */
        var semProntuario = !dados.prontuario || !dados.prontuario.queixa_principal;
        controlarEdicao(dados.consulta_atual, Boolean(!consultaFinalizada && (forcarEdicao || semProntuario)));

        /* Visualização: só histórico. Preenchimento: abas + campos */
        var leitura = (tabInicial === 'abaHistorico');
        aplicarModoAtendimento(leitura);
        if (!leitura && dados.consulta_atual && dados.consulta_atual.status === 'finalizada') {
            /* consulta já finalizada: nada para preencher */
        }

        /* Configurar cálculo de IMC e sinais vitais */
        configurarIMC();
        configurarSinaisVitais();
        restaurarRascunhoProntuario(idConsulta);
        configurarAutoSaveProntuario();
    })
    .catch(function(err) {
        console.error('Erro ao carregar dados do paciente:', err);
        alert('Erro ao carregar dados do paciente: ' + (err.message || 'Falha na requisição'));
        document.getElementById('atendimentoLoading').innerHTML =
            '<p style="color:red;">Erro ao carregar dados. Tente novamente.</p>';
    });
}


/* ==============================
   PREENCHER DADOS DO PACIENTE
============================== */

function preencherDadosPaciente(pac) {

    document.getElementById('atdNomePaciente').textContent = pac.nome;

    var sexoTexto = '';
    if (pac.sexo === 'M') sexoTexto = 'Masculino';
    else if (pac.sexo === 'F') sexoTexto = 'Feminino';
    else sexoTexto = pac.sexo || '—';

    var idadeTexto = '—';
    if (pac.data_nascimento) {
        var nascimento = new Date(pac.data_nascimento + 'T00:00:00');
        var hoje = new Date();
        var idade = hoje.getFullYear() - nascimento.getFullYear();
        var mesAtual = hoje.getMonth() - nascimento.getMonth();
        if (mesAtual < 0 || (mesAtual === 0 && hoje.getDate() < nascimento.getDate())) { idade--; }
        idadeTexto = idade + ' anos';
    }

    var nascFormatado = '—';
    if (pac.data_nascimento) {
        var p = pac.data_nascimento.split('-');
        nascFormatado = p[2] + '/' + p[1] + '/' + p[0];
    }

    var html = '';
    html += campoDado('Nome', pac.nome);
    html += campoDado('CPF', pac.cpf || '—');
    html += campoDado('RG', pac.rg || '—');
    html += campoDado('Data Nascimento', nascFormatado);
    html += campoDado('Idade', idadeTexto);
    html += campoDado('Sexo', sexoTexto);
    html += campoDado('Telefone', pac.telefone || pac.celular || '—');
    html += campoDado('E-mail', pac.email || '—');
    html += campoDado('Convênio', pac.convenio || '—');
    html += campoDado('Nº Carteirinha', pac.numero_carteirinha || '—');

    document.getElementById('atdDadosPaciente').innerHTML = html;
}

function campoDado(label, valor) {
    return '<div class="field-group"><label>' + label + '</label>' +
        '<div class="field">' + escapeHtml(valor) + '</div></div>';
}

function escapeHtml(text) {
    if (!text) return '';
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}


/* ==============================
   PREENCHER CONSULTA ATUAL
============================== */

function preencherConsultaAtual(consulta) {

    var card = document.getElementById('atdConsultaAtualCard');
    var statusSpan = document.getElementById('atdStatusConsulta');

    if (!consulta) {
        card.style.display = 'none';
        statusSpan.textContent = '';
        statusSpan.className = 'status';
        return;
    }

    card.style.display = 'block';

    var statusMap = {
        'aguardando_atendimento': { texto: 'Aguardando Atendimento', classe: 'status status-aguardando' },
        'aguardando_atendimen':   { texto: 'Aguardando Atendimento', classe: 'status status-aguardando' },
        'aguardando':             { texto: 'Aguardando Atendimento', classe: 'status status-aguardando' },
        'em_atendimento':        { texto: 'Em Atendimento', classe: 'status status-em-atendimento' },
        'agendada':              { texto: 'Agendada', classe: 'status pending' },
        'finalizada':            { texto: 'Finalizada', classe: 'status confirmed' }
    };

    var info = statusMap[consulta.status] || { texto: consulta.status, classe: 'status' };
    statusSpan.textContent = info.texto;
    statusSpan.className = info.classe;

    var dataFormatada = '—';
    if (consulta.data_consulta) {
        var p = consulta.data_consulta.split('-');
        dataFormatada = p[2] + '/' + p[1] + '/' + p[0];
    }
    var horaFormatada = consulta.hora_consulta ? consulta.hora_consulta.substring(0, 5) : '—';

    var chegadaFormatada = '—';
    if (consulta.chegada_em) {
        var dt = new Date(consulta.chegada_em.replace(' ', 'T'));
        chegadaFormatada = dt.getHours().toString().padStart(2, '0') + ':' +
                           dt.getMinutes().toString().padStart(2, '0');
    }

    var html = '';
    html += campoDado('Data', dataFormatada);
    html += campoDado('Horário', horaFormatada);
    html += campoDado('Chegada', chegadaFormatada);
    html += campoDado('Tipo', consulta.tipo_consulta || 'presencial');
    html += '<div class="field-group full"><label>Motivo da Consulta</label>' +
            '<div class="field">' + escapeHtml(consulta.motivo_consulta || '—') + '</div></div>';

    if (consulta.observacoes) {
        html += '<div class="field-group full"><label>Observações</label>' +
                '<div class="field">' + escapeHtml(consulta.observacoes) + '</div></div>';
    }

    document.getElementById('atdConsultaAtualDados').innerHTML = html;
}


/* ==============================
   PREENCHER FORMULÁRIO PRONTUÁRIO
============================== */

function preencherFormProntuario(pront, consulta) {

    /* Limpa todos os campos */
    var ids = [
        'prontQueixa', 'prontHistDoenca', 'prontAntecedentes',
        'prontAlergias', 'prontMedicamentos', 'prontHistFamiliar', 'prontHabitos',
        'prontPA', 'prontFC', 'prontFR', 'prontTemp',
        'prontPeso', 'prontAltura', 'prontSat', 'prontObsExame',
        'prontHipotese', 'prontCID', 'prontConduta', 'prontOrientacoes',
        'prontOrientacoesPos', 'prontPrazoRetorno', 'prontObsFinais'
    ];

    ids.forEach(function(id) {
        var el = document.getElementById(id);
        if (el) el.value = '';
    });

    var imc = document.getElementById('prontIMC');
    if (imc) imc.value = '';

    var retorno = document.getElementById('prontRetorno');
    if (retorno) retorno.value = '0';

    /* Preenche se houver prontuário */
    if (pront) {
        setVal('prontQueixa',         pront.queixa_principal);
        setVal('prontHistDoenca',     pront.historico_doenca);
        setVal('prontAntecedentes',   pront.antecedentes);
        setVal('prontAlergias',       pront.alergias);
        setVal('prontMedicamentos',   pront.medicamentos);
        setVal('prontHistFamiliar',   pront.historico_familiar);
        setVal('prontHabitos',        pront.habitos_vida);
        setVal('prontPA',             pront.pressao_arterial);
        setVal('prontFC',             pront.frequencia_cardiaca);
        setVal('prontFR',             pront.frequencia_respiratoria);
        setVal('prontTemp',           pront.temperatura);
        setVal('prontPeso',           pront.peso);
        setVal('prontAltura',         pront.altura);
        setVal('prontSat',            pront.saturacao);
        setVal('prontObsExame',       pront.observacoes_exame);
        setVal('prontHipotese',       pront.hipotese_diagnostica);
        setVal('prontCID',            pront.cid10);
        setVal('prontConduta',        pront.conduta);
        setVal('prontOrientacoes',    pront.orientacoes);
        setVal('prontOrientacoesPos', pront.orientacoes_pos);
        setVal('prontPrazoRetorno',   pront.prazo_retorno);
        setVal('prontObsFinais',      pront.observacoes_finais);

        if (retorno && pront.retorno_necessario) {
            retorno.value = pront.retorno_necessario.toString();
        }

        /* Calcular IMC */
        calcularIMC();
    }

    /* Pré-preenche queixa com motivo da consulta se vazia */
    var queixa = document.getElementById('prontQueixa');
    if (consulta && consulta.motivo_consulta && queixa && !queixa.value) {
        queixa.value = consulta.motivo_consulta;
    }
}

function setVal(id, valor) {
    var el = document.getElementById(id);
    if (el && valor) el.value = valor;
}


/* ==============================
   PREENCHER RECEITA / EXAMES
============================== */

function preencherFormReceita(receita, exames) {

    setVal('prontReceitaMed', '');
    setVal('prontExamesSol', '');
    setVal('prontExamesObs', '');

    if (receita && receita.medicamentos) {
        setVal('prontReceitaMed', receita.medicamentos);
    }

    if (exames) {
        if (exames.exames)      setVal('prontExamesSol', exames.exames);
        if (exames.observacoes) setVal('prontExamesObs', exames.observacoes);
    }
}


/* ==============================
   IMC — CÁLCULO AUTOMÁTICO
============================== */

function configurarIMC() {
    var peso = document.getElementById('prontPeso');
    var altura = document.getElementById('prontAltura');

    if (peso) peso.addEventListener('input', calcularIMC);
    if (altura) altura.addEventListener('input', calcularIMC);
}

function calcularIMC() {
    var pesoEl = document.getElementById('prontPeso');
    var alturaEl = document.getElementById('prontAltura');
    var imcEl = document.getElementById('prontIMC');
    var tagIMC = document.getElementById('vitalTagIMC');

    if (!pesoEl || !alturaEl || !imcEl) return;

    var peso = parseFloat(pesoEl.value.replace(',', '.'));
    var alturaCm = parseFloat(alturaEl.value.replace(',', '.'));

    if (peso > 0 && alturaCm > 0) {
        var alturaM = alturaCm / 100;
        var imc = peso / (alturaM * alturaM);
        var imcVal = imc.toFixed(1);
        var classif = '';
        var classeTag = 'vital-tag-normal';

        if (imc < 18.5) {
            classif = 'Baixo peso';
            classeTag = 'vital-tag-alert';
        } else if (imc < 25) {
            classif = 'Eutrófico (Normal)';
            classeTag = 'vital-tag-normal';
        } else if (imc < 30) {
            classif = 'Sobrepeso';
            classeTag = 'vital-tag-alert';
        } else {
            classif = 'Obesidade';
            classeTag = 'vital-tag-danger';
        }

        imcEl.value = imcVal + ' — ' + classif;
        if (tagIMC) tagIMC.innerHTML = '<span class="vital-tag ' + classeTag + '">' + classif + '</span>';
    } else {
        imcEl.value = '';
        if (tagIMC) tagIMC.innerHTML = '';
    }
}

function configurarSinaisVitais() {
    var pa = document.getElementById('prontPA');
    var temp = document.getElementById('prontTemp');
    var sat = document.getElementById('prontSat');

    if (pa) pa.addEventListener('input', avaliarPA);
    if (temp) temp.addEventListener('input', avaliarTemp);
    if (sat) sat.addEventListener('input', avaliarSat);

    avaliarPA();
    avaliarTemp();
    avaliarSat();
    calcularIMC();
}

function avaliarPA() {
    var paEl = document.getElementById('prontPA');
    var tagPA = document.getElementById('vitalTagPA');
    if (!paEl || !tagPA) return;

    var v = paEl.value.trim();
    if (!v) { tagPA.innerHTML = ''; return; }

    var parts = v.split('/');
    if (parts.length === 2) {
        var sist = parseInt(parts[0], 10);
        var diast = parseInt(parts[1], 10);

        if (sist >= 140 || diast >= 90) {
            tagPA.innerHTML = '<span class="vital-tag vital-tag-danger">⚠ Hipertensão</span>';
        } else if (sist >= 130 || diast >= 85) {
            tagPA.innerHTML = '<span class="vital-tag vital-tag-alert">Pré-hipertensão</span>';
        } else if (sist > 0 && diast > 0) {
            tagPA.innerHTML = '<span class="vital-tag vital-tag-normal">✓ PA Normal</span>';
        }
    }
}

function avaliarTemp() {
    var tempEl = document.getElementById('prontTemp');
    var tagTemp = document.getElementById('vitalTagTemp');
    if (!tempEl || !tagTemp) return;

    var t = parseFloat(tempEl.value.replace(',', '.'));
    if (!t) { tagTemp.innerHTML = ''; return; }

    if (t >= 37.8) {
        tagTemp.innerHTML = '<span class="vital-tag vital-tag-danger">⚠ Febre (' + t + '°C)</span>';
    } else if (t >= 37.3) {
        tagTemp.innerHTML = '<span class="vital-tag vital-tag-alert">Febrícula (' + t + '°C)</span>';
    } else {
        tagTemp.innerHTML = '<span class="vital-tag vital-tag-normal">✓ Afebril</span>';
    }
}

function avaliarSat() {
    var satEl = document.getElementById('prontSat');
    var tagSat = document.getElementById('vitalTagSat');
    if (!satEl || !tagSat) return;

    var s = parseInt(satEl.value, 10);
    if (!s) { tagSat.innerHTML = ''; return; }

    if (s < 95) {
        tagSat.innerHTML = '<span class="vital-tag vital-tag-danger">⚠ Hipoxemia (<95%)</span>';
    } else {
        tagSat.innerHTML = '<span class="vital-tag vital-tag-normal">✓ Saturação Normal</span>';
    }
}
/* ==============================
   AUTO-SAVE & RASCUNHO (LOCALSTORAGE)
============================== */

var timerAutoSave = null;

function configurarAutoSaveProntuario() {
    var inputs = document.querySelectorAll('#atendimento input, #atendimento textarea, #atendimento select');
    inputs.forEach(function(el) {
        if (el.dataset.autosaveBound) return;
        el.dataset.autosaveBound = 'true';
        el.addEventListener('input', function() {
            if (consultaAtualId <= 0 || modoLeitura) return;
            clearTimeout(timerAutoSave);
            timerAutoSave = setTimeout(function() {
                salvarRascunhoProntuario(consultaAtualId);
            }, 1200);
        });
    });
}

function salvarRascunhoProntuario(idConsulta) {
    if (!idConsulta || idConsulta <= 0 || modoLeitura) return;
    var draft = {
        queixa_principal:   getVal('prontQueixa'),
        historico_doenca:   getVal('prontHistDoenca'),
        antecedentes:       getVal('prontAntecedentes'),
        alergias:           getVal('prontAlergias'),
        medicamentos:       getVal('prontMedicamentos'),
        historico_familiar: getVal('prontHistFamiliar'),
        habitos_vida:       getVal('prontHabitos'),
        pressao_arterial:        getVal('prontPA'),
        frequencia_cardiaca:     getVal('prontFC'),
        frequencia_respiratoria: getVal('prontFR'),
        temperatura:             getVal('prontTemp'),
        peso:                    getVal('prontPeso'),
        altura:                  getVal('prontAltura'),
        saturacao:               getVal('prontSat'),
        observacoes_exame:       getVal('prontObsExame'),
        hipotese_diagnostica:    getVal('prontHipotese'),
        cid10:                   getVal('prontCID'),
        conduta:                 getVal('prontConduta'),
        orientacoes:             getVal('prontOrientacoes'),
        medicamentos_receita:    getVal('prontReceitaMed'),
        exames_solicitados:      getVal('prontExamesSol'),
        observacoes_exames:      getVal('prontExamesObs'),
        orientacoes_pos:         getVal('prontOrientacoesPos'),
        retorno_necessario:      getVal('prontRetorno'),
        prazo_retorno:           getVal('prontPrazoRetorno'),
        observacoes_finais:      getVal('prontObsFinais'),
        timestamp:               new Date().toISOString()
    };
    try {
        localStorage.setItem('technyes_draft_' + idConsulta, JSON.stringify(draft));
    } catch(e) {}
}

function restaurarRascunhoProntuario(idConsulta) {
    if (!idConsulta || idConsulta <= 0 || modoLeitura) return;
    try {
        var raw = localStorage.getItem('technyes_draft_' + idConsulta);
        if (!raw) return;
        var d = JSON.parse(raw);
        if (!d) return;

        var queixaAtual = getVal('prontQueixa');
        if (!queixaAtual && (d.queixa_principal || d.hipotese_diagnostica || d.medicamentos_receita)) {
            if (d.queixa_principal)   setFieldVal('prontQueixa', d.queixa_principal);
            if (d.historico_doenca)   setFieldVal('prontHistDoenca', d.historico_doenca);
            if (d.antecedentes)       setFieldVal('prontAntecedentes', d.antecedentes);
            if (d.alergias)           setFieldVal('prontAlergias', d.alergias);
            if (d.medicamentos)       setFieldVal('prontMedicamentos', d.medicamentos);
            if (d.historico_familiar) setFieldVal('prontHistFamiliar', d.historico_familiar);
            if (d.habitos_vida)       setFieldVal('prontHabitos', d.habitos_vida);
            if (d.pressao_arterial)        setFieldVal('prontPA', d.pressao_arterial);
            if (d.frequencia_cardiaca)     setFieldVal('prontFC', d.frequencia_cardiaca);
            if (d.frequencia_respiratoria) setFieldVal('prontFR', d.frequencia_respiratoria);
            if (d.temperatura)             setFieldVal('prontTemp', d.temperatura);
            if (d.peso)                    setFieldVal('prontPeso', d.peso);
            if (d.altura)                  setFieldVal('prontAltura', d.altura);
            if (d.saturacao)               setFieldVal('prontSat', d.saturacao);
            if (d.observacoes_exame)       setFieldVal('prontObsExame', d.observacoes_exame);
            if (d.hipotese_diagnostica)    setFieldVal('prontHipotese', d.hipotese_diagnostica);
            if (d.cid10)                   setFieldVal('prontCID', d.cid10);
            if (d.conduta)                 setFieldVal('prontConduta', d.conduta);
            if (d.orientacoes)             setFieldVal('prontOrientacoes', d.orientacoes);
            if (d.medicamentos_receita)    setFieldVal('prontReceitaMed', d.medicamentos_receita);
            if (d.exames_solicitados)      setFieldVal('prontExamesSol', d.exames_solicitados);
            if (d.observacoes_exames)      setFieldVal('prontExamesObs', d.observacoes_exames);
            if (d.orientacoes_pos)         setFieldVal('prontOrientacoesPos', d.orientacoes_pos);
            if (d.retorno_necessario)      setFieldVal('prontRetorno', d.retorno_necessario);
            if (d.prazo_retorno)           setFieldVal('prontPrazoRetorno', d.prazo_retorno);
            if (d.observacoes_finais)      setFieldVal('prontObsFinais', d.observacoes_finais);

            configurarIMC();
            avaliarPA();
            avaliarTemp();
            avaliarSat();

            if (window.showToast) {
                window.showToast('📝 Rascunho anterior recuperado automaticamente.', 'info', 4000);
            }
        }
    } catch(e) {}
}

function limparRascunhoProntuario(idConsulta) {
    if (!idConsulta) return;
    try {
        localStorage.removeItem('technyes_draft_' + idConsulta);
    } catch(e) {}
}

function setFieldVal(id, val) {
    var el = document.getElementById(id);
    if (el && val !== undefined && val !== null) {
        el.value = val;
    }
}


/* ==============================
   CONTROLAR EDIÇÃO (READONLY)
============================== */

function controlarEdicao(consulta, forcarEdicao) {

    /* Consulta finalizada é sempre somente leitura */
    var isFinalizada = Boolean(consulta && consulta.status === 'finalizada');

    /* Textareas e inputs do prontuário */
    var campos = document.querySelectorAll(
        '#formProntuario textarea, #formProntuario input:not([type=hidden]),' +
        '#formReceita textarea, #formReceita input:not([type=hidden]),' +
        '#formPosAtendimento textarea, #formPosAtendimento input:not([type=hidden]),' +
        '#formPosAtendimento select'
    );

    campos.forEach(function(campo) {
        if (campo.tagName === 'TEXTAREA' || campo.tagName === 'INPUT') {
            campo.readOnly = isFinalizada;
        }
        if (campo.tagName === 'SELECT') {
            campo.disabled = isFinalizada;
        }
    });

    /* Botões de ação */
    var botoes = document.querySelectorAll(
        '#formProntuario button[type=submit], #formReceita button[type=submit],' +
        '#formPosAtendimento button[type=submit],' +
        '#btnFinalizarConsulta, #btnFinalizarConsulta2'
    );

    botoes.forEach(function(btn) {
        btn.disabled = isFinalizada;
        if (isFinalizada) btn.style.opacity = '0.5';
        else btn.style.opacity = '';
    });

    /* Barra fixa */
    var btnSalvarTudo = document.querySelector('#barraAcoes .confirm-btn');
    if (btnSalvarTudo) {
        btnSalvarTudo.disabled = isFinalizada;
        btnSalvarTudo.style.opacity = isFinalizada ? '0.5' : '';
    }
}


/* ==============================
   SALVAR PRONTUÁRIO (ABAS 1-3)
============================== */

function salvarProntuario(event) {

    event.preventDefault();

    if (consultaAtualId <= 0) {
        alert('Nenhuma consulta selecionada.');
        return;
    }

    /* Coleta dados de todos os forms do prontuário */
    var form = new FormData();
    form.append('id_consulta', consultaAtualId);

    /* Aba 1 — Anamnese */
    form.append('queixa_principal',   getVal('prontQueixa'));
    form.append('historico_doenca',   getVal('prontHistDoenca'));
    form.append('antecedentes',       getVal('prontAntecedentes'));
    form.append('alergias',           getVal('prontAlergias'));
    form.append('medicamentos',       getVal('prontMedicamentos'));
    form.append('historico_familiar', getVal('prontHistFamiliar'));
    form.append('habitos_vida',       getVal('prontHabitos'));

    /* Aba 2 — Exame Físico */
    form.append('pressao_arterial',        getVal('prontPA'));
    form.append('frequencia_cardiaca',     getVal('prontFC'));
    form.append('frequencia_respiratoria', getVal('prontFR'));
    form.append('temperatura',             getVal('prontTemp'));
    form.append('peso',                    getVal('prontPeso'));
    form.append('altura',                  getVal('prontAltura'));
    form.append('saturacao',               getVal('prontSat'));
    form.append('observacoes_exame',       getVal('prontObsExame'));

    /* Aba 3 — Diagnóstico */
    form.append('hipotese_diagnostica', getVal('prontHipotese'));
    form.append('cid10',                getVal('prontCID'));
    form.append('conduta',              getVal('prontConduta'));
    form.append('orientacoes',          getVal('prontOrientacoes'));

    /* Aba 5 — Pós-atendimento */
    form.append('orientacoes_pos',    getVal('prontOrientacoesPos'));
    form.append('retorno_necessario', getVal('prontRetorno'));
    form.append('prazo_retorno',      getVal('prontPrazoRetorno'));
    form.append('observacoes_finais', getVal('prontObsFinais'));

    postForm('api/medico_salvar_prontuario.php', form)
    .then(function(res) {
        if (res.sucesso) {
            limparRascunhoProntuario(consultaAtualId);
            if (window.showToast) window.showToast(res.mensagem || 'Prontuário salvo com sucesso!', 'success');
            else alert(res.mensagem);
        } else {
            if (window.showToast) window.showToast('Erro: ' + (res.erro || 'Desconhecido'), 'error');
            else alert('Erro: ' + (res.erro || 'Desconhecido'));
        }
    })
    .catch(function(err) {
        if (window.showToast) window.showToast('Erro ao salvar prontuário: ' + (err.message || 'Falha na requisição.'), 'error');
        else alert('Erro ao salvar prontuário: ' + (err.message || 'Falha na requisição.'));
    });
}

function getVal(id) {
    var el = document.getElementById(id);
    return el ? el.value : '';
}

function postForm(url, formData) {
    return fetch(url, { method: 'POST', body: formData })
    .then(function(r) {
        return r.text().then(function(text) {
            var dados;
            try {
                dados = JSON.parse(text);
            } catch (e) {
                var firstBrace = text.indexOf('{');
                var lastBrace  = text.lastIndexOf('}');
                if (firstBrace !== -1 && lastBrace > firstBrace) {
                    try {
                        dados = JSON.parse(text.substring(firstBrace, lastBrace + 1));
                    } catch (e2) {
                        console.error('Falha ao extrair JSON do servidor:', text);
                        throw new Error('Formato de resposta inválido.');
                    }
                } else {
                    console.error('Resposta do servidor não-JSON:', text);
                    if (!r.ok) {
                        throw new Error('Erro no servidor (código ' + r.status + ').');
                    }
                    throw new Error('Servidor retornou resposta não-JSON.');
                }
            }
            return dados;
        });
    });
}


/* ==============================
   SALVAR TUDO (barra fixa)
============================== */

function salvarTudoPromise() {
    /* Salva prontuário + receita em paralelo; retorna Promise */

    /* Prontuário */
    var form = new FormData();
    form.append('id_consulta', consultaAtualId);
    form.append('queixa_principal',   getVal('prontQueixa'));
    form.append('historico_doenca',   getVal('prontHistDoenca'));
    form.append('antecedentes',       getVal('prontAntecedentes'));
    form.append('alergias',           getVal('prontAlergias'));
    form.append('medicamentos',       getVal('prontMedicamentos'));
    form.append('historico_familiar', getVal('prontHistFamiliar'));
    form.append('habitos_vida',       getVal('prontHabitos'));
    form.append('pressao_arterial',        getVal('prontPA'));
    form.append('frequencia_cardiaca',     getVal('prontFC'));
    form.append('frequencia_respiratoria', getVal('prontFR'));
    form.append('temperatura',             getVal('prontTemp'));
    form.append('peso',                    getVal('prontPeso'));
    form.append('altura',                  getVal('prontAltura'));
    form.append('saturacao',               getVal('prontSat'));
    form.append('observacoes_exame',       getVal('prontObsExame'));
    form.append('hipotese_diagnostica', getVal('prontHipotese'));
    form.append('cid10',                getVal('prontCID'));
    form.append('conduta',              getVal('prontConduta'));
    form.append('orientacoes',          getVal('prontOrientacoes'));
    form.append('orientacoes_pos',    getVal('prontOrientacoesPos'));
    form.append('retorno_necessario', getVal('prontRetorno'));
    form.append('prazo_retorno',      getVal('prontPrazoRetorno'));
    form.append('observacoes_finais', getVal('prontObsFinais'));

    /* Receita */
    var formRec = new FormData();
    formRec.append('id_consulta', consultaAtualId);
    formRec.append('medicamentos_receita', getVal('prontReceitaMed'));
    formRec.append('exames_solicitados',   getVal('prontExamesSol'));
    formRec.append('observacoes_exames',   getVal('prontExamesObs'));

    return Promise.all([
        postForm('api/medico_salvar_prontuario.php', form),
        postForm('api/medico_salvar_receita.php', formRec)
    ])
    .then(function(resultados) {
        var erros = resultados.filter(function(r) { return r && r.erro; });
        if (erros.length > 0) {
            throw new Error(erros.map(function(e) { return e.erro; }).join(', '));
        }
        return true;
    });
}

function salvarProntuarioGeral() {
    if (consultaAtualId <= 0) {
        if (window.showToast) window.showToast('Nenhuma consulta selecionada.', 'warning');
        else alert('Nenhuma consulta selecionada.');
        return;
    }
    salvarTudoPromise()
    .then(function() {
        limparRascunhoProntuario(consultaAtualId);
        if (window.showToast) window.showToast('Tudo salvo com sucesso!', 'success');
        else alert('Tudo salvo com sucesso!');
    })
    .catch(function(err) {
        if (window.showToast) window.showToast('Erro ao salvar atendimento: ' + (err.message || 'Falha na comunicação.'), 'error');
        else alert('Erro ao salvar atendimento: ' + (err.message || 'Falha na comunicação com o servidor.'));
    });
}


/* ==============================
   SALVAR RECEITA / EXAMES (Aba 4)
============================== */

function salvarReceita(event) {

    event.preventDefault();

    if (consultaAtualId <= 0) {
        if (window.showToast) window.showToast('Nenhuma consulta selecionada.', 'warning');
        else alert('Nenhuma consulta selecionada.');
        return;
    }

    var form = new FormData();
    form.append('id_consulta',         consultaAtualId);
    form.append('medicamentos_receita', getVal('prontReceitaMed'));
    form.append('exames_solicitados',   getVal('prontExamesSol'));
    form.append('observacoes_exames',   getVal('prontExamesObs'));

    postForm('api/medico_salvar_receita.php', form)
    .then(function(res) {
        if (res.sucesso) {
            limparRascunhoProntuario(consultaAtualId);
            if (window.showToast) window.showToast(res.mensagem || 'Receita salva com sucesso!', 'success');
            else alert(res.mensagem);
        } else {
            if (window.showToast) window.showToast('Erro: ' + (res.erro || 'Desconhecido'), 'error');
            else alert('Erro: ' + (res.erro || 'Desconhecido'));
        }
    })
    .catch(function(err) {
        if (window.showToast) window.showToast('Erro ao salvar receita/exames: ' + (err.message || 'Falha na requisição.'), 'error');
        else alert('Erro ao salvar receita/exames: ' + (err.message || 'Falha na requisição.'));
    });
}


/* ==============================
   FINALIZAR CONSULTA (Etapa 10)
============================== */

function finalizarConsulta() {

    if (consultaAtualId <= 0) {
        if (window.showToast) window.showToast('Nenhuma consulta selecionada.', 'warning');
        else alert('Nenhuma consulta selecionada.');
        return;
    }

    if (!confirm('Deseja salvar todos os dados e finalizar esta consulta?\n\nTodos os dados preenchidos serão gravados no prontuário do paciente.')) {
        return;
    }

    var botoesFinalizar = document.querySelectorAll('#btnFinalizarConsulta, #btnFinalizarConsulta2');
    botoesFinalizar.forEach(function(b) {
        b.disabled = true;
        b.dataset.txtOriginal = b.textContent;
        b.textContent = 'Gravando dados e finalizando...';
    });

    /* 1º PASSO: Salva prontuário, receitas e exames */
    salvarTudoPromise()
    .then(function() {
        /* 2º PASSO: Finaliza a consulta */
        var form = new FormData();
        form.append('id_consulta', consultaAtualId);

        return postForm('api/medico_finalizar_consulta.php', form);
    })
    .then(function(res) {
        if (res && res.sucesso) {
            limparRascunhoProntuario(consultaAtualId);
            if (window.showToast) {
                window.showToast('Consulta finalizada e prontuário salvo com sucesso!', 'success', 3000);
                setTimeout(function() { window.location.reload(); }, 1200);
            } else {
                alert('Consulta finalizada e prontuário salvo com sucesso!');
                window.location.reload();
            }
        } else {
            if (window.showToast) window.showToast('Erro ao finalizar consulta: ' + (res && res.erro ? res.erro : 'Desconhecido'), 'error');
            else alert('Erro ao finalizar consulta: ' + (res && res.erro ? res.erro : 'Desconhecido'));
            botoesFinalizar.forEach(function(b) {
                b.disabled = false;
                b.textContent = b.dataset.txtOriginal || '✓ Finalizar Consulta';
            });
        }
    })
    .catch(function(err) {
        alert('Erro ao salvar e finalizar consulta: ' + (err.message || 'Falha na comunicação com o servidor.'));
        botoesFinalizar.forEach(function(b) {
            b.disabled = false;
            b.textContent = b.dataset.txtOriginal || '✓ Finalizar Consulta';
        });
    });
}


/* ==============================
   PREENCHER HISTÓRICO (Etapa 11)
============================== */

function preencherHistorico(historico) {

    var container = document.getElementById('atdHistoricoConteudo');
    if (!container) return;

    /* Filtra apenas prontuários / consultas anteriores preenchidas ou finalizadas */
    var historicoAntigo = [];
    if (historico && historico.length > 0) {
        historicoAntigo = historico.filter(function(h) {
            // Se for a própria consulta atual e ainda não finalizada, só exibe se tiver prontuário salvo
            if (h.id_consulta == consultaAtualId && h.status !== 'finalizada') {
                return (h.queixa_principal || h.historico_doenca || h.hipotese_diagnostica || h.conduta || h.receita || h.exames_solicitados);
            }
            return (h.queixa_principal || h.historico_doenca || h.hipotese_diagnostica || h.conduta || h.receita || h.exames_solicitados || h.status === 'finalizada');
        });
    }

    var html = '';

    /* CABEÇALHO DO HISTÓRICO COM BOTÃO INICIAR ATENDIMENTO */
    html += '<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px; margin-bottom:20px; background:#fff; padding:18px 25px; border-radius:12px; box-shadow:0 4px 15px rgba(0,0,0,0.06); border-left:5px solid var(--gold);">';
    html += '  <div>';
    html += '    <h3 style="margin:0; color:#2c3e50; font-size:1.25rem;">📂 Prontuários Antigos / Histórico do Paciente</h3>';
    html += '    <p style="margin:4px 0 0; color:#7f8c8d; font-size:0.9rem;">Consulte os atendimentos e prontuários anteriores deste paciente</p>';
    html += '  </div>';
    if (consultaAtualId > 0 && !consultaAtualFinalizada) {
        html += '  <button type="button" class="confirm-btn" style="background:#27ae60; font-size:0.95rem; padding:10px 20px; display:inline-flex; align-items:center; gap:8px;" onclick="iniciarNovoProntuario()">';
        html += '    ▶ Iniciar Atendimento (Novo Prontuário)';
        html += '  </button>';
    }
    html += '</div>';

    /* CASO SEM HISTÓRICO */
    if (historicoAntigo.length === 0) {
        html += '<div class="card" style="text-align:center; padding:45px 20px; border-top:4px solid #2980b9;">';
        html += '  <div style="font-size:3.5rem; margin-bottom:12px; color:#bdc3c7;">📋</div>';
        html += '  <h3 style="color:#2c3e50; margin-bottom:8px; font-size:1.4rem;">Sem Histórico</h3>';
        html += '  <p style="color:#7f8c8d; font-size:1rem; max-width:500px; margin:0 auto 25px auto;">';
        html += '    Nenhum prontuário anterior foi encontrado para este paciente.';
        html += '  </p>';
        if (consultaAtualId > 0 && !consultaAtualFinalizada) {
            html += '  <button type="button" class="confirm-btn" style="background:#27ae60; font-size:1.05rem; padding:12px 28px;" onclick="iniciarNovoProntuario()">';
            html += '    ▶ Iniciar Atendimento (Novo Prontuário)';
            html += '  </button>';
        }
        html += '</div>';
        container.innerHTML = html;
        return;
    }

    /* RENDERIZA CADA PRONTUÁRIO ANTERIOR */
    historicoAntigo.forEach(function(h) {

        var dataFormatada = '—';
        if (h.data_consulta) {
            var p = h.data_consulta.split('-');
            dataFormatada = p[2] + '/' + p[1] + '/' + p[0];
        }
        var horaFormatada = h.hora_consulta ? h.hora_consulta.substring(0, 5) : '';

        var statusClass = 'status';
        var statusTexto = h.status || '';
        if (h.status === 'finalizada')              { statusClass = 'status confirmed'; statusTexto = 'Finalizada'; }
        else if (h.status === 'em_atendimento')     { statusClass = 'status status-em-atendimento'; statusTexto = 'Em Atendimento'; }
        else if (h.status === 'aguardando_atendimento' || h.status === 'aguardando_atendimen' || h.status === 'aguardando') { statusClass = 'status status-aguardando'; statusTexto = 'Aguardando'; }
        else if (h.status === 'agendada')           { statusClass = 'status pending'; statusTexto = 'Agendada'; }

        html += '<details class="card historico-item" style="border-left:4px solid #2980b9; margin-bottom:20px;">';
        html += '<summary style="cursor:pointer; list-style:none; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">';
        html += '<h3 style="margin:0;">📅 Consulta de ' + dataFormatada + (horaFormatada ? ' às ' + horaFormatada : '') + '</h3>';
        html += '<span style="display:flex; align-items:center; gap:12px;">';
        html += '<span class="' + statusClass + '">' + statusTexto + '</span>';
        html += '<span style="color:#2980b9; font-weight:600; font-size:0.9rem;">▾ Ver consulta completa</span>';
        html += '</span>';
        html += '</summary>';
        html += '<div style="margin-top:15px;">';

        if (h.motivo_consulta) {
            html += '<p style="margin-bottom:12px;"><strong>Motivo:</strong> ' + escapeHtml(h.motivo_consulta) + '</p>';
        }

        /* Sinais Vitais */
        if (h.pressao_arterial || h.frequencia_cardiaca || h.peso || h.temperatura || h.saturacao) {
            html += '<div class="historico-sinais" style="display:flex; flex-wrap:wrap; gap:12px; background:#f8f9fa; padding:12px 18px; border-radius:8px; margin-bottom:15px;">';
            if (h.pressao_arterial) html += '<span style="font-weight:600; color:#333;">PA: ' + escapeHtml(h.pressao_arterial) + '</span>';
            if (h.frequencia_cardiaca) html += '<span style="font-weight:600; color:#333;">FC: ' + escapeHtml(h.frequencia_cardiaca) + ' bpm</span>';
            if (h.frequencia_respiratoria) html += '<span style="font-weight:600; color:#333;">FR: ' + escapeHtml(h.frequencia_respiratoria) + ' irpm</span>';
            if (h.temperatura) html += '<span style="font-weight:600; color:#333;">Temp: ' + escapeHtml(h.temperatura) + '°C</span>';
            if (h.peso) html += '<span style="font-weight:600; color:#333;">Peso: ' + escapeHtml(h.peso) + ' kg</span>';
            if (h.altura) html += '<span style="font-weight:600; color:#333;">Altura: ' + escapeHtml(h.altura) + ' cm</span>';
            if (h.saturacao) html += '<span style="font-weight:600; color:#333;">SpO₂: ' + escapeHtml(h.saturacao) + '%</span>';
            html += '</div>';
        }

        var temProntuario = h.queixa_principal || h.historico_doenca || h.conduta ||
                            h.hipotese_diagnostica || h.observacoes_exame;

        if (temProntuario) {
            html += '<div class="grid" style="margin-top:10px;">';

            if (h.queixa_principal) {
                html += campoHistorico('Queixa Principal', h.queixa_principal);
            }
            if (h.historico_doenca) {
                html += campoHistorico('Histórico da Doença', h.historico_doenca);
            }
            if (h.antecedentes) {
                html += campoHistoricoHalf('Antecedentes', h.antecedentes);
            }
            if (h.alergias) {
                html += campoHistoricoHalf('Alergias', h.alergias);
            }
            if (h.medicamentos) {
                html += campoHistoricoHalf('Medicamentos em Uso', h.medicamentos);
            }
            if (h.historico_familiar) {
                html += campoHistoricoHalf('Histórico Familiar', h.historico_familiar);
            }
            if (h.habitos_vida) {
                html += campoHistorico('Hábitos de Vida', h.habitos_vida);
            }
            if (h.observacoes_exame) {
                html += campoHistorico('Exame Físico', h.observacoes_exame);
            }
            if (h.hipotese_diagnostica) {
                html += campoHistoricoHalf('Hipótese Diagnóstica', h.hipotese_diagnostica);
            }
            if (h.cid10) {
                html += campoHistoricoHalf('CID-10', h.cid10);
            }
            if (h.conduta) {
                html += campoHistorico('Conduta', h.conduta);
            }
            if (h.orientacoes) {
                html += campoHistorico('Orientações', h.orientacoes);
            }
            if (h.orientacoes_pos) {
                html += campoHistorico('Orientações Pós-atendimento', h.orientacoes_pos);
            }
            if (h.retorno_necessario && parseInt(h.retorno_necessario) === 1) {
                html += campoHistoricoHalf('Retorno', 'Sim — ' + escapeHtml(h.prazo_retorno || ''));
            }
            if (h.observacoes_finais) {
                html += campoHistorico('Observações Finais', h.observacoes_finais);
            }

            html += '</div>';
        }

        /* Receita e Exames */
        if (h.receita) {
            html += '<div style="margin-top:15px;padding:15px;background:rgba(240,178,79,.08);border-radius:10px;border-left:4px solid var(--gold);">';
            html += '<strong>💊 Receita Médica:</strong><pre style="white-space:pre-wrap;margin:8px 0 0;font-family:inherit;font-size:0.95rem;">' + escapeHtml(h.receita) + '</pre>';
            html += '</div>';
        }

        if (h.exames_solicitados) {
            html += '<div style="margin-top:10px;padding:15px;background:rgba(41,128,185,.08);border-radius:10px;border-left:4px solid #2980b9;">';
            html += '<strong>🔎 Exames Solicitados:</strong><pre style="white-space:pre-wrap;margin:8px 0 0;font-family:inherit;font-size:0.95rem;">' + escapeHtml(h.exames_solicitados) + '</pre>';
            if (h.observacoes_exames) {
                html += '<p style="margin-top:8px;color:#666;"><em>' + escapeHtml(h.observacoes_exames) + '</em></p>';
            }
            html += '</div>';
        }

        if (h.anexos && h.anexos.length) {
            html += '<div style="margin-top:10px;padding:15px;background:rgba(39,174,96,.08);border-radius:10px;border-left:4px solid #27ae60;">';
            html += '<strong>📎 Anexos:</strong>' + htmlListaAnexos(h.anexos);
            html += '</div>';
        }

        if (!temProntuario && !h.receita && !h.exames_solicitados && !(h.anexos && h.anexos.length)) {
            html += '<p style="color:#888;font-style:italic;margin-top:10px;">Sem registros detalhados para esta consulta.</p>';
            if (h.status !== 'finalizada' && h.status !== 'cancelada' && ehHojeISO(h.data_consulta)) {
    html += '<div style="margin-top:15px;"><button type="button" class="confirm-btn" style="background:#27ae60;" onclick="preencherConsulta(' + h.id_consulta + ')">✍ Preencher dados desta consulta</button></div>';
            }
        } else {
            if (h.status !== 'finalizada' && h.status !== 'cancelada' && ehHojeISO(h.data_consulta)) {
    html += '<div style="margin-top:15px;"><button type="button" class="menu-btn" style="background:#2980b9;" onclick="preencherConsulta(' + h.id_consulta + ')">✏️ Editar / Visualizar nos formulários</button></div>';
            }
        }

        if (h.status === 'finalizada') {
            html += '<div style="margin-top:15px;padding:15px;background:rgba(142,68,173,.07);border-radius:10px;border-left:4px solid #8e44ad;">';
            html += '<strong>📝 Adendos</strong>';
            if (h.adendos && h.adendos.length) {
                h.adendos.forEach(function(ad) {
                    html += '<div style="margin-top:8px;padding-left:10px;border-left:2px solid #8e44ad;"><small style="color:#888;">' + escapeHtml(ad.criado_em || '') + '</small><div style="white-space:pre-wrap;">' + escapeHtml(ad.texto || '') + '</div></div>';
                });
            } else {
                html += '<p style="color:#888;font-style:italic;margin:6px 0;">Nenhum adendo registrado.</p>';
            }
            html += '<textarea id="adendoTexto' + h.id_consulta + '" class="field" rows="2" style="margin-top:10px;" placeholder="Registrar adendo (o prontuário original não é alterado)"></textarea>';
            html += '<button type="button" class="menu-btn" style="margin-top:8px;background:#8e44ad;" onclick="salvarAdendo(' + h.id_consulta + ')">Adicionar adendo</button>';
            html += '</div>';
            html += '<div style="margin-top:12px;"><a class="menu-btn" style="background:#34495e;text-decoration:none;display:inline-block;" target="_blank" href="medico_imprimir_prontuario.php?id_consulta=' + h.id_consulta + '">🖨 Imprimir Prontuário / PDF</a> ' +
                '<a class="menu-btn" style="background:#8a6d1d;text-decoration:none;display:inline-block;margin-left:8px;" target="_blank" href="medico_imprimir_receita.php?id_consulta=' + h.id_consulta + '">🖨 Imprimir Receita</a></div>';
        }

        html += '</div></details>';
    });

    container.innerHTML = html;
}

function ehHojeISO(d) {
    var t = new Date();
    var hoje = t.getFullYear() + '-' + ('0' + (t.getMonth() + 1)).slice(-2) + '-' + ('0' + t.getDate()).slice(-2);
    return d === hoje;
}

function htmlListaAnexos(anexos) {
    var out = '<ul style="margin:8px 0 0;padding-left:20px;">';
    anexos.forEach(function(a) {
        out += '<li><a href="api/arquivo_download.php?id=' + a.id_arquivo + '" target="_blank" rel="noopener">' +
               escapeHtml(a.titulo) + '</a> <small style="color:#888;">(' + escapeHtml(a.nome_original) + ')</small></li>';
    });
    return out + '</ul>';
}

function preencherAnexos(anexos) {
    var el = document.getElementById('prontAnexos');
    if (!el) return;
    if (!anexos || !anexos.length) {
        el.innerHTML = '<span style="color:#888;">Nenhum anexo compartilhado pelo paciente.</span>';
        return;
    }
    el.innerHTML = htmlListaAnexos(anexos);
}

function campoHistorico(label, valor) {
    return '<div class="field-group full"><label>' + label + '</label>' +
        '<div class="field">' + escapeHtml(valor) + '</div></div>';
}

function campoHistoricoHalf(label, valor) {
    return '<div class="field-group"><label>' + label + '</label>' +
        '<div class="field">' + escapeHtml(valor) + '</div></div>';
}


/* ===================== AGENDA ===================== */

var DIAS_PT = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];

function formatarData(iso) {
    var partes = iso.split('-');
    var diaSemana = DIAS_PT[new Date(iso + 'T00:00:00').getDay()];
    return partes[2] + '/' + partes[1] + '/' + partes[0] + ' (' + diaSemana + ')';
}

function carregarAgenda() {

    fetch('api/medico_agenda_listar.php')
    .then(function(r) { return r.json(); })
    .then(function(blocos) {

        var tbody = document.getElementById('tabelaAgenda');

        if (!blocos.length) {
            tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;color:var(--text-muted)">Nenhuma disponibilidade cadastrada.</td></tr>';
            return;
        }

        tbody.innerHTML = blocos.map(function(b) {
            return '<tr>' +
                '<td>' + formatarData(b.data_disp) + '</td>' +
                '<td>' + b.hora_inicio.slice(0,5) + '</td>' +
                '<td>' + b.hora_fim.slice(0,5) + '</td>' +
                '<td><button class="menu-btn" style="background:var(--danger,#e74c3c);color:#fff;" onclick="excluirBlocoAgenda(' + b.id + ')">Remover</button></td>' +
                '</tr>';
        }).join('');

    })
    .catch(function() {
        document.getElementById('tabelaAgenda').innerHTML =
            '<tr><td colspan="4" style="color:red">Erro ao carregar agenda.</td></tr>';
    });
}

function salvarBlocoAgenda() {

    var data     = document.getElementById('agDataInicio').value;
    var inicio   = document.getElementById('agHoraInicio').value;
    var fim      = document.getElementById('agHoraFim').value;
    var recorr   = document.getElementById('agRecorrente').checked ? 1 : 0;
    var semanas  = document.getElementById('agNumSemanas').value;

    if (!data || !inicio || !fim) {
        alert('Preencha data, hora início e hora fim.');
        return;
    }

    var form = new FormData();
    form.append('data_inicio',  data);
    form.append('hora_inicio',  inicio);
    form.append('hora_fim',     fim);
    form.append('recorrente',   recorr);
    form.append('num_semanas',  semanas);

    fetch('api/medico_agenda_salvar.php', { method: 'POST', body: form })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.sucesso) {
            var msg = res.inseridos > 1
                ? res.inseridos + ' datas adicionadas com sucesso!'
                : 'Disponibilidade adicionada!';
            alert(msg);
            carregarAgenda();
        } else {
            alert('Erro: ' + (res.erro || 'Desconhecido'));
        }
    })
    .catch(function() { alert('Erro de comunicação com o servidor.'); });
}

function excluirBlocoAgenda(id) {

    if (!confirm('Remover este bloco de disponibilidade?')) return;

    var form = new FormData();
    form.append('id', id);

    fetch('api/medico_agenda_excluir.php', { method: 'POST', body: form })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.sucesso) {
            carregarAgenda();
        } else {
            alert('Erro: ' + (res.erro || 'Desconhecido'));
        }
    })
    .catch(function() { alert('Erro de comunicação com o servidor.'); });
}

function toggleRecorrencia() {
    var grupo = document.getElementById('grupoSemanas');
    grupo.style.display = document.getElementById('agRecorrente').checked
        ? 'block' : 'none';
}


/* ==============================
   AUTO-SAVE (RASCUNHOS LOCAIS)
============================== */

var autoSaveTimer = null;

function configurarAutoSaveProntuario() {
    var campos = document.querySelectorAll('#atendimentoConteudo input, #atendimentoConteudo textarea, #atendimentoConteudo select');
    campos.forEach(function(c) {
        c.addEventListener('input', agendarAutoSave);
        c.addEventListener('change', agendarAutoSave);
    });
}

function agendarAutoSave() {
    if (!consultaAtualId || modoLeitura) return;
    clearTimeout(autoSaveTimer);
    autoSaveTimer = setTimeout(salvarRascunhoLocal, 1000);
}

function salvarRascunhoLocal() {
    if (!consultaAtualId || modoLeitura) return;

    var dados = {
        queixa: getVal('prontQueixa'),
        histDoenca: getVal('prontHistDoenca'),
        antecedentes: getVal('prontAntecedentes'),
        alergias: getVal('prontAlergias'),
        medicamentos: getVal('prontMedicamentos'),
        histFamiliar: getVal('prontHistFamiliar'),
        habitos: getVal('prontHabitos'),
        pa: getVal('prontPA'),
        fc: getVal('prontFC'),
        fr: getVal('prontFR'),
        temp: getVal('prontTemp'),
        peso: getVal('prontPeso'),
        altura: getVal('prontAltura'),
        sat: getVal('prontSat'),
        obsExame: getVal('prontObsExame'),
        hipotese: getVal('prontHipotese'),
        cid: getVal('prontCID'),
        conduta: getVal('prontConduta'),
        orientacoes: getVal('prontOrientacoes'),
        receita: getVal('prontReceitaMed'),
        exames: getVal('prontExamesSol'),
        examesObs: getVal('prontExamesObs'),
        orientacoesPos: getVal('prontOrientacoesPos'),
        prazoRetorno: getVal('prontPrazoRetorno'),
        obsFinais: getVal('prontObsFinais'),
        salvoEm: new Date().toISOString()
    };

    try {
        localStorage.setItem('draft_prontuario_' + consultaAtualId, JSON.stringify(dados));
    } catch(e) {}
}

function restaurarRascunhoProntuario(idConsulta) {
    if (!idConsulta) return;
    try {
        var str = localStorage.getItem('draft_prontuario_' + idConsulta);
        if (!str) return;
        var d = JSON.parse(str);
        if (!d) return;

        // Se campo estiver vazio na tela, restaura do rascunho
        function restoreIfEmpty(id, val) {
            var el = document.getElementById(id);
            if (el && !el.value && val) el.value = val;
        }

        restoreIfEmpty('prontQueixa', d.queixa);
        restoreIfEmpty('prontHistDoenca', d.histDoenca);
        restoreIfEmpty('prontAntecedentes', d.antecedentes);
        restoreIfEmpty('prontAlergias', d.alergias);
        restoreIfEmpty('prontMedicamentos', d.medicamentos);
        restoreIfEmpty('prontHistFamiliar', d.histFamiliar);
        restoreIfEmpty('prontHabitos', d.habitos);
        restoreIfEmpty('prontPA', d.pa);
        restoreIfEmpty('prontFC', d.fc);
        restoreIfEmpty('prontFR', d.fr);
        restoreIfEmpty('prontTemp', d.temp);
        restoreIfEmpty('prontPeso', d.peso);
        restoreIfEmpty('prontAltura', d.altura);
        restoreIfEmpty('prontSat', d.sat);
        restoreIfEmpty('prontObsExame', d.obsExame);
        restoreIfEmpty('prontHipotese', d.hipotese);
        restoreIfEmpty('prontCID', d.cid);
        restoreIfEmpty('prontConduta', d.conduta);
        restoreIfEmpty('prontOrientacoes', d.orientacoes);
        restoreIfEmpty('prontReceitaMed', d.receita);
        restoreIfEmpty('prontExamesSol', d.exames);
        restoreIfEmpty('prontExamesObs', d.examesObs);
        restoreIfEmpty('prontOrientacoesPos', d.orientacoesPos);
        restoreIfEmpty('prontPrazoRetorno', d.prazoRetorno);
        restoreIfEmpty('prontObsFinais', d.obsFinais);

        if (window.showToast) {
            window.showToast('Rascunho recuperado automaticamente.', 'info');
        }
    } catch(e) {}
}

function limparRascunhoLocal(idConsulta) {
    if (!idConsulta) return;
    try {
        localStorage.removeItem('draft_prontuario_' + idConsulta);
    } catch(e) {}
}


/* ==============================
   IMPRESSÃO TIMBRADA DE RECEITAS
============================== */

function abrirModalImpressaoReceita() {
    var nomePaciente = document.getElementById('atdNomePaciente') 
        ? document.getElementById('atdNomePaciente').textContent.replace('Paciente', '').trim() 
        : 'Paciente';

    var medicamentos = getVal('prontReceitaMed').trim();
    var exames = getVal('prontExamesSol').trim();
    var orientacoes = getVal('prontOrientacoes').trim();
    var cid = getVal('prontCID').trim();

    var html = '<div class="printable-area print-prescription-card" id="areaImpressaoDoc">' +
        '<div class="print-header">' +
            '<div>' +
                '<div class="print-clinic-title">NyesClinic</div>' +
                '<div class="print-meta">Receituário e Orientações Médicas</div>' +
            '</div>' +
            '<div style="text-align:right;">' +
                '<div class="print-meta">Data: ' + new Date().toLocaleDateString('pt-BR') + '</div>' +
            '</div>' +
        '</div>' +
        '<div class="print-content-block" style="background:#f8fafc;padding:12px;border-radius:6px;margin-bottom:18px;">' +
            '<strong>Paciente:</strong> ' + escapeHtml(nomePaciente) +
            (cid ? ' | <strong>CID-10:</strong> ' + escapeHtml(cid) : '') +
        '</div>';

    if (medicamentos) {
        html += '<div class="print-content-block">' +
            '<h4>💊 Prescrição Medicamentosa:</h4>' +
            '<div style="white-space:pre-wrap;font-size:0.95rem;line-height:1.7;">' + escapeHtml(medicamentos) + '</div>' +
        '</div>';
    }

    if (exames) {
        html += '<div class="print-content-block">' +
            '<h4>🔎 Exames Solicitados:</h4>' +
            '<div style="white-space:pre-wrap;font-size:0.95rem;line-height:1.7;">' + escapeHtml(exames) + '</div>' +
        '</div>';
    }

    if (orientacoes) {
        html += '<div class="print-content-block">' +
            '<h4>📋 Recomendações e Conduta:</h4>' +
            '<div style="white-space:pre-wrap;font-size:0.95rem;line-height:1.7;">' + escapeHtml(orientacoes) + '</div>' +
        '</div>';
    }

    if (!medicamentos && !exames && !orientacoes) {
        html += '<p style="color:#888;text-align:center;padding:24px;">Nenhuma prescrição ou exame registrado nesta consulta ainda.</p>';
    }

    html += '<div class="print-footer">' +
        '<div class="print-meta">Documento emitido eletronicamente via NyesClinic.</div>' +
        '<div class="print-signature">Assinatura / Carimbo Médico</div>' +
    '</div></div>';

    var botoes = '<button class="btn btn-secondary" onclick="window.fecharModal()">Fechar</button>' +
                 '<button class="btn btn-primary" onclick="window.imprimirElemento(\'areaImpressaoDoc\', \'Receituário\')">🖨️ Imprimir (A4)</button>';

    window.abrirModal('Impressão de Receituário e Documentos', html, botoes);
}


/* ==============================
   POLLING DE FILA (NOTIFICAÇÃO)
============================== */

var ultimoTotalAguardando = null;

function iniciarPollingFilaMedico() {
    setInterval(function() {
        var badge = document.querySelector('nav .nav-badge');
        var atual = badge ? parseInt(badge.textContent.trim(), 10) : 0;

        if (ultimoTotalAguardando !== null && atual > ultimoTotalAguardando) {
            if (window.showToast) {
                window.showToast('🔔 Novo paciente deu entrada na recepção!', 'info', 6000);
            }
        }
        ultimoTotalAguardando = atual;
    }, 25000);
}

document.addEventListener('DOMContentLoaded', function() {
    iniciarPollingFilaMedico();
});


/* ==============================
   ADENDO EM CONSULTA FINALIZADA
============================== */

function salvarAdendo(idConsulta) {
    var campo = document.getElementById('adendoTexto' + idConsulta);
    var texto = campo ? campo.value.trim() : '';
    if (!texto) {
        alert('Digite o texto do adendo.');
        return;
    }
    var form = new FormData();
    form.append('id_consulta', idConsulta);
    form.append('texto', texto);

    fetch('api/medico_salvar_adendo.php', { method: 'POST', body: form })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res && res.sucesso) {
                iniciarAtendimento(consultaAtualId, pacienteAtualId, 'abaHistorico');
            } else {
                alert((res && res.erro) ? res.erro : 'Não foi possível salvar o adendo.');
            }
        })
        .catch(function() { alert('Falha na comunicação com o servidor.'); });
}


/* ==============================
   MODELOS DE RECEITA / EXAMES (salvos neste navegador)
============================== */

function lerModelos(chave) {
    try { return JSON.parse(localStorage.getItem(chave) || '[]'); } catch (e) { return []; }
}

function salvarModelo(chave, idCampo, rotulo) {
    var campo = document.getElementById(idCampo);
    if (!campo || !campo.value.trim()) { alert('Preencha o campo antes de salvar como modelo.'); return; }
    var nome = prompt('Nome do modelo de ' + rotulo + ':');
    if (!nome) return;
    var modelos = lerModelos(chave).filter(function(m) { return m.nome !== nome; });
    modelos.push({ nome: nome, texto: campo.value });
    localStorage.setItem(chave, JSON.stringify(modelos));
    atualizarSelectModelos(chave, idCampo + 'Modelos');
}

function aplicarModelo(chave, idCampo, nome) {
    var campo = document.getElementById(idCampo);
    var m = lerModelos(chave).filter(function(x) { return x.nome === nome; })[0];
    if (campo && m && !campo.readOnly) campo.value = m.texto;
}

function atualizarSelectModelos(chave, idSelect) {
    var sel = document.getElementById(idSelect);
    if (!sel) return;
    sel.innerHTML = '<option value="">Usar modelo...</option>';
    lerModelos(chave).forEach(function(m) {
        var o = document.createElement('option');
        o.value = m.nome; o.textContent = m.nome;
        sel.appendChild(o);
    });
}

document.addEventListener('DOMContentLoaded', function() {
    atualizarSelectModelos('modelosReceita', 'prontReceitaMedModelos');
    atualizarSelectModelos('modelosExames', 'prontExamesSolModelos');
});
