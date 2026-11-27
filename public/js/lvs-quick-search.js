(() => {
    const root=document.querySelector('[data-lvs-quick-search]');
    if(!root)return;
    const input=root.querySelector('input'),out=root.querySelector('[data-search-results]'),status=root.querySelector('[data-search-status]');
    let timer,controller,version=0;
    input.addEventListener('input',()=>{
        clearTimeout(timer);controller?.abort();
        const current=++version,term=input.value.trim();
        out.replaceChildren();status.textContent='';
        if(term.length<2)return;
        timer=setTimeout(async()=>{
            controller=new AbortController();status.textContent='Zoeken…';
            try{
                const url=new URL(root.dataset.lvsQuickSearch,location.origin);url.searchParams.set('q',term);
                const res=await fetch(url,{signal:controller.signal,credentials:'same-origin',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}});
                if(!res.ok||res.redirected)throw Error('search');
                const rows=await res.json();if(current!==version)return;
                status.textContent=rows.length?(rows.length===20?'Eerste 20 resultaten. Verfijn indien nodig.':rows.length+' resultaten'):'Geen leerlingen gevonden.';
                rows.forEach(p=>{
                    const a=document.createElement('a');a.className='class-roster-row';a.href=p.url;
                    const img=document.createElement('img');img.className='student-photo';img.src=p.photo;img.alt='';img.loading='lazy';
                    const info=document.createElement('span');info.className='my-pupil-info';
                    const name=document.createElement('strong');name.textContent=p.name;
                    const cls=document.createElement('small');cls.textContent=p.class||'Geen klas';
                    info.append(name,cls);a.append(img,info);out.append(a);
                });
            }catch(e){if(e.name!=='AbortError'&&current===version)status.textContent='Zoeken mislukt. Probeer opnieuw.';}
        },200);
    });
})();
