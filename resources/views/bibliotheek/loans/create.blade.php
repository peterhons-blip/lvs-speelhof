@extends('layouts.app')
@section('title', 'Boeken uitlenen')
@section('breadcrumbs')
@include('partials.breadcrumbs', ['items' => [
    ['label' => 'Dashboard', 'url' => route('home')],
    ['label' => 'Bibliotheek', 'url' => route('bibliotheek.index')],
    ['label' => 'Uitleningen', 'url' => route('bibliotheek.loans.index')],
    ['label' => 'Boeken uitlenen'],
]])
@endsection

@section('content')
<main class="library-page library-form-page library-checkout-page">
    <div class="library-page-head">
        <div>
            <p class="portal-eyebrow">BIBLIOTHEEK / UITLEENBEHEER</p>
            <h1>Boeken uitlenen</h1>
            <p>Identificeer de leerling, scan één of meer boeken en bevestig de uitlening.</p>
        </div>
        <a class="library-button library-button-secondary" href="{{ route('bibliotheek.loans.index') }}">Terug naar uitleningen</a>
    </div>
    @if($errors->any())
        <div class="library-errors" role="alert">
            <strong>Uitlenen is niet gelukt. Er is niets geregistreerd.</strong>
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form id="library-checkout-form" action="{{ route('bibliotheek.loans.store') }}" method="post" autocomplete="off">
        @csrf

        <section class="library-checkout-card">
            <div class="library-checkout-section-head">
                <span class="library-checkout-step">1</span>
                <div><h2>Leerling identificeren</h2><p>Scan de Smartschool-kaart of zoek een leerling op naam of gebruikersnaam.</p></div>
            </div>
            <div class="library-checkout-section-body">
                <div class="library-checkout-grid">
                    <div class="library-field">
                        <label for="student-card-scan">Smartschool-kaart / scanwaarde</label>
                        <div class="library-checkout-inline">
                            <input id="student-card-scan" type="text" maxlength="200" placeholder="Scan de kaart of typ de gebruikersnaam">
                            <button id="student-scan-button" class="library-button library-button-secondary" type="button">Herkennen</button>
                        </div>
                        <p class="library-help">De QR-code wordt bij de Smartschool-leerlingensync lokaal opgeslagen. Bij het scannen is geen webserviceoproep nodig.</p>
                    </div>
                    <div class="library-field">
                        <label for="student-name-search">Leerling zoeken</label>
                        <input id="student-name-search" type="search" placeholder="Naam of Smartschool-gebruikersnaam" autocomplete="off">
                        <div id="student-search-results" class="library-checkout-results" role="status"></div>
                    </div>
                </div>

                <div id="student-feedback" class="library-checkout-feedback" aria-live="polite" hidden></div>
                <div id="student-selected" class="library-checkout-selected" hidden>
                    <span class="library-checkout-avatar" aria-hidden="true">✓</span>
                    <div><strong id="student-selected-name"></strong><small id="student-selected-username"></small></div>
                    <button type="button" id="student-clear" class="library-button library-button-secondary">Wijzigen</button>
                </div>

                <input type="hidden" id="selected-student-username" name="leerling" value="{{ old('leerling', $username) }}">
            </div>
        </section>

        <section class="library-checkout-card">
            <div class="library-checkout-section-head">
                <span class="library-checkout-step">2</span>
                <div><h2>Boeken toevoegen</h2><p>Scan de exemplaren na elkaar. Elk boek verschijnt in de lijst hieronder.</p></div>
                <span class="library-checkout-counter"><strong id="checkout-book-count">0</strong> boek(en)</span>
            </div>
            <div class="library-checkout-section-body">
                <div class="library-field">
                    <label for="checkout-barcode">Barcode van het boek</label>
                    <div class="library-checkout-inline">
                        <input id="checkout-barcode" type="text" maxlength="60" placeholder="Scan of typ een exemplaarbarcode" value="{{ old('barcode', $barcode) }}">
                        <button type="button" id="checkout-add-book" class="library-button library-button-secondary">+ Boek toevoegen</button>
                    </div>
                    <p class="library-help">Druk na het scannen op Enter of klik op ‘Boek toevoegen’. Dubbele exemplaren worden geweigerd.</p>
                </div>
                <div id="book-feedback" class="library-checkout-feedback" aria-live="polite" hidden></div>
                <div id="checkout-book-fields"></div>
                <div class="library-checkout-table-wrap">
                    <table class="library-checkout-table">
                        <thead><tr><th>Boek</th><th>Exemplaarbarcode</th><th></th></tr></thead>
                        <tbody id="checkout-books">
                            <tr id="checkout-books-empty"><td colspan="3">Er zijn nog geen boeken toegevoegd.</td></tr>
                        </tbody>
                    </table>
                </div>
                <p id="checkout-limit" class="library-checkout-caption">Maximaal {{ $maxLoans }} openstaande uitleningen per leerling, inclusief eerder uitgeleende boeken.</p>
            </div>
        </section>

        <section class="library-checkout-card">
            <div class="library-checkout-section-head">
                <span class="library-checkout-step">3</span>
                <div><h2>Uitleentermijn</h2><p>Dezelfde termijn wordt toegepast op alle boeken in deze uitleenactie.</p></div>
            </div>
            <div class="library-checkout-section-body">
                <div class="library-checkout-term-row">
                    <div class="library-field">
                        <label for="loan-days">Aantal uitleendagen</label>
                        <input id="loan-days" name="loan_days" type="number" min="1" max="365" required value="{{ old('loan_days', $loanDays) }}">
                    </div>
                    <div class="library-checkout-due">
                        <span>Uiterste inleverdatum</span>
                        <strong id="checkout-due-date">—</strong>
                    </div>
                </div>
                <p class="library-help">Standaard ingesteld op {{ $loanDays }} dagen. Je kunt de termijn voor deze uitlening aanpassen. Smartschool-herinneringen volgen de globale bibliotheekinstellingen.</p>
            </div>
        </section>

        <div class="library-checkout-actions">
            <span>Controleer de leerling en de gekozen exemplaren voor je registreert.</span>
            <button type="submit" id="checkout-submit" class="library-button library-button-primary" disabled>Uitlening registreren</button>
        </div>
    </form>
