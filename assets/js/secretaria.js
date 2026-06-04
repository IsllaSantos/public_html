function showSecretariaTab(tab){

    document
    .querySelectorAll('.prontuario-content')
    .forEach(item=>{
        item.classList.remove('active');
    });

    document
    .getElementById(tab)
    .classList.add('active');
}