@extends('layouts.app')

@section('title', 'Boek toevoegen')

@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Dashboard', 'url' => route('home')],
        ['label' => 'Bibliotheek', 'url' => route('bibliotheek.index')],
        ['label' => 'Boekencatalogus', 'url' => route('bibliotheek.books.index')],
        ['label' => 'Boek toevoegen'],
    ]])
@endsection

@section('content')
<main class="library-page library-form-page" aria-labelledby="add-book-title">
    <div class="library-page-head">
        <div>
            <p class="portal-eyebrow">BIBLIOTHEEK / BOEKENCATALOGUS</p>
            <h1 id="add-book-title">Nieuw boek registreren</h1>
            <p>Zoek een boek via ISBN, titel of auteur. Controleer de gegevens vóór het opslaan.</p>
        </div>
        <a class="library-button library-button-secondary" href="{{ route('bibliotheek.books.index') }}">Terug naar catalogus</a>
    </div>
    @if(session('success'))
        <div class="library-message" role="status">{{ session('success') }} · {{ session('last_barcode') }}</div>
    @endif
    <section class="library-form-card portal-today-summary" aria-labelledby="today-summary-title">
        <h2 id="today-summary-title" class="portal-today-summary-title">Vandaag geregistreerd</h2>
        <div class="portal-today-summary-grid">
            <div class="portal-today-summary-stat"><strong>{{ $todayCopies }}</strong><span>{{ $todayCopies == 1 ? 'Exemplaar' : 'Exemplaren' }}</span></div>
            <div class="portal-today-summary-stat"><strong>{{ $todayBooks }}</strong><span>{{ $todayBooks == 1 ? 'Nieuwe titel' : 'Nieuwe titels' }}</span></div>
            <div class="portal-today-summary-stat"><strong>{{ $todayUnprinted }}</strong><span>{{ $todayUnprinted == 1 ? 'Etiket nog niet bevestigd' : 'Etiketten nog niet bevestigd' }}</span></div>
        </div>
        <p class="portal-today-summary-help">Scan het ISBN, controleer de gegevens en kies <strong>Registreren en volgend boek</strong>. Leg onbekende boeken indien nodig apart.</p>
    </section>
    @if($errors->any())
        <div class="library-errors" role="alert"><strong>Controleer de invoer:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <form method="post" action="{{ route('bibliotheek.books.store') }}" class="library-form" id="library-new-book-form">
        @csrf
        <section class="library-form-card">
            <h2>1. Boekgegevens</h2>
            <div class="library-isbn-row">
                <div class="library-field">
                    <label for="isbn">ISBN-10 of ISBN-13</label>
                    <input id="isbn" name="isbn" type="text" maxlength="20" value="{{ old('isbn') }}" placeholder="Scan of typ ISBN" autocomplete="off" autofocus>
                </div>
                <button type="button" class="library-button library-button-secondary" id="library-lookup">Gegevens ophalen</button>
            </div>
            <p class="library-help" id="library-lookup-result" role="status" aria-live="polite">We zoeken eerst in Open Library en daarna in Google Books. Zonder resultaat kun je het boek handmatig toevoegen.</p>
            <div id="library-other-sources" class="library-help" hidden>
                Niet automatisch gevonden? Zoek zelf bij
                <a id="library-source-google" href="https://www.google.com/" target="_blank" rel="noopener noreferrer">Google</a>,
                <a id="library-source-bibliotheek" href="https://www.bibliotheek.be/" target="_blank" rel="noopener noreferrer">Bibliotheek.be</a>
                of <a id="library-source-isbnsearch" href="https://isbnsearch.org/" target="_blank" rel="noopener noreferrer">ISBNsearch</a>.
                Controleer altijd de exacte editie en vul de gevonden gegevens hieronder handmatig in.
            </div>
            <div class="library-lookup-diagnostics">
                <button type="button" class="library-button library-button-secondary" id="library-test-providers">Controleer opzoekdiensten</button>
                <p class="library-help" id="library-provider-feedback" role="status" aria-live="polite">Geeft aan of de live server verbinding kan maken met de opzoekdiensten. Er worden geen leerlinggegevens verzonden.</p>
            </div>
            <div class="library-name-lookup">
                <h3>Geen ISBN bij de hand? Zoek op naam</h3>
                <div class="library-name-search-row">
                    <div class="library-field">
                        <label for="library-search-type">Zoeken op</label>
                        <select id="library-search-type">
                            <option value="title">Boektitel</option>
                            <option value="author">Auteur</option>
                        </select>
                    </div>
                    <div class="library-field">
                        <label for="library-search-source">Bron</label>
                        <select id="library-search-source">
                            <option value="auto">Automatisch</option>
                            <option value="openlibrary">Open Library</option>
                            <option value="google">Google Books</option>
                        </select>
                    </div>
                    <div class="library-field library-name-search-input">
                        <label for="library-name-query">Zoekterm</label>
                        <input id="library-name-query" type="search" maxlength="120" placeholder="Bijv. De brief voor de koning" autocomplete="off">
                    </div>
                    <button type="button" class="library-button library-button-secondary" id="library-search-name">Zoeken</button>
                </div>
                <p class="library-help" id="library-name-feedback" role="status" aria-live="polite">Kies een boek om de beschikbare edities te bekijken en selecteer daarna de uitgave die je in handen hebt.</p>
                <div id="library-name-results" class="library-name-results" hidden></div>
                <section class="library-edition-panel" id="library-edition-panel" hidden aria-label="Kies een editie">
                    <div class="library-edition-head"><div><h4 id="library-edition-title">Kies de juiste editie</h4><p class="library-help" id="library-edition-feedback" role="status" aria-live="polite"></p></div><button type="button" class="library-button library-button-secondary" id="library-edition-back">Terug naar zoekresultaten</button></div>
                    <div class="library-field library-edition-filter"><label for="library-edition-language">Taal van de uitgave</label><select id="library-edition-language"><option value="all">Alle talen</option><option value="nld">Nederlands</option><option value="eng">Engels</option><option value="fra">Frans</option><option value="deu">Duits</option><option value="other">Andere / onbekend</option></select></div>
                    <div class="library-edition-results" id="library-edition-results"></div>
                    <div class="library-edition-navigation"><button type="button" class="library-button library-button-secondary" id="library-edition-prev">Vorige edities</button><span id="library-edition-page"></span><button type="button" class="library-button library-button-secondary" id="library-edition-next">Volgende edities</button></div>
                </section>
                <p class="library-help">Selecteer de juiste editie op basis van taal, omslag, uitgever en ISBN. Controleer altijd met het fysieke boek.</p>
                <p class="library-help">Bronnen: Open Library en, wanneer nodig, Google Books. Meta4books kan later worden aangesloten zodra de school API-toegang heeft.</p>
            </div>

            <div class="library-form-grid">
                <div class="library-field library-wide"><label for="title">Titel <span aria-hidden="true">*</span></label><input id="title" name="title" value="{{ old('title') }}" maxlength="255"></div>
                <div class="library-field"><label for="subtitle">Ondertitel</label><input id="subtitle" name="subtitle" value="{{ old('subtitle') }}" maxlength="255"></div>
                <div class="library-field"><label for="authors">Auteur(s)</label><input id="authors" name="authors" value="{{ old('authors') }}" maxlength="255"></div>
                <div class="library-field"><label for="publisher">Uitgever</label><input id="publisher" name="publisher" value="{{ old('publisher') }}" maxlength="255"></div>
                <div class="library-field"><label for="published_year">Uitgavejaar / datum</label><input id="published_year" name="published_year" value="{{ old('published_year') }}" maxlength="20"></div>
                <div class="library-field"><label for="category">Categorie / genre</label><input id="category" name="category" value="{{ old('category') }}" maxlength="255"></div>
                <div class="library-field library-wide"><label for="description">Omschrijving</label><textarea id="description" name="description" rows="3" maxlength="5000">{{ old('description') }}</textarea></div>
                <input type="hidden" id="cover_url" name="cover_url" value="{{ old('cover_url') }}">
            </div>
            <div class="library-cover-preview" id="library-cover-preview" hidden><img id="library-cover-image" alt="Opgehaalde boekomslag" referrerpolicy="no-referrer"><button type="button" class="library-button library-button-secondary" id="library-clear-cover">Verkeerde omslag? Verwijderen</button></div>
            <p class="library-help">Controleer de omslag met jouw fysieke boek. Bij zoeken op naam wordt geen willekeurige editie-omslag opgeslagen: controleer het ISBN en klik daarna op Gegevens ophalen.</p>
        </section>

        <section class="library-form-card">
            <h2>2. Fysiek exemplaar</h2>
            <p>Elk exemplaar heeft een eigen barcode, ook wanneer meerdere boeken hetzelfde ISBN hebben.</p>
            <div class="library-form-grid">
                <div class="library-field"><label for="barcode">Unieke barcode / inventarisnummer <span aria-hidden="true">*</span></label><input id="barcode" name="barcode" value="{{ old('barcode', $suggestedBarcode) }}" maxlength="60" required></div>
                <p class="library-help library-wide">Een unieke barcode is automatisch ingevuld. Je mag die indien nodig aanpassen.</p>
                <div class="library-field"><label for="shelf_location">Rek / locatie</label><input id="shelf_location" name="shelf_location" value="{{ old('shelf_location') }}" maxlength="120" placeholder="Bijv. A3"></div>
                <div class="library-field"><label for="condition">Staat</label><select id="condition" name="condition"><option value="nieuw" @selected(old('condition') === 'nieuw')>Nieuw</option><option value="goed" @selected(old('condition', 'goed') === 'goed')>Goed</option><option value="gebruikt" @selected(old('condition') === 'gebruikt')>Gebruikt</option><option value="beschadigd" @selected(old('condition') === 'beschadigd')>Beschadigd</option></select></div>
                <div class="library-field library-wide"><label for="notes">Opmerkingen over dit exemplaar</label><textarea id="notes" name="notes" rows="2" maxlength="2000">{{ old('notes') }}</textarea></div>
            </div>
        </section>
        <div class="library-form-actions">
                <button type="submit" class="library-button library-button-primary">Boek / exemplaar registreren</button>
                <button type="submit" name="next_book" value="1" class="library-button library-button-secondary">Registreren en volgend boek</button>
