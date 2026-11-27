(() => {
    const root=document.getElementById('my-classes');
    if(!root)return;
    const search=document.getElementById('my-class-search');
    const status=document.getElementById('my-class-status');
    const classes=[...root.querySelectorAll('details[data-roster-url]')];
    const pending=new Map();
    let opened=[];
    try{opened=JSON.parse(sessionStorage.getItem(root.dataset.sessionKey)||'[]');if(!Array.isArray(opened))opened=[];}catch{}
    const norm=s=>s.toLocaleLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'');
    function remember(){
        if(search.value.trim())return;
        try{sessionStorage.setItem(root.dataset.sessionKey,JSON.stringify(classes.filter(d=>d.open).map(d=>d.dataset.classId)));}catch{}
    }
    function load(d){
        if(d.dataset.loaded)return Promise.resolve(true);
        if(pending.has(d))return pending.get(d);
        const target=d.querySelector('[data-class-roster]');
        target.textContent='Leerlingen laden…';
        const work=fetch(d.dataset.rosterUrl,{credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest'}})
            .then(r=>{if(!r.ok||r.redirected)throw Error('load');return r.text();})
            .then(html=>{target.innerHTML=html;d.dataset.loaded='1';return true;})
            .catch(()=>{target.textContent='Leerlingen konden niet worden geladen. ';const retry=document.createElement('button');retry.type='button';retry.textContent='Opnieuw proberen';retry.onclick=()=>load(d);target.append(retry);return false;})
            .finally(()=>pending.delete(d));
        pending.set(d,work);return work;
    }
    classes.forEach(d=>{
        d.open=opened.includes(d.dataset.classId);
        d.addEventListener('toggle',()=>{if(d.open)load(d);remember();});
        if(d.open)load(d);
    });
    let generation=0,timer,previousOpen=null;
    search.addEventListener('input',()=>{
        clearTimeout(timer);
        const token=++generation;
        const words=norm(search.value.trim()).split(/\s+/).filter(Boolean);
        if(!words.length){
            classes.forEach(d=>{d.hidden=false;d.querySelectorAll('[data-pupil-row]').forEach(p=>p.hidden=false);if(previousOpen)d.open=previousOpen.includes(d);});
            previousOpen=null;status.textContent='';return;
        }
        if(!previousOpen)previousOpen=classes.filter(d=>d.open);
        timer=setTimeout(async()=>{
            status.textContent='Zoeken…';
            // Limit parallel network requests for staff with many classes.
            for(let i=0;i<classes.length;i+=4){if(token!==generation)return;await Promise.all(classes.slice(i,i+4).map(load));}
            if(token!==generation)return;
            let matches=0,failed=0;
            classes.forEach(d=>{
                const heading=norm(d.querySelector('summary').textContent);
                const whole=words.every(w=>heading.includes(w));
                const pupils=[...d.querySelectorAll('[data-pupil-row]')];
                let visible=0;
                pupils.forEach(p=>{const hay=heading+' '+norm(p.textContent);p.hidden=!words.every(w=>hay.includes(w));if(!p.hidden)visible++;});
                if(!d.dataset.loaded)failed++;
                d.hidden=!whole&&!visible&&!!d.dataset.loaded;d.open=!d.hidden;
                matches+=visible;
            });
            status.textContent=matches+' passende leerlingen'+(failed?' · '+failed+' klassen konden niet worden geladen':'')+'.';
        },250);
    });
})();
