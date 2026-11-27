(() => {
    const root=document.querySelector('[data-dossier-tabs]');
    if(!root)return;
    const tabs=[...root.querySelectorAll('[data-dossier-tab]')];
    const panels=[...root.querySelectorAll('[data-dossier-panel]')];
    function select(key){
        if(!tabs.some(t=>t.dataset.dossierTab===key))key=tabs[0].dataset.dossierTab;
        tabs.forEach(t=>{const active=t.dataset.dossierTab===key;t.classList.toggle('is-selected',active);if(active)t.setAttribute('aria-current','true');else t.removeAttribute('aria-current');});
        panels.forEach(p=>p.hidden=p.dataset.dossierPanel!==key);
    }
    tabs.forEach(t=>t.addEventListener('click',e=>{e.preventDefault();history.replaceState(null,'','#'+t.dataset.dossierTab);select(t.dataset.dossierTab);}));
    window.addEventListener('hashchange',()=>select(location.hash.slice(1)));
    select(location.hash.slice(1));
})();