<a class="library-button library-button-secondary" href="{{ route('bibliotheek.books.index') }}">Annuleren</a></div>
    </form>
</main>
@endsection

@push('scripts')
<script>
(() => {
    const button = document.getElementById('library-lookup');
    if (!button) return;
    const isbnInput = document.getElementById('isbn');
    const testProvidersButton = document.getElementById('library-test-providers');
    const testProvidersFeedback = document.getElementById('library-provider-feedback');
    testProvidersButton?.addEventListener('click', async () => {
        testProvidersButton.disabled = true;
        testProvidersFeedback.textContent = 'Verbinding vanaf de server controleren…';
        try {
            const response = await fetch(@json(route('bibliotheek.books.lookup-diagnostics')), {
                headers: {Accept: 'application/json'},
            });
            const result = await response.json();
            if (!response.ok) throw new Error(result.message || 'Diagnose mislukt');
            testProvidersFeedback.textContent = Object.entries(result.providers || {})
                .map(([name, status]) => name + ': ' + (status.reachable
                    ? 'OK (' + status.status + ')' : status.message + (status.status ? ' (' + status.status + ')' : '')))
                .join(' · ');
        } catch {
            testProvidersFeedback.textContent = 'De controle kon niet worden uitgevoerd. Controleer de serverlogs.';
        } finally {
            testProvidersButton.disabled = false;
        }
    });
    const message = document.getElementById('library-lookup-result');
    const otherSources = document.getElementById('library-other-sources');
    const updateOtherSources = isbn => {
        const number = isbn.replace(/[^0-9X]/gi, '');
        document.getElementById('library-source-google').href = 'https://www.google.com/search?q=' + encodeURIComponent('ISBN ' + number);
        document.getElementById('library-source-bibliotheek').href = 'https://www.bibliotheek.be/catalogus?zoekterm=' + encodeURIComponent(number);
        document.getElementById('library-source-isbnsearch').href = 'https://isbnsearch.org/isbn/' + encodeURIComponent(number);
    };

    const coverPreview = document.getElementById('library-cover-preview');
    const coverImage = document.getElementById('library-cover-image');
    const clearCoverButton = document.getElementById('library-clear-cover');
    const clearCover = () => {
        document.getElementById('cover_url').value = '';
        coverImage.removeAttribute('src');
        coverPreview.hidden = true;
    };
    clearCoverButton.addEventListener('click', clearCover);
    coverImage.addEventListener('error', clearCover);
    const nameButton = document.getElementById('library-search-name');
    const nameQuery = document.getElementById('library-name-query');
    const nameType = document.getElementById('library-search-type');
    const nameSource = document.getElementById('library-search-source');
    const nameFeedback = document.getElementById('library-name-feedback');
    const nameResults = document.getElementById('library-name-results');
    const editionPanel = document.getElementById('library-edition-panel');
    const editionResults = document.getElementById('library-edition-results');
    const editionFeedback = document.getElementById('library-edition-feedback');
    const editionTitle = document.getElementById('library-edition-title');
    const editionFilter = document.getElementById('library-edition-language');
    const editionPrev = document.getElementById('library-edition-prev');
    const editionNext = document.getElementById('library-edition-next');
    const editionPage = document.getElementById('library-edition-page');
    let selectedWork = null;
    let editionData = null;
    let editionRequest = 0;

    const applyBook = (book, fromName = false) => {
        ['isbn', 'title', 'subtitle', 'authors', 'publisher', 'published_year', 'category', 'description', 'cover_url'].forEach(key => {
            const input = document.getElementById(key);
            if (!input) return;
            input.value = fromName && key === 'cover_url' ? '' : (book[key] || '');
        });
        if (book.cover_url && !fromName) {
            coverImage.src = book.cover_url;
            coverPreview.hidden = false;
        } else {
            clearCover();
        }
    };

    const searchByName = async () => {
        const q = nameQuery.value.trim();
        nameResults.replaceChildren();
        nameResults.hidden = true;
        editionPanel.hidden = true;
        editionRequest++;
        if (q.length < 2) {
            nameFeedback.textContent = 'Geef minstens 2 tekens in om te zoeken.';
            nameQuery.focus();
            return;
        }
        nameButton.disabled = true;
        nameFeedback.textContent = 'Boeken zoeken…';
        try {
            const url = new URL(@json(route('bibliotheek.books.search-external')), window.location.origin);
            url.searchParams.set('q', q);
            url.searchParams.set('type', nameType.value);
            url.searchParams.set('source', nameSource.value);
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            const data = await response.json();
            if (!response.ok) {
                nameFeedback.textContent = data.message || 'Zoeken is niet gelukt.';
                return;
            }
            const books = data.books || [];
            if (!books.length) {
                nameFeedback.textContent = 'Geen boeken gevonden. Je kunt de gegevens handmatig invullen.';
                return;
            }
            nameFeedback.textContent = books.length + ' resultaten gevonden via ' + (data.source || 'de externe bron')
                + '. Kies het boek dat overeenkomt met jouw exemplaar.';
            nameResults.hidden = false;
            books.forEach(book => {
                const option = document.createElement('button');
                option.type = 'button';
                option.className = 'library-name-result';

                const cover = document.createElement('span');
                cover.className = 'library-name-result-cover';
                // Show the work-level thumbnail to help identify the book.
                // Do not save this image as the selected edition's cover.
                if (book.cover_url) {
                    const image = document.createElement('img');
                    image.src = book.cover_url;
                    image.alt = '';
                    image.loading = 'lazy';
                    image.referrerPolicy = 'no-referrer';
                    image.addEventListener('error', () => {
                        image.remove();
                        cover.textContent = '▤';
                    });
                    cover.append(image);
                } else {
                    cover.textContent = '▤';
                }

                const info = document.createElement('span');
                info.className = 'library-name-result-info';
                const title = document.createElement('strong');
                title.textContent = book.title || 'Zonder titel';
                const author = document.createElement('small');
                author.textContent = [book.authors, book.published_year, book.edition_count ? book.edition_count + ' uitgaven' : 'Edities bekijken'].filter(Boolean).join(' · ');
                info.append(title, author);

                const choose = document.createElement('span');
                choose.textContent = book.work_key ? 'Edities bekijken →' : 'Boek kiezen →';
                choose.className = 'library-name-result-select';
                option.append(cover, info, choose);
                option.addEventListener('click', () => openEditions(book));
                nameResults.append(option);
            });
        } catch {
            nameFeedback.textContent = 'De zoekdienst is tijdelijk niet bereikbaar. Vul de gegevens handmatig in.';
        } finally {
            nameButton.disabled = false;
        }
    };

    const languages = {nld: 'Nederlands', dut: 'Nederlands', eng: 'Engels', fra: 'Frans', fre: 'Frans', deu: 'Duits', ger: 'Duits', spa: 'Spaans', ita: 'Italiaans'};
    const isDutch = edition => (edition.languages || []).some(lang => ['nld', 'dut'].includes(lang));
    const languageLabel = edition => (edition.languages || []).length
        ? edition.languages.map(code => languages[code] || code.toUpperCase()).join(', ')
        : 'Taal onbekend';
    const matchesLanguage = edition => {
        const choice = editionFilter.value;
        if (choice === 'all') return true;
        const codes = edition.languages || [];
        if (choice === 'other') return !codes.length || !codes.some(code => ['nld','dut','eng','fra','fre','deu','ger'].includes(code));
        const aliases = {nld: ['nld','dut'], fra: ['fra','fre'], deu: ['deu','ger'], eng: ['eng']};
        return codes.some(code => aliases[choice]?.includes(code));
    };
    const editionMeta = edition => [languageLabel(edition), edition.published_year, edition.publisher, edition.isbn ? 'ISBN: ' + edition.isbn : 'ISBN niet opgegeven'].filter(Boolean).join(' · ');

    const renderEditions = () => {
        editionResults.replaceChildren();
        if (!editionData) return;
        const sorted = [...editionData.editions].sort((a, b) => Number(isDutch(b)) - Number(isDutch(a)));
        const visible = sorted.filter(matchesLanguage);
        const count = editionData.total || editionData.editions.length;
        editionPage.textContent = 'Uitgaven ' + (editionData.offset + 1) + '–' + Math.min(editionData.offset + editionData.limit, count) + ' van ' + count;
        editionPrev.disabled = editionData.offset === 0;
        editionNext.disabled = editionData.offset + editionData.limit >= count;
        editionFeedback.textContent = visible.length
            ? visible.length + ' edities op deze pagina. Nederlandstalige uitgaven staan bovenaan.'
            : 'Geen editie in deze taal op deze pagina. Kies een andere taal of ga naar de volgende pagina.';
        if (!visible.length) {
            const empty = document.createElement('p');
            empty.className = 'library-help';
            empty.textContent = 'Geen passende edities op deze pagina.';
            editionResults.append(empty);
        }
        visible.forEach(edition => {
            const row = document.createElement('div');
            row.className = 'library-edition-item';
            const cover = document.createElement('span');
            cover.className = 'library-edition-cover';
            if (edition.cover_url) {
                const img = document.createElement('img');
                img.src = edition.cover_url;
                img.loading = 'lazy';
                img.alt = '';
                img.referrerPolicy = 'no-referrer';
                img.addEventListener('error', () => { img.remove(); cover.textContent = '▤'; });
                cover.append(img);
            } else {
                cover.textContent = '▤';
            }
            const info = document.createElement('div');
            info.className = 'library-edition-info';
            const title = document.createElement('strong');
            title.textContent = edition.title;
            const subtitle = document.createElement('small');
            subtitle.textContent = editionMeta(edition);
            info.append(title, subtitle);
            const choose = document.createElement('button');
            choose.type = 'button';
            choose.className = 'library-button library-button-secondary';
            choose.textContent = 'Deze editie kiezen';
            choose.addEventListener('click', () => selectEdition(edition));
            row.append(cover, info, choose);
            editionResults.append(row);
        });
    };

    const loadEditions = async (offset = 0) => {
        if (!selectedWork?.work_key) return;
        const current = ++editionRequest;
        editionResults.replaceChildren();
        editionFeedback.textContent = 'Edities laden…';
        editionPrev.disabled = true;
        editionNext.disabled = true;
        try {
            const url = new URL(@json(route('bibliotheek.books.editions')), window.location.origin);
            url.searchParams.set('key', selectedWork.work_key);
            url.searchParams.set('offset', String(offset));
            const response = await fetch(url, {headers: {Accept: 'application/json'}});
            const result = await response.json();
            if (current !== editionRequest) return;
            if (!response.ok || result.error) throw new Error('Edities konden niet geladen worden.');
            editionData = result;
            renderEditions();
        } catch {
            if (current !== editionRequest) return;
            editionFeedback.textContent = 'De edities konden niet opgehaald worden. Probeer het opnieuw of vul de gegevens handmatig in.';
        }
    };

    const openEditions = book => {
        if (!book.work_key) {
            applyBook(book, true);
            nameFeedback.textContent = 'Geen editiegegevens beschikbaar. Controleer het ISBN en de informatie zelf.';
            nameResults.hidden = true;
            return;
        }
        selectedWork = book;
        editionFilter.value = 'all';
        editionTitle.textContent = 'Edities van ' + book.title;
        nameResults.hidden = true;
        editionPanel.hidden = false;
        loadEditions(0);
    };

    const selectEdition = async edition => {
        const chosenWork = selectedWork;
        const editionValues = {
            ...chosenWork,
            ...edition,
            authors: chosenWork.authors,
            category: chosenWork.category,
            description: edition.description || '',
        };
        applyBook(editionValues);
        editionPanel.hidden = true;
        nameFeedback.textContent = 'Editie geselecteerd: ' + editionMeta(edition) + '. Controleer het ISBN en de omslag.';
        document.getElementById('title').focus();
        if (chosenWork?.work_key && !edition.description) {
            try {
                const url = new URL(@json(route('bibliotheek.books.work-details')), window.location.origin);
                url.searchParams.set('key', chosenWork.work_key);
                const response = await fetch(url, {headers: {Accept: 'application/json'}});
                if (response.ok) {
                    const details = await response.json();
                    if (document.getElementById('title').value === edition.title) {
                        if (details.description && !document.getElementById('description').value) document.getElementById('description').value = details.description;
                        if (details.category && !document.getElementById('category').value) document.getElementById('category').value = details.category;
                        if (details.subtitle && !document.getElementById('subtitle').value) document.getElementById('subtitle').value = details.subtitle;
                    }
                }
            } catch { /* Work description is optional. */ }
        }
    };
    editionFilter.addEventListener('change', renderEditions);
    editionPrev.addEventListener('click', () => loadEditions(Math.max(0, (editionData?.offset || 0) - (editionData?.limit || 100))));
    editionNext.addEventListener('click', () => loadEditions((editionData?.offset || 0) + (editionData?.limit || 100)));
    document.getElementById('library-edition-back').addEventListener('click', () => {
        editionRequest++;
        editionPanel.hidden = true;
        nameResults.hidden = false;
    });

    nameButton.addEventListener('click', searchByName);
    nameQuery.addEventListener('keydown', event => {
        if (event.key === 'Enter') {
            event.preventDefault();
            searchByName();
        }
    });

    const form = document.getElementById('library-new-book-form');
    let lookingUp = false;
    let submitting = false;
    isbnInput.addEventListener('keydown', event => {
        if (event.key === 'Enter') {
            event.preventDefault();
            if (!lookingUp) button.click();
        }
    });
    form.addEventListener('submit', event => {
        if (lookingUp || submitting) { event.preventDefault(); return; }
        submitting = true;
    });
    button.addEventListener('click', async () => {
        if (lookingUp) return;
        const isbn = isbnInput.value.trim();
        if (!isbn) { message.textContent = 'Vul eerst een ISBN in.'; isbnInput.focus(); return; }
        lookingUp = true;
        button.disabled = true;
        otherSources.hidden = true;
        message.textContent = 'Boekgegevens ophalen…';
        try {
            const response = await fetch(@json(route('bibliotheek.books.lookup')) + '?isbn=' + encodeURIComponent(isbn), { headers: { Accept: 'application/json' } });
            const result = await response.json();
            if (!response.ok) { message.textContent = result.message || 'Geen gegevens gevonden.'; if (response.status !== 422) { updateOtherSources(isbn); otherSources.hidden = false; } return; }
            if (isbnInput.value.trim() !== isbn) { message.textContent = 'ISBN gewijzigd. Scan opnieuw.'; return; }
            const book = result.book;
            applyBook(book);
            message.textContent = result.existing_book
                ? 'Dit ISBN bestaat al in de bibliotheek. Bij registreren wordt een extra exemplaar toegevoegd; de bestaande boekgegevens blijven behouden.'
                : 'Gegevens gevonden via ' + (book.source || 'Open Library') + '. Controleer de velden; de exemplaarbarcode staat al ingevuld.';
        } catch {
            message.textContent = 'De ISBN-dienst is tijdelijk niet bereikbaar. Vul de gegevens handmatig in.';
            updateOtherSources(isbn);
            otherSources.hidden = false;
        } finally {
            lookingUp = false;
            button.disabled = false;
        }
    });
})();
</script>
@endpush
