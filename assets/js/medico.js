function showScreen(screen,event){

    document.querySelectorAll('.screen')
    .forEach(item=>{
        item.classList.remove('active');
    });

    document.querySelectorAll('nav button')
    .forEach(btn=>{
        btn.classList.remove('active');
    });

    document.getElementById(screen)
    .classList.add('active');

    if(event){
        event.target.classList.add('active');
    }

}

function showProntuario(tab,event){

    document.querySelectorAll('.prontuario-content')
    .forEach(item=>{
        item.classList.remove('active');
    });

    document.querySelectorAll('.prontuario-btn')
    .forEach(btn=>{
        btn.classList.remove('active');
    });

    document.getElementById(tab)
    .classList.add('active');

    if(event){
        event.target.classList.add('active');
    }

}

function abrirProntuarioPaciente(nome){

    document.querySelectorAll('.screen')
    .forEach(item=>{
        item.classList.remove('active');
    });

    document.getElementById('prontuario')
    .classList.add('active');

    const titulo =
    document.getElementById(
        'nomePacienteProntuario'
    );

    if(titulo){
        titulo.innerText = nome;
    }

}