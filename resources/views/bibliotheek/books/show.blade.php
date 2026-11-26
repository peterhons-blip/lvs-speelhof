@extends('layouts.app')

@section('title', $book->title)

@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Dashboard', 'url' => route('home')],
        ['label' => 'Bibliotheek', 'url' => route('bibliotheek.index')],
        ['label' => 'Boekencatalogus', 'url' => route('bibliotheek.books.index')],
        ['label' => $book->title],
    ]])
@endsection

@section('content')
<main class="library-page" aria-labelledby="book-title">
    <div class="library-page-head">
        <div><p class="portal-eyebrow">BIBLIOTHEEK / BOEKENCATALOGUS</p><h1 id="book-title">{{ $book->title }}</h1><p>{{ $book->authors ?: 'Auteur onbekend' }}</p></div>
        <a class="library-button library-button-secondary" href="{{ route('bibliotheek.books.index') }}">Terug naar catalogus</a>
    </div>
    @if(session('success'))<div class="library-message" role="status">
        {{ session('success') }}
        @if(session('new_copy_id'))
            @if(app(\App\Services\LibraryAccess::class)->has(request(),'bibliotheek.qr.view'))
<a class="library-button library-button-primary" style="margin-left:12px" href="{{ route('bibliotheek.books.qr-labels', ['copy' => session('new_copy_id')]) }}">QR-etiket voor nieuw exemplaar maken</a>
@endif
        @endif
    </div>@endif
    @if($errors->any())<div class="library-errors" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="library-form-actions">
        @if(app(\App\Services\LibraryAccess::class)->has(request(),'bibliotheek.qr.view'))
<a class="library-button library-button-secondary" href="{{ route('bibliotheek.books.qr-labels', ['book' => $book->id]) }}">Alle QR-codes van dit boek</a>
@endif
        @if(app(\App\Services\LibraryAccess::class)->has(request(),'bibliotheek.books.edit'))
<a class="library-button library-button-secondary" href="{{ route('bibliotheek.books.edit', $book) }}">Boek bewerken</a>
@endif
        @if($book->copies->every(fn ($copy) => $copy->status === 'beschikbaar'))
            @if(app(\App\Services\LibraryAccess::class)->has(request(),'bibliotheek.books.delete'))
<form method="post" action="{{ route('bibliotheek.books.destroy', $book) }}" onsubmit="return confirm('Dit boek en alle bijbehorende exemplaren definitief verwijderen? Dit kan niet ongedaan gemaakt worden.');">
                @csrf
                @method('DELETE')
                <button class="library-button library-button-secondary" type="submit">Boek verwijderen</button>
            </form>
@endif
        @else
            <span class="library-help">Verwijderen niet mogelijk: een exemplaar is niet beschikbaar of uitgeleend.</span>
        @endif
    </div>
    <section class="library-detail-card">
        <div class="library-detail-cover">
            @if($book->cover_url)<img src="{{ $book->cover_url }}" alt="Omslag van {{ $book->title }}" referrerpolicy="no-referrer">@else <span aria-hidden="true">▤</span>@endif
        </div>
        <div class="library-detail-data">
            @if($book->subtitle)<h2>{{ $book->subtitle }}</h2>@endif
            <dl>
                <div><dt>ISBN</dt><dd>{{ $book->isbn ?: 'Niet ingevuld' }}</dd></div>
                <div><dt>Auteur(s)</dt><dd>{{ $book->authors ?: 'Niet ingevuld' }}</dd></div>
                <div><dt>Uitgever</dt><dd>{{ $book->publisher ?: 'Niet ingevuld' }}</dd></div>
                <div><dt>Uitgave</dt><dd>{{ $book->published_year ?: 'Niet ingevuld' }}</dd></div>
                <div><dt>Categorie</dt><dd>{{ $book->category ?: 'Niet ingevuld' }}</dd></div>
            </dl>
            @if($book->description)<p class="library-description">{{ $book->description }}</p>@endif
        </div>
    </section>
    <section class="library-form-card">
        <h2>Fysieke exemplaren ({{ $book->copies->count() }})</h2>
        <div class="library-table-wrap">
            <table class="library-table">
                <thead><tr><th>Barcode</th><th>Locatie</th><th>Staat</th><th>Status</th><th>QR-etiket</th><th>Opmerking</th><th>Actie</th></tr></thead>
                <tbody>
                    @foreach($book->copies as $copy)
                        <tr><td><strong>{{ $copy->barcode }}</strong></td><td>{{ $copy->shelf_location ?: '—' }}</td><td>{{ ucfirst($copy->condition) }}</td><td><span class="library-pill">{{ ucfirst($copy->status) }}</span></td>
                            <td>{{ $copy->qr_printed_at ? 'Etiket bevestigd' : ($copy->qr_generated_at ? 'QR gemaakt' : 'Nog niet gemaakt') }}</td>
                            <td>{{ $copy->notes ?: '—' }}</td><td>
                            <div class="library-copy-actions">
                                @if($copy->status === 'beschikbaar')
                                    @if(app(\App\Services\LibraryAccess::class)->has(request(),'bibliotheek.loans.create'))
