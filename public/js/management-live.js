(() => {
    const root = document.querySelector('[data-live-management]');
    if (!root) return;
    const search = root.querySelector('[data-live-search]');
    const results = root.querySelector('[data-management-results]');
    const total = root.querySelector('[data-management-total]');
    const form = root.querySelector('[data-bulk-form]');
    const count = root.querySelector('[data-selected-count]');
    const action = root.querySelector('[data-bulk-action]');
    const role = root.querySelector('[data-bulk-role]');
    const roleSelect = role?.querySelector('select');
    const submit = root.querySelector('[data-bulk-submit]');
    const summary = root.querySelector('[data-bulk-summary]');
    let controller = null;
    let timer;
    let serial = 0;

    function chosen() {
        return [...results.querySelectorAll('[data-bulk-id]:checked')];
    }
    function updateSelection() {
        const boxes = [...results.querySelectorAll('[data-bulk-id]')];
        const n = chosen().length;
        if (count) count.textContent = n + ' geselecteerd (deze pagina)';
        const selectAll = results.querySelector('[data-select-all]');
        if (selectAll) {
            selectAll.checked = boxes.length > 0 && n === boxes.length;
            selectAll.indeterminate = n > 0 && n < boxes.length;
        }
        updateAction();
    }
    function updateAction() {
        if (!action) return;
        const isRole = action.value === 'role';
        if (role) role.style.display = isRole ? '' : 'none';
        if (roleSelect) {
            roleSelect.disabled = !isRole;
            roleSelect.required = isRole;
        }
        const n = chosen().length;
        const target = isRole && roleSelect?.value ? roleSelect.options[roleSelect.selectedIndex]?.text : '';
        const ready = n > 0 && !!action.value && (!isRole || !!roleSelect?.value);
        if (submit) submit.disabled = !ready;
        if (summary) {
            let message = 'Selecteer medewerkers en kies een actie. Er wordt nog niets gewijzigd.';
            if (n > 0 && !action.value) message = n + ' medewerkers geselecteerd. Kies welke wijziging je wilt uitvoeren.';
            else if (n > 0 && isRole && !target) message = n + ' medewerkers geselecteerd. Kies nu expliciet de nieuwe schoolrol.';
            else if (n > 0 && isRole) message = n + ' medewerkers krijgen de schoolrol "' + target + '". Hun aanmeldtoegang verandert niet.';
            else if (n > 0 && action.value === 'allow') message = n + ' medewerkers krijgen toegang tot het LVS. Hun schoolrol blijft behouden.';
            else if (n > 0 && action.value === 'block') message = n + ' medewerkers worden geblokkeerd voor aanmelden in het LVS. Hun schoolrol blijft behouden.';
            summary.textContent = message;
        }
    }

    async function load(url) {
        ++serial;
        const current = serial;
        if (controller) controller.abort();
        controller = new AbortController();
        root.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(url, {
                headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html'},
                signal: controller.signal
            });
            if (!response.ok) throw new Error('Ophalen mislukt');
            const html = await response.text();
            if (current !== serial) return;
            const doc = new DOMParser().parseFromString(html, 'text/html');
            const next = doc.querySelector('[data-management-results]');
            if (!next) throw new Error('Onverwacht antwoord');
            results.innerHTML = next.innerHTML;
            const nextTotal = doc.querySelector('[data-management-total]');
            if (nextTotal && total) total.textContent = nextTotal.textContent;
            window.history.replaceState(null, '', url);
            updateSelection();
        } catch (e) {
            if (e.name !== 'AbortError') {
                root.setAttribute('data-search-error', '1');
                console.error('Zoeken mislukt:', e);
            }
        } finally {
            if (current === serial) root.removeAttribute('aria-busy');
        }
    }
    function searchUrl() {
        const params = new URLSearchParams(window.location.search);
        const term = search.value.trim();
        if (term) params.set('zoek', term); else params.delete('zoek');
        params.delete('page');
        const qs = params.toString();
        return window.location.pathname + (qs ? '?' + qs : '');
    }
    if (search) search.closest('form')?.addEventListener('submit', event => {
        event.preventDefault();
        clearTimeout(timer);
        load(searchUrl());
    });
    if (search) search.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(() => load(searchUrl()), 250);
    });
    root.addEventListener('change', e => {
        if (e.target.matches('[data-bulk-id]')) updateSelection();
        if (e.target.matches('[data-select-all]')) {
            results.querySelectorAll('[data-bulk-id]').forEach(box => { box.checked = e.target.checked; });
            updateSelection();
        }
        if (e.target.matches('[data-bulk-action]') || e.target.matches('[data-bulk-role] select')) updateAction();
    });
    root.addEventListener('click', e => {
        const link = e.target.closest('[data-management-results] a[href]');
        if (!link || link.origin !== window.location.origin) return;
        // Only update table navigation asynchronously; detail/edit links navigate normally.
        const currentPath = window.location.pathname.replace(/\/$/, '');
        const targetPath = link.pathname.replace(/\/$/, '');
        if (currentPath !== targetPath) return;
        e.preventDefault();
        load(link.href);
    });
    if (form) form.addEventListener('submit', e => {
        const n = chosen().length;
        if (!n) { e.preventDefault(); alert('Selecteer eerst minstens één persoon.'); return; }
        if (action && !action.value) { e.preventDefault(); alert('Kies eerst een bulkactie.'); return; }
        const message = summary?.textContent || ('Deze actie toepassen op ' + n + ' geselecteerde personen?');
        if (!confirm(message + '\n\nWeet je zeker dat je deze wijziging wilt uitvoeren?')) e.preventDefault();
    });
    updateAction();
    updateSelection();
})();
