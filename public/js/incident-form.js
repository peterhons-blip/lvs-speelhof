(() => {
    const form=document.getElementById('meldingForm');
    if(!form)return;
    const cat=form.querySelector('#categorie_id'), kind=form.querySelector('#soort_id');
    const student=form.querySelector('#visible_student'), parent=form.querySelector('#visible_coaccount'), confidential=form.querySelector('#vertrouwelijk');
    const note=form.querySelector('#incident-visibility-note');
    const options=[...kind.options].filter(o=>o.dataset.catid);
    const placeholder=kind.options[0];
    const old=kind.dataset.old;
    let previous=null;
    function filter(restore=false){
        kind.value='';
        options.forEach(o=>{const show=o.dataset.catid===cat.value;o.hidden=!show;o.disabled=!show;});
        placeholder.textContent=cat.value?'— Kies soort —':'— Kies eerst een categorie —';
        if(restore && options.some(o=>!o.disabled&&o.value===old))kind.value=old;
    }
    function syncConfidential(){
        const locked=!!confidential?.checked;
        if(locked){
            if(!previous)previous=[student.checked,parent.checked];
            student.checked=false;parent.checked=false;
        }else if(previous){
            [student.checked,parent.checked]=previous;previous=null;
        }
        student.disabled=locked;parent.disabled=locked;
        note.textContent=locked?'Deze vertrouwelijke melding is niet zichtbaar voor leerling of co-accounts.':'Vertrouwelijke meldingen worden nooit vrijgegeven.';
    }
    cat.addEventListener('change',()=>{filter();});
    confidential?.addEventListener('change',syncConfidential);
    filter(true);
    syncConfidential();
})();
