@extends('layouts.app')

@section('title', 'Boekencatalogus')

@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Dashboard', 'url' => route('home')],
        ['label' => 'Bibliotheek', 'url' => route('bibliotheek.index')],
        ['label' => 'Boekencatalogus'],
    ]])
@endsection

@section('content')
<main class="library-page" aria-labelledby="catalog-title">
    <div class="library-page-head">
        <div>
            <p class="portal-eyebrow">BIBLIOTHEEK</p>
            <h1 id="catalog-title">Boekencatalogus</h1>
            <p>Zoek boeken op titel, auteur, ISBN of exemplaarbarcode.</p>
        </div>
        <div class="library-form-actions" style="margin:0">
            <a class="library-button library-button-secondary" href="{{ route('bibliotheek.books.import.create') }}">ISBN-lijst importeren</a>
            <a class="library-button library-button-secondary" href="{{ route('bibliotheek.books.import.history') }}">Importhistoriek</a>
            <a class="library-button library-button-primary" href="{{ route('bibliotheek.books.create') }}">+ Boek toevoegen</a>
        </div>
    </div>
    @if(session('success'))<div class="library-message" role="status">{{ session('success') }}</div>@endif
    <form class="library-search" action="{{ route('bibliotheek.books.index') }}" method="get" role="search">
        <label for="book-search">Zoeken in de catalogus</label>
        <div class="library-search-row">
            <input id="book-search" name="q" value="{{ $query }}" placeholder="Titel, auteur, ISBN of barcode" autocomplete="off">
            <button class="library-button library-button-primary" type="submit">Zoeken</button>
            @if($query !== '')<a class="library-button library-button-secondary" href="{{ route('bibliotheek.books.index') }}">Wissen</a>@endif
        </div>
    </form>
    <div class="library-list">
        @forelse($books as $book)
            <a class="library-list-item" href="{{ route('bibliotheek.books.show', $book) }}">
                <div class="library-cover">
                    @if($book->cover_url)
                        <img src="{{ $book->cover_url }}" alt="Omslag van {{ $book->title }}" loading="lazy" referrerpolicy="no-referrer">
                    @else <span aria-hidden="true">▤</span> @endif
                </div>
                <div class="library-list-info">
                    <strong>{{ $book->title }}</strong>
                    @if($book->authors)<span>{{ $book->authors }}</span>@endif
                    <small>ISBN: {{ $book->isbn ?: 'Niet ingevuld' }}</small>
                </div>
                <div class="library-stock"><strong>{{ $book->available_copies_count }} / {{ $book->copies_count }}</strong><small>beschikbaar</small></div>
                <span class="library-arrow" aria-hidden="true">→</span>
            </a>
        @empty
            <div class="library-empty">
                <strong>Geen boeken gevonden</strong>
                <p>{{ $query !== '' ? 'Probeer een andere zoekterm.' : 'Voeg het eerste boek toe om de catalogus te starten.' }}</p>
            </div>
        @endforelse
    </div>
    <div class="library-pagination">{{ $books->links() }}</div>
</main>
@endsection
