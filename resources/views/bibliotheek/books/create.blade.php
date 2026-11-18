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
            <p>Scan of typ een ISBN om de gegevens op te halen. Controleer ze vóór het opslaan.</p>
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
            <div class="library-cover-preview" id="library-cover-preview" hidden><img id="library-cover-image" alt="Opgehaalde boekomslag" referrerpolicy="no-referrer"></div>
        </section>

        <section class="library-form-card">
            <h2>2. Eerste fysiek exemplaar</h2>
            <p>Elk exemplaar heeft een eigen barcode, ook wanneer meerdere boeken hetzelfde ISBN hebben.</p>
            <div class="library-form-grid">
                <div class="library-field"><label for="barcode">Unieke barcode / inventarisnummer <span aria-hidden="true">*</span></label><input id="barcode" name="barcode" value="{{ old('barcode') }}" maxlength="60" required placeholder="Bijv. BIB-00001"></div>
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
            ['isbn', 'title', 'subtitle', 'authors', 'publisher', 'published_year', 'cover_url'].forEach(key => {
                const input = document.getElementById(key);
                if (input && book[key] !== null && book[key] !== undefined) input.value = book[key];
            });
            if (book.cover_url) {
                coverImage.src = book.cover_url;
                coverPreview.hidden = false;
            } else {
                coverImage.removeAttribute('src');
                coverPreview.hidden = true;
            }
            message.textContent = 'Gegevens gevonden. Controleer de velden en vul de exemplaarbarcode in.';
        } catch {
            message.textContent = 'De ISBN-dienst is tijdelijk niet bereikbaar. Vul de gegevens handmatig in.';
        } finally {
            button.disabled = false;
        }
    });
})();
</script>
@endpush
