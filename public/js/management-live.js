(() => {
    const root = document.querySelector('[data-live-management]');
    if (!root) return;
    const search = root.querySelector('[data-live-search]');
    const results = root.querySelector('[data-management-results]');
    const total = root.querySelector('[data-management-total]');
    const form = root.querySelector('[data-bulk-form]');
    const count = root.querySelector('[data-selected-count]');
    const selectAll = root.querySelector('[data-select-all]');
    const action = root.querySelector('[data-bulk-action]');
    const role = root.querySelector('[data-bulk-role]');
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
        if (selectAll) {
            selectAll.checked = boxes.length > 0 && n === boxes.length;
            selectAll.indeterminate = n > 0 && n < boxes.length;
        }
    }
    function updateAction() {
        if (role && action) role.hidden = action.value !== 'role';
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
        if (e.target.matches('[data-bulk-action]')) updateAction();
    });
    root.addEventListener('click', e => {
        const link = e.target.closest('[data-management-results] a[href]');
        if (!link || link.origin !== window.location.origin) return;
        e.preventDefault();
        load(link.href);
    });
    if (form) form.addEventListener('submit', e => {
        const n = chosen().length;
        if (!n) { e.preventDefault(); alert('Selecteer eerst minstens één persoon.'); return; }
        if (action && !action.value) { e.preventDefault(); alert('Kies eerst een bulkactie.'); return; }
        if (!confirm('Deze actie toepassen op ' + n + ' geselecteerde personen?')) e.preventDefault();
    });
    updateAction();
    updateSelection();
})();
