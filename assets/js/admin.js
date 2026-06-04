function showAdminTab(tab){

    document.querySelectorAll('.admin-tab')
    .forEach(item=>{
        item.style.display='none';
    });

    document.getElementById(tab)
    .style.display='block';
}

window.onload = function(){

    document.querySelectorAll('.admin-tab')
    .forEach(item=>{
        item.style.display='none';
    });

    document.getElementById('dashboard')
    .style.display='block';
}