<a class="library-button library-button-primary" href="{{ route('bibliotheek.loans.create', ['barcode' => $copy->barcode]) }}" aria-label="Exemplaar {{ $copy->barcode }} uitlenen">Uitlenen</a>
@endif
                                @endif
                                @if(app(\App\Services\LibraryAccess::class)->has(request(),'bibliotheek.qr.view'))
<a class="library-button library-button-secondary" href="{{ route('bibliotheek.books.qr-labels', ['copy' => $copy->id]) }}">Etiket afdrukken</a>
@endif
                                <button type="button" class="library-button library-button-secondary library-single-qr" data-barcode="{{ $copy->barcode }}" data-copy-id="{{ $copy->id }}" aria-label="QR-code voor exemplaar {{ $copy->barcode }} downloaden">QR downloaden</button>
                                @if(app(\App\Services\LibraryAccess::class)->has(request(),'bibliotheek.copies.edit'))
<a class="library-button library-button-secondary" href="{{ route('bibliotheek.books.copies.edit', [$book, $copy]) }}" aria-label="Exemplaar {{ $copy->barcode }} bewerken">Bewerken</a>
@endif
                                @if(app(\App\Services\LibraryAccess::class)->has(request(),'bibliotheek.loans.view'))
                                    <a class="library-button library-button-secondary" href="{{ route('bibliotheek.loans.index',['filter'=>'all','copy_id'=>$copy->id]) }}">Uitleenhistoriek</a>
                                @endif
                                @if($copy->status !== 'uitgeleend' && app(\App\Services\LibraryAccess::class)->has(request(),'bibliotheek.copies.status'))
                                    <form method="post" action="{{ route('bibliotheek.books.copies.status',[$book,$copy]) }}">@csrf
                                    <select name="status">@foreach(['beschikbaar','beschadigd','verloren','afgevoerd'] as $status)<option @selected($copy->status===$status)>{{ $status }}</option>@endforeach</select>
                                    <input name="note" required maxlength="2000" placeholder="Reden statuswijziging"><button class="library-button library-button-secondary">Status wijzigen</button></form>
                                @endif
                                @if($copy->status === 'beschikbaar')
                                    @if(app(\App\Services\LibraryAccess::class)->has(request(),'bibliotheek.copies.delete'))
<form method="post" action="{{ route('bibliotheek.books.copies.destroy', [$book, $copy]) }}" onsubmit="return confirm('Exemplaar {{ $copy->barcode }} definitief verwijderen? Het boek en de andere exemplaren blijven behouden.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="library-button library-button-secondary" aria-label="Exemplaar {{ $copy->barcode }} verwijderen">Verwijderen</button>
                                    </form>
@endif
                                @else
                                    <small class="library-help">Niet verwijderbaar zolang het exemplaar niet beschikbaar is.</small>
                                @endif
                            </div>
                        </td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
    <section class="library-form-card">
        <h2>Extra exemplaar registreren</h2>
        @if(app(\App\Services\LibraryAccess::class)->has(request(),'bibliotheek.copies.create'))
<form method="post" action="{{ route('bibliotheek.books.copies.store', $book) }}" class="library-form-grid">
            @csrf
            <div class="library-field"><label for="barcode">Unieke barcode / inventarisnummer *</label><input id="barcode" name="barcode" value="{{ old('barcode', $suggestedBarcode) }}" required maxlength="60"></div>
            <p class="library-help library-wide">De barcode is automatisch ingevuld. Je kunt die aanpassen als het exemplaar al een eigen nummer heeft.</p>
            <div class="library-field"><label for="shelf_location">Rek / locatie</label><input id="shelf_location" name="shelf_location" value="{{ old('shelf_location') }}" maxlength="120"></div>
            <div class="library-field"><label for="condition">Staat</label><select id="condition" name="condition"><option value="nieuw">Nieuw</option><option value="goed" selected>Goed</option><option value="gebruikt">Gebruikt</option><option value="beschadigd">Beschadigd</option></select></div>
            <div class="library-field library-wide"><label for="notes">Opmerkingen</label><textarea id="notes" name="notes" rows="2" maxlength="2000">{{ old('notes') }}</textarea></div>
            <div class="library-wide"><button type="submit" class="library-button library-button-primary">Exemplaar toevoegen</button></div>
        </form>
@endif
    </section>
</main>
@endsection
@push('scripts')
<script src="{{ asset('js/vendor/qrcode.js') }}"></script>
<script src="{{ asset('js/library-qr.js') }}"></script>
<script>
document.querySelectorAll('.library-single-qr').forEach(button => button.addEventListener('click', () => {
    try {
        const code = button.dataset.barcode;
        LibraryQr.download(LibraryQr.dataUrl(code), 'QR-' + LibraryQr.safeName(code) + '.png');
        fetch(@json(route('bibliotheek.books.qr-generated')), {
            method: 'POST',
            headers: {'Content-Type':'application/json', 'Accept':'application/json', 'X-CSRF-TOKEN': @json(csrf_token())},
            body: JSON.stringify({copies: [Number(button.dataset.copyId)]})
        }).catch(() => {});

    } catch (error) {
        alert('QR-code genereren is mislukt: ' + error.message);
    }
}));
</script>
@endpush
