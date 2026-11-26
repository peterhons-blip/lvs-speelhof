(() => {
    const root=document.querySelector('[data-personal-catalog]');
    if(!root)return;
    const form=root.querySelector('[data-catalog-search]'), results=root.querySelector('[data-catalog-results]'), feedback=root.querySelector('[data-catalog-feedback]');
    let timer, controller, sequence=0;
    const urlFor=()=>{
        const url=new URL(form.action,location.href);
        for(const [key,value] of new FormData(form))if(String(value).trim())url.searchParams.set(key,String(value).trim());
        return url;
    };
    async function load(url){
        clearTimeout(timer);
        controller?.abort();
        controller=new AbortController();
        const id=++sequence;
        results.setAttribute('aria-busy','true');
        feedback.textContent='Boeken zoeken…';
        try{
            const response=await fetch(url,{headers:{'X-Requested-With':'XMLHttpRequest','Accept':'text/html'},credentials:'same-origin',signal:controller.signal,cache:'no-store'});
            if(!response.ok||response.redirected)throw Error('Ophalen mislukt');
            const html=await response.text();
            if(id!==sequence)return;
            const doc=new DOMParser().parseFromString(html,'text/html');
            if(!doc.querySelector('.personal-catalog-table'))throw Error('Ongeldig antwoord');
            results.innerHTML=html;
            history.replaceState(null,'',url);
            feedback.textContent='';
        }catch(error){
            if(error.name!=='AbortError'&&id===sequence)feedback.textContent='Zoeken mislukt. Probeer opnieuw of vernieuw de pagina.';
        }finally{
            if(id===sequence)results.removeAttribute('aria-busy');
        }
    }
    form.addEventListener('submit',e=>{e.preventDefault();load(urlFor());});
    form.querySelector('[name=q]').addEventListener('input',()=>{clearTimeout(timer);controller?.abort();sequence++;timer=setTimeout(()=>load(urlFor()),250);});
    form.addEventListener('change',e=>{if(e.target.matches('select'))load(urlFor());});
    root.querySelector('[data-catalog-clear]').addEventListener('click',()=>{form.querySelector('[name=q]').value='';form.querySelectorAll('select').forEach(s=>s.selectedIndex=0);load(urlFor());});
    results.addEventListener('click',e=>{const link=e.target.closest('.portal-pagination-controls a');if(!link||e.ctrlKey||e.metaKey||e.shiftKey||e.altKey)return;e.preventDefault();load(new URL(link.href));});
})();
