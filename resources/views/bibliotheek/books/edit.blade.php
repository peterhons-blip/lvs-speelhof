@extends('layouts.app')
@section('title', 'Boek bewerken')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Dashboard', 'url' => route('home')],
        ['label' => 'Bibliotheek', 'url' => route('bibliotheek.index')],
        ['label' => 'Boekencatalogus', 'url' => route('bibliotheek.books.index')],
        ['label' => $book->title, 'url' => route('bibliotheek.books.show', $book)],
        ['label' => 'Bewerken'],
    ]])
@endsection
@section('content')
<main class="library-page library-form-page">
    <div class="library-page-head"><div><p class="portal-eyebrow">BIBLIOTHEEK / BOEKENCATALOGUS</p><h1>Boek bewerken</h1><p>Wijzig de gegevens van {{ $book->title }}. Exemplaren en uitleningen blijven behouden.</p></div>
    <a class="library-button library-button-secondary" href="{{ route('bibliotheek.books.show', $book) }}">Terug naar boek</a></div>
    @if($errors->any())<div class="library-errors" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form class="library-form" method="post" action="{{ route('bibliotheek.books.update', $book) }}">
        @csrf
        @method('PUT')
        <section class="library-form-card">
            <h2>Boekgegevens</h2>
            <div class="library-form-grid">
                <div class="library-field"><label for="isbn">ISBN</label><input id="isbn" name="isbn" value="{{ old('isbn', $book->isbn) }}" maxlength="20"></div>
                <div class="library-field"><label for="title">Titel *</label><input id="title" name="title" value="{{ old('title', $book->title) }}" maxlength="255" required></div>
                <div class="library-field"><label for="subtitle">Ondertitel</label><input id="subtitle" name="subtitle" value="{{ old('subtitle', $book->subtitle) }}" maxlength="255"></div>
                <div class="library-field"><label for="authors">Auteur(s)</label><input id="authors" name="authors" value="{{ old('authors', $book->authors) }}" maxlength="255"></div>
                <div class="library-field"><label for="publisher">Uitgever</label><input id="publisher" name="publisher" value="{{ old('publisher', $book->publisher) }}" maxlength="255"></div>
                <div class="library-field"><label for="published_year">Uitgavejaar / datum</label><input id="published_year" name="published_year" value="{{ old('published_year', $book->published_year) }}" maxlength="20"></div>
                <div class="library-field library-wide"><label for="category">Categorie / genre</label><input id="category" name="category" value="{{ old('category', $book->category) }}" maxlength="255"></div>
                <div class="library-field library-wide"><label for="description">Omschrijving</label><textarea id="description" name="description" rows="6" maxlength="5000">{{ old('description', $book->description) }}</textarea></div>
                <div class="library-field library-wide"><label for="cover_url">Omslag-URL (Open Library)</label><input id="cover_url" name="cover_url" value="{{ old('cover_url', $book->cover_url) }}" maxlength="500" placeholder="Laat leeg om de omslag te verwijderen"><p class="library-help">Je kunt een onjuiste omslag verwijderen door dit veld leeg te maken.</p></div>
            </div>
        </section>
        <div class="library-form-actions"><button type="submit" class="library-button library-button-primary">Wijzigingen opslaan</button><a href="{{ route('bibliotheek.books.show', $book) }}" class="library-button library-button-secondary">Annuleren</a></div>
    </form>
</main>
@endsection
