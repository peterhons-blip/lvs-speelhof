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
            <p class="library-help" id="library-lookup-result" role="status" aria-live="polite">Zonder ISBN kun je alle gegevens handmatig invullen.</p>
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
                    <div class="library-field library-name-search-input">
                        <label for="library-name-query">Zoekterm</label>
                        <input id="library-name-query" type="search" maxlength="120" placeholder="Bijv. De brief voor de koning" autocomplete="off">
                    </div>
                    <button type="button" class="library-button library-button-secondary" id="library-search-name">Zoeken</button>
                </div>
                <p class="library-help" id="library-name-feedback" role="status" aria-live="polite">Kies een gevonden boek om de invoervelden automatisch in te vullen.</p>
                <div id="library-name-results" class="library-name-results" hidden></div>
                <p class="library-help">Open Library kan een ISBN van een andere editie voorstellen. Controleer het voorgestelde ISBN altijd op de achterkant van jouw boek.</p>
            </div>

            <div class="library-form-grid">
                <div class="library-field library-wide"><label for="title">Titel <span aria-hidden="true">*</span></label><input id="title" name="title" value="{{ old('title') }}" required maxlength="255"></div>
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
            <h2>2. Eerste fysiek exemplaar</h2>
            <p>Elk exemplaar heeft een eigen barcode, ook wanneer meerdere boeken hetzelfde ISBN hebben.</p>
            <div class="library-form-grid">
                <div class="library-field"><label for="barcode">Unieke barcode / inventarisnummer <span aria-hidden="true">*</span></label><input id="barcode" name="barcode" value="{{ old('barcode', $suggestedBarcode) }}" maxlength="60" required></div>
                <p class="library-help library-wide">Een unieke barcode is automatisch ingevuld. Je mag die indien nodig aanpassen.</p>
                <div class="library-field"><label for="shelf_location">Rek / locatie</label><input id="shelf_location" name="shelf_location" value="{{ old('shelf_location') }}" maxlength="120" placeholder="Bijv. A3"></div>
                <div class="library-field"><label for="condition">Staat</label><select id="condition" name="condition"><option value="nieuw" @selected(old('condition') === 'nieuw')>Nieuw</option><option value="goed" @selected(old('condition', 'goed') === 'goed')>Goed</option><option value="gebruikt" @selected(old('condition') === 'gebruikt')>Gebruikt</option><option value="beschadigd" @selected(old('condition') === 'beschadigd')>Beschadigd</option></select></div>
                <div class="library-field library-wide"><label for="notes">Opmerkingen over dit exemplaar</label><textarea id="notes" name="notes" rows="2" maxlength="2000">{{ old('notes') }}</textarea></div>
            </div>
        </section>
        <div class="library-form-actions"><button type="submit" class="library-button library-button-primary">Boek registreren</button><a class="library-button library-button-secondary" href="{{ route('bibliotheek.books.index') }}">Annuleren</a></div>
    </form>
</main>
@endsection

@push('scripts')
<script>
(() => {
    const button = document.getElementById('library-lookup');
    if (!button) return;
    const isbnInput = document.getElementById('isbn');
    const message = document.getElementById('library-lookup-result');
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
    const nameFeedback = document.getElementById('library-name-feedback');
    const nameResults = document.getElementById('library-name-results');

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
        if (q.length < 2) {
            nameFeedback.textContent = 'Geef minstens 2 tekens in om te zoeken.';
            nameQuery.focus();
            return;
        }
        nameButton.disabled = true;
        nameFeedback.textContent = 'Zoeken in Open Library…';
        try {
            const url = new URL(@json(route('bibliotheek.books.search-external')), window.location.origin);
            url.searchParams.set('q', q);
            url.searchParams.set('type', nameType.value);
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
            nameFeedback.textContent = books.length + ' resultaten gevonden. Kies het boek dat overeenkomt met jouw exemplaar.';
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
                author.textContent = [book.authors, book.published_year, book.isbn ? 'Voorgesteld ISBN: ' + book.isbn : 'ISBN niet gevonden'].filter(Boolean).join(' · ');
                info.append(title, author);

                const choose = document.createElement('span');
                choose.textContent = 'Kiezen →';
                choose.className = 'library-name-result-select';
                option.append(cover, info, choose);
                option.addEventListener('click', async () => {
                    applyBook(book, true);
                    const selectedTitle = book.title;
                    nameFeedback.textContent = 'Boekgegevens ingevuld. Controleer ISBN en haal de juiste editie-omslag op via Gegevens ophalen. Omschrijving ophalen…';
                    nameResults.hidden = true;
                    document.getElementById('title').focus();

                    if (book.work_key) {
                        try {
                            const url = new URL(@json(route('bibliotheek.books.work-details')), window.location.origin);
                            url.searchParams.set('key', book.work_key);
                            const response = await fetch(url, { headers: { Accept: 'application/json' } });
                            if (response.ok) {
                                const details = await response.json();
                                // Do not overwrite data if another book was selected meanwhile.
                                if (document.getElementById('title').value === selectedTitle) {
                                    if (details.description) document.getElementById('description').value = details.description;
                                    if (details.subtitle) document.getElementById('subtitle').value = details.subtitle;
                                    if (details.category && !document.getElementById('category').value) {
                                        document.getElementById('category').value = details.category;
                                    }
                                }
                            }
                        } catch {
                            // Description is optional; the book can still be added manually.
                        }
                    }
                    nameFeedback.textContent = book.isbn
                        ? 'Controleer ISBN ' + book.isbn + ' op jouw boek en klik op Gegevens ophalen voor de bijbehorende omslag.'
                        : 'Controleer de boekgegevens. Vul indien mogelijk het ISBN in en klik op Gegevens ophalen voor de juiste omslag.';
                });
                nameResults.append(option);
            });
        } catch {
            nameFeedback.textContent = 'De zoekdienst is tijdelijk niet bereikbaar. Vul de gegevens handmatig in.';
        } finally {
            nameButton.disabled = false;
        }
    };
    nameButton.addEventListener('click', searchByName);
    nameQuery.addEventListener('keydown', event => {
        if (event.key === 'Enter') {
            event.preventDefault();
            searchByName();
        }
    });

    button.addEventListener('click', async () => {
        const isbn = isbnInput.value.trim();
        if (!isbn) { message.textContent = 'Vul eerst een ISBN in.'; isbnInput.focus(); return; }
        button.disabled = true;
        message.textContent = 'Boekgegevens ophalen…';
        try {
            const response = await fetch(@json(route('bibliotheek.books.lookup')) + '?isbn=' + encodeURIComponent(isbn), { headers: { Accept: 'application/json' } });
            const result = await response.json();
            if (!response.ok) { message.textContent = result.message || 'Geen gegevens gevonden.'; return; }
            const book = result.book;
            applyBook(book);
            message.textContent = 'Gegevens gevonden. Controleer de velden; de exemplaarbarcode staat al ingevuld.';
        } catch {
            message.textContent = 'De ISBN-dienst is tijdelijk niet bereikbaar. Vul de gegevens handmatig in.';
        } finally {
            button.disabled = false;
        }
    });
})();
</script>
@endpush
