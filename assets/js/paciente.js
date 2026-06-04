function showPacienteTab(tab,event){

    document
    .querySelectorAll('.paciente-tab')
    .forEach(item => {
        item.classList.remove('active');
    });

    document
    .getElementById(tab)
    .classList.add('active');
}

function enviarArquivo(){

    let nome =
    document.getElementById('nomeArquivo').value;

    let consulta =
    document.getElementById('consultaArquivo').value;

    if(nome === ''){

        alert('Digite o nome do arquivo');

        return;
    }

    let tabela =
    document.getElementById('arquivosTabela');

    tabela.innerHTML += `

        <tr>

            <td>
                Hoje
            </td>

            <td>
                ${nome}
            </td>

            <td>
                ${consulta}
            </td>

            <td>

                <button class="menu-btn">
                    Compartilhar
                </button>

            </td>

        </tr>

    `;

    alert('Arquivo enviado com sucesso.');

    document.getElementById('nomeArquivo').value='';
}