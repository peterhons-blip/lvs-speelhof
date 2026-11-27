/* Leerlingen zoeken — alleen actief op de leerlingenlijst. */
(() => {
    'use strict';

    const qInput = document.getElementById('q');
    const studentGrid = document.getElementById('studentGrid');
    const infoCard = document.getElementById('infoCard');
    const resultsHeading = document.getElementById('resultsHeading');
    const resultsCount = document.getElementById('resultsCount');
    const students = Array.isArray(window.LVS_STUDENTS) ? window.LVS_STUDENTS : [];
    const baseHref = studentGrid?.dataset.linkBase || '/leerlingen/';
    const linkLabel = studentGrid?.dataset.linkLabel || 'Bekijk leerlinggegevens en meldingen';

    if (!qInput || !studentGrid || !infoCard) return;

    const normalize = value => String(value ?? '').trim();
    const normalizeClass = value => normalize(value).toLocaleLowerCase('nl-BE').replace(/\s+/g, ' ');
    const fullName = student => [student.voornaam, student.achternaam ?? student.naam].map(normalize).filter(Boolean).join(' ');
    const isClassQuery = query => /^\s*\d{1,2}\s*[a-zà-ÿ]/i.test(query);

    function matches(query) {
        const term = normalize(query).toLocaleLowerCase('nl-BE');
        if (!term) return [];
        if (isClassQuery(query)) {
            const classQuery = normalizeClass(query);
            return students.filter(student => normalizeClass(student.klas).startsWith(classQuery));
        }
        if (term.length < 2) return [];
        return students.filter(student =>
            fullName(student).toLocaleLowerCase('nl-BE').includes(term) ||
            normalizeClass(student.klas).includes(term)
        );
    }

    function clearResults() {
        studentGrid.replaceChildren();
        infoCard.dataset.hidden = 'true';
        if (resultsHeading) resultsHeading.dataset.hidden = 'true';
    }

    function render() {
        const query = normalize(qInput.value);
        clearResults();
        if (!query || (!isClassQuery(query) && query.length < 2)) return;

        const found = matches(query);
        if (!found.length) {
            infoCard.textContent = 'Geen leerlingen gevonden voor "' + query + '".';
            infoCard.dataset.hidden = 'false';
            return;
        }

        if (resultsHeading) resultsHeading.dataset.hidden = 'false';
        if (resultsCount) resultsCount.textContent = found.length + (found.length === 1 ? ' leerling' : ' leerlingen');

        const fragment = document.createDocumentFragment();
        found.forEach(student => {
            const link = document.createElement('a');
            link.href = baseHref + encodeURIComponent(student.id);
            link.className = 'lvs-student-card';

            const avatar = document.createElement('span');
            avatar.className = 'lvs-student-avatar';
            avatar.textContent = normalize(student.voornaam).charAt(0).toLocaleUpperCase('nl-BE') || 'L';

            if (window.LVS_PHOTO_BASE) {
                const img=document.createElement('img');
                img.src=window.LVS_PHOTO_BASE+encodeURIComponent(student.id);
                img.alt='';
                img.loading='lazy';
                img.className='student-photo';
                img.addEventListener('error',()=>img.remove(),{once:true});
                avatar.append(img);
            }
            const textWrap = document.createElement('span');
            textWrap.className = 'lvs-student-meta';

            const name = document.createElement('strong');
            name.textContent = fullName(student);
            const sub = document.createElement('small');
            sub.textContent = linkLabel;
            textWrap.append(name, sub);

            const right = document.createElement('span');
            right.className = 'lvs-student-trailing';

            const classBadge = document.createElement('span');
            classBadge.className = 'lvs-class-badge';
            classBadge.textContent = normalize(student.klas) || 'Geen klas';

            const arrow = document.createElement('span');
            arrow.className = 'lvs-student-arrow';
            arrow.textContent = '→';
            arrow.setAttribute('aria-hidden', 'true');
            right.append(classBadge, arrow);

            link.append(avatar, textWrap, right);
            fragment.appendChild(link);
        });

        studentGrid.appendChild(fragment);
    }

    let timer;
    qInput.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(render, 150);
    });
    qInput.addEventListener('search', render);
})();
