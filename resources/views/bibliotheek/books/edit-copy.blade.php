@extends('layouts.app')

@section('title', 'Exemplaar bewerken')

@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Dashboard', 'url' => route('home')],
        ['label' => 'Bibliotheek', 'url' => route('bibliotheek.index')],
        ['label' => 'Boekencatalogus', 'url' => route('bibliotheek.books.index')],
        ['label' => $book->title, 'url' => route('bibliotheek.books.show', $book)],
        ['label' => 'Exemplaar bewerken'],
    ]])
@endsection

@section('content')
<main class="library-page library-form-page">
    <div class="library-page-head">
        <div>
            <p class="portal-eyebrow">BIBLIOTHEEK / FYSIEKE EXEMPLAREN</p>
            <h1>Exemplaar bewerken</h1>
            <p>{{ $book->title }} · {{ $copy->barcode }}</p>
        </div>
        <a class="library-button library-button-secondary" href="{{ route('bibliotheek.books.show', $book) }}">Terug naar boek</a>
    </div>

    @if($errors->any())
        <div class="library-errors" role="alert"><strong>Controleer de invoer:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <form method="post" class="library-form" action="{{ route('bibliotheek.books.copies.update', [$book, $copy]) }}">
        @csrf
        @method('PUT')
        <section class="library-form-card">
            <h2>Gegevens van dit exemplaar</h2>
            <div class="library-form-grid">
                <div class="library-field">
                    <label for="barcode">Unieke barcode / inventarisnummer *</label>
                    <input id="barcode" name="barcode" value="{{ old('barcode', $copy->barcode) }}" required maxlength="60">
                </div>
                <div class="library-field">
                    <label for="shelf_location">Rek / locatie</label>
                    <input id="shelf_location" name="shelf_location" value="{{ old('shelf_location', $copy->shelf_location) }}" maxlength="120">
                </div>
                <div class="library-field">
                    <label for="condition">Staat</label>
                    <select id="condition" name="condition">
                        @foreach(['nieuw' => 'Nieuw', 'goed' => 'Goed', 'gebruikt' => 'Gebruikt', 'beschadigd' => 'Beschadigd'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('condition', $copy->condition) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="library-field">
                    <label>Uitleenstatus</label>
                    <p class="library-copy-readonly-status"><span class="library-pill">{{ ucfirst($copy->status) }}</span></p>
                    <p class="library-help">De uitleenstatus verandert alleen via uitlenen of inleveren en kan hier niet worden aangepast.</p>
                </div>
                <div class="library-field library-wide">
                    <label for="notes">Opmerkingen</label>
                    <textarea id="notes" name="notes" rows="4" maxlength="2000">{{ old('notes', $copy->notes) }}</textarea>
                </div>
            </div>
        </section>
        <div class="library-form-actions">
            <button type="submit" class="library-button library-button-primary">Exemplaar opslaan</button>
            <a href="{{ route('bibliotheek.books.show', $book) }}" class="library-button library-button-secondary">Annuleren</a>
        </div>
    </form>
</main>
@endsection
