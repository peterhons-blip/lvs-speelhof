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
    const confirmBox=form.querySelector('#confidential-release-confirm');
    const confirm=form.querySelector('#confirm_confidential_release');
    function updateRelease(){
        const needs=!!confidential?.checked && (student.checked||parent.checked);
        if(confirmBox)confirmBox.hidden=!needs;
        if(confirm){confirm.required=needs;if(!needs)confirm.checked=false;}
        note.textContent=confidential?.checked?'Vertrouwelijkheid beperkt de inzage door medewerkers. Je kunt bewust vrijgeven aan leerling en/of co-accounts.':'Kies wie deze melding mag raadplegen.';
    }
    function syncConfidential(){
        if(confidential?.checked){
            previous=[student.checked,parent.checked];
            student.checked=false;parent.checked=false;
        }else if(previous){
            [student.checked,parent.checked]=previous;previous=null;
        }
        if(confirm)confirm.checked=false;
        updateRelease();
    }
    student.addEventListener('change',()=>{if(confirm)confirm.checked=false;updateRelease();});
    parent.addEventListener('change',()=>{if(confirm)confirm.checked=false;updateRelease();});
    cat.addEventListener('change',()=>{filter();});
    confidential?.addEventListener('change',syncConfidential);
    filter(true);
    updateRelease();
})();
