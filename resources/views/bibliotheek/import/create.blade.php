@extends('layouts.app')

@section('title', 'ISBN-lijst importeren')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Dashboard', 'url' => route('home')],
        ['label' => 'Bibliotheek', 'url' => route('bibliotheek.index')],
        ['label' => 'Boekencatalogus', 'url' => route('bibliotheek.books.index')],
        ['label' => 'ISBN-lijst importeren'],
    ]])
@endsection
@section('content')
<main class="library-page library-form-page">
    <div class="library-page-head">
        <div><p class="portal-eyebrow">BIBLIOTHEEK / BOEKENCATALOGUS</p><h1>Boeken importeren via ISBN</h1>
        <p>Plak ISBN-nummers (één per regel, of gescheiden door komma's of puntkomma's). Maximaal 150 nummers per import.</p></div>
        <div class="library-form-actions" style="margin:0">
            <a class="library-button library-button-secondary" href="{{ route('bibliotheek.books.import.history') }}">Importhistoriek</a>
            <a class="library-button library-button-secondary" href="{{ route('bibliotheek.books.index') }}">Terug naar catalogus</a>
        </div>
    </div>
    @if($errors->any())<div class="library-errors" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form action="{{ route('bibliotheek.books.import.store') }}" method="post" class="library-form-card">
        @csrf
        <div class="library-field">
            <label for="isbns">ISBN-nummers</label>
            <textarea id="isbns" name="isbns" rows="12" maxlength="15000" required placeholder="9780747532699&#10;9789026127267">{{ old('isbns') }}</textarea>
        </div>
        <p class="library-help">Elk ISBN wordt gecontroleerd. Open Library levert de boekgegevens. Bestaande ISBN's worden overgeslagen; een nieuw boek krijgt automatisch één exemplaar en een unieke barcode. De geïmporteerde gegevens kun je achteraf wijzigen.</p>
        <button class="library-button library-button-primary" type="submit">Import starten</button>
    </form>
</main>
@endsection