</main>
@endsection

@push('scripts')
<script>
(() => {
    const studentSearch = document.getElementById('student-name-search');
    const studentList = document.getElementById('student-search-results');
    const studentUsername = document.getElementById('selected-student-username');
    const studentSelected = document.getElementById('student-selected');
    const studentName = document.getElementById('student-selected-name');
    const studentLabel = document.getElementById('student-selected-username');
    const studentScan = document.getElementById('student-card-scan');
    const studentFeedback = document.getElementById('student-feedback');
    const bookInput = document.getElementById('checkout-barcode');
    const bookFeedback = document.getElementById('book-feedback');
    const bookTable = document.getElementById('checkout-books');
    const bookFields = document.getElementById('checkout-book-fields');
    const bookCount = document.getElementById('checkout-book-count');
    const submit = document.getElementById('checkout-submit');
    const term = document.getElementById('loan-days');
    const due = document.getElementById('checkout-due-date');
    const maxLoans = @json($maxLoans);
    const endpoints = {
        students: @json(route('bibliotheek.loans.students')),
        scan: @json(route('bibliotheek.loans.student-scan')),
        copy: @json(route('bibliotheek.loans.copy-scan'))
    };
    const initialStudent = @json($leerling);
    const initialCopy = @json($copy);
    const oldBarcodes = @json(old('barcodes', []));
    let books = [];
    let learner = null;
    let studentLookupSeq = 0;
    let bookBusy = false;

    function feedback(element, message, isError = false) {
        element.hidden = !message;
        element.textContent = message || '';
        element.classList.toggle('is-error', isError);
    }
    function updateSubmit() {
        submit.disabled = !(studentUsername.value && books.length && Number(term.value) >= 1 && Number(term.value) <= 365);
    }
    function selectStudent(person) {
        learner = person;
        studentUsername.value = person.gebruikersnaam;
        studentName.textContent = [person.voornaam, person.naam].filter(Boolean).join(' ');
        studentLabel.textContent = person.gebruikersnaam;
        studentSelected.hidden = false;
        studentList.replaceChildren();
        studentSearch.value = '';
        feedback(studentFeedback, '');
        updateSubmit();
    }
    function clearStudent() {
        learner = null;
        studentUsername.value = '';
        studentSelected.hidden = true;
        studentScan.value = '';
        updateSubmit();
        studentSearch.focus();
    }
    async function lookupStudent() {
        const code = studentScan.value.trim();
        if (!code) return;
        feedback(studentFeedback, 'Kaart controleren...');
        cardPairing.hidden = true;
        unlinkedScan = '';
        try {
            const response = await fetch(endpoints.scan + '?code=' + encodeURIComponent(code), {
                headers: {Accept: 'application/json'}
            });
            const data = await response.json();
            if (!response.ok) {
                feedback(studentFeedback, data.message || 'Leerling niet gevonden.', true);
                studentSearch.focus();
                return;
            }
            selectStudent(data.student);
            studentScan.value = '';
            bookInput.focus();
        } catch {
            feedback(studentFeedback, 'De kaart kan momenteel niet gecontroleerd worden.', true);
        }
    }

    studentSearch.addEventListener('input', async () => {
        const q = studentSearch.value.trim();
        const seq = ++studentLookupSeq;
        studentList.replaceChildren();
        if (q.length < 2) return;
        try {
            const r = await fetch(endpoints.students + '?q=' + encodeURIComponent(q), {
                headers: {Accept: 'application/json'}
            });
            if (!r.ok) return;
            const people = await r.json();
            if (seq !== studentLookupSeq) return;
            if (!people.length) {
                const empty = document.createElement('p');
                empty.className = 'library-help';
                empty.textContent = 'Geen actieve leerlingen gevonden.';
                studentList.appendChild(empty);
            }
            for (const person of people) {
                const btn = document.createElement('button');
                btn.className = 'library-checkout-result';
                btn.type = 'button';
                btn.textContent = [person.voornaam, person.naam].filter(Boolean).join(' ') + ' · ' + person.gebruikersnaam;
                btn.addEventListener('click', () => {
                    selectStudent(person);
                    bookInput.focus();
                });
                studentList.appendChild(btn);
            }
        } catch { feedback(studentFeedback, 'Leerlingen zoeken is momenteel niet beschikbaar.', true); }
    });
    studentScan.addEventListener('keydown', e => {
        if (e.key === 'Enter') { e.preventDefault(); lookupStudent(); }
    });
    document.getElementById('student-scan-button').addEventListener('click', lookupStudent);
    document.getElementById('student-clear').addEventListener('click', clearStudent);
    function renderBooks() {
        bookFields.replaceChildren();
        bookTable.replaceChildren();
        if (!books.length) {
            const tr = document.createElement('tr');
            const td = document.createElement('td');
            td.colSpan = 3;
            td.textContent = 'Er zijn nog geen boeken toegevoegd.';
            tr.appendChild(td);
            bookTable.appendChild(tr);
        }
        books.forEach((copy, index) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'barcodes[]';
            input.value = copy.barcode;
            bookFields.appendChild(input);
            const row = document.createElement('tr');
            const title = document.createElement('td');
            title.textContent = copy.title;
            const barcode = document.createElement('td');
            barcode.textContent = copy.barcode;
            const action = document.createElement('td');
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'library-checkout-remove';
            remove.textContent = 'Verwijderen';
            remove.setAttribute('aria-label', 'Verwijder '+copy.title+' uit selectie');
            remove.addEventListener('click', () => { books.splice(index, 1); renderBooks(); });
            action.appendChild(remove);
            row.append(title, barcode, action);
            bookTable.appendChild(row);
        });
        bookCount.textContent = books.length;
        updateSubmit();
    }
    async function addBook(code, quiet = false) {
        const barcode = String(code || '').trim();
        if (!barcode || bookBusy) return;
        if (books.some(item => item.barcode === barcode)) {
            feedback(bookFeedback, 'Dit exemplaar staat al in de lijst.', true);
            return;
        }
        if (books.length >= maxLoans) {
            feedback(bookFeedback, 'Je kunt in één keer maximaal '+maxLoans+' exemplaren toevoegen.', true);
            return;
        }
        bookBusy = true;
        feedback(bookFeedback, quiet ? '' : 'Exemplaar controleren...');
        try {
            const response = await fetch(endpoints.copy + '?barcode=' + encodeURIComponent(barcode), {
                headers: {Accept: 'application/json'}
            });
            const data = await response.json();
            if (!response.ok) {
                feedback(bookFeedback, data.message || 'Exemplaar niet gevonden.', true);
                return;
            }
            books.push(data.copy);
            renderBooks();
            bookInput.value = '';
            feedback(bookFeedback, '');
            bookInput.focus();
        } catch {
            feedback(bookFeedback, 'Kan deze barcode momenteel niet controleren.', true);
        } finally { bookBusy = false; }
    }
    document.getElementById('checkout-add-book').addEventListener('click', () => addBook(bookInput.value));
    bookInput.addEventListener('keydown', e => {
        if (e.key === 'Enter') { e.preventDefault(); addBook(bookInput.value); }
    });

    function updateDate() {
        const days = Number(term.value);
        if (!Number.isInteger(days) || days < 1 || days > 365) {
            due.textContent = '—';
        } else {
            const now = new Date();
            const dateParts = new Intl.DateTimeFormat('en-GB', {
                timeZone: 'Europe/Brussels', year: 'numeric', month: '2-digit', day: '2-digit'
            }).formatToParts(now);
            const value = type => Number(dateParts.find(part => part.type === type).value);
            const date = new Date(Date.UTC(value('year'), value('month') - 1, value('day') + days));
            due.textContent = new Intl.DateTimeFormat('nl-BE', {
                timeZone: 'UTC', day: 'numeric', month: 'long', year: 'numeric'
            }).format(date);
        }
        updateSubmit();
    }
    term.addEventListener('input', updateDate);
    document.getElementById('library-checkout-form').addEventListener('submit', e => {
        if (!studentUsername.value || !books.length) e.preventDefault();
        else submit.disabled = true;
    });
    if (initialStudent) selectStudent(initialStudent);
    renderBooks();
    updateDate();
    (async () => {
        const initialBarcodes = Array.isArray(oldBarcodes) && oldBarcodes.length
            ? oldBarcodes : (initialCopy && initialCopy.status === 'beschikbaar' ? [initialCopy.barcode] : []);
        for (const code of initialBarcodes) await addBook(code, true);
    })();
})();
</script>
@endpush
