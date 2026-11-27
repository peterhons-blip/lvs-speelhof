(() => {
    const toggle=document.querySelector('.portal-mobile-menu'), nav=document.getElementById('portal-navigation'), backdrop=document.querySelector('.portal-mobile-backdrop'), close=nav?.querySelector('.portal-mobile-close');
    if(!toggle||!nav||!backdrop||!close)return;
    const mobile=matchMedia('(max-width: 800px)');
    document.documentElement.classList.add('portal-nav-enhanced');
    function setOpen(open,restore=false){
        open=mobile.matches&&open;
        document.body.classList.toggle('portal-mobile-nav-open',open);
        toggle.setAttribute('aria-expanded',String(open));
        backdrop.hidden=!open;
        nav.inert=mobile.matches&&!open;
        if(open)close.focus();
        else if(restore&&mobile.matches)toggle.focus();
    }
    toggle.addEventListener('click',()=>setOpen(toggle.getAttribute('aria-expanded')!=='true',true));
    close.addEventListener('click',()=>setOpen(false,true));
    backdrop.addEventListener('click',()=>setOpen(false,true));
    document.addEventListener('keydown',e=>{if(toggle.getAttribute('aria-expanded')!=='true')return;if(e.key==='Escape'){e.preventDefault();setOpen(false,true);}if(e.key==='Tab'){const items=[...nav.querySelectorAll('a[href],button:not([disabled])')].filter(el=>el.getClientRects().length);const first=items[0],last=items[items.length-1];if(e.shiftKey&&document.activeElement===first){e.preventDefault();last?.focus();}else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first?.focus();}}});
    mobile.addEventListener('change',()=>setOpen(false));
    setOpen(false);
})();
