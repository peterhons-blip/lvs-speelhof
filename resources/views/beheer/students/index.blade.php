@extends('layouts.app')
@section('title', 'Leerlingenbeheer')
@section('breadcrumbs')
@include('partials.breadcrumbs', ['items' => [
    ['label' => 'Dashboard', 'url' => route('home')],
    ['label' => 'Beheer', 'url' => route('beheer.users.index')],
    ['label' => 'Leerlingenbeheer'],
]])
@endsection
@section('content')
<main class="library-page portal-users-page">
    <div class="library-page-head">
        <div>
            <p class="portal-eyebrow">PORTAALBEHEER / LEERLINGEN</p>
            <h1>Leerlingenbeheer</h1>
            <p>Beheer bestaande leerlinggegevens uit het LVS. Gegevens uit Smartschool worden iedere nacht opnieuw gesynchroniseerd.</p>
        </div>
    </div>
    <section class="library-form-card">
        <div class="portal-users-table-heading">
            <div><h2>Leerlingen</h2><p>Zoek op naam, Smartschool-gebruikersnaam of klas.</p></div>
            <span class="portal-users-total">{{ $students->total() }} leerlingen</span>
        </div>
        <nav class="portal-users-account-filters" style="display:flex;gap:0.75rem;flex-wrap:wrap;margin:1rem 0;">
            <a class="library-button {{ $showInactive ? 'library-button-secondary' : 'library-button-primary' }}" href="{{ route('beheer.students.index', $search !== '' ? ['zoek' => $search] : []) }}">Actieve leerlingen</a>
            <a class="library-button {{ $showInactive ? 'library-button-primary' : 'library-button-secondary' }}" href="{{ route('beheer.students.index', array_merge(['toon_inactief' => 1], $search !== '' ? ['zoek' => $search] : [])) }}">Alle leerlingen</a>
        </nav>
        <form method="get" action="{{ route('beheer.students.index') }}" style="display:flex;gap:0.75rem;flex-wrap:wrap;align-items:end;margin-bottom:1rem;">
            @if($showInactive)<input type="hidden" name="toon_inactief" value="1">@endif
            <div class="library-field" style="flex:1 1 240px;margin:0;">
                <label for="leerlingenbeheer-zoek">Leerling of klas zoeken</label>
                <input id="leerlingenbeheer-zoek" type="search" name="zoek" maxlength="100" value="{{ $search }}" placeholder="Naam, gebruikersnaam of klas...">
            </div>
            <button class="library-button library-button-primary" type="submit">Zoeken</button>
            @if($search !== '')
                <a class="library-button library-button-secondary" href="{{ route('beheer.students.index', $showInactive ? ['toon_inactief' => 1] : []) }}">Wissen</a>
            @endif
        </form>
        <div class="library-table-wrap">
            <table class="library-table">
                <thead><tr><th>Leerling</th><th>Klas</th><th>Smartschool-gebruikersnaam</th><th>Status</th><th>Details</th></tr></thead>
                <tbody>
                    @forelse($students as $student)
                    <tr>
                        <td><strong>{{ trim(($student->voornaam ?? '').' '.($student->naam ?? '')) }}</strong></td>
                        <td>{{ $student->klasnaam ?: '—' }}</td>
                        <td>{{ $student->gebruikersnaam ?: '—' }}</td>
                        <td>{{ (int) $student->active === 1 ? 'Actief' : 'Inactief' }}</td>
                        <td><a class="library-button library-button-secondary" href="{{ route('beheer.students.show', $student->id) }}">Bekijken / bewerken</a></td>
                    </tr>
                    @empty
                    <tr><td colspan="5">Geen leerlingen gevonden.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <nav class="portal-users-pagination" aria-label="Pagina's met leerlingen">
            <span class="portal-pagination-summary">Leerlingen {{ $students->firstItem() ?? 0 }}–{{ $students->lastItem() ?? 0 }} van {{ $students->total() }}</span>
            @if($students->hasPages())
            <div class="portal-pagination-controls">
                @if($students->onFirstPage())<span class="portal-page-link is-disabled">‹ Vorige</span>
                @else<a class="portal-page-link" href="{{ $students->previousPageUrl() }}">‹ Vorige</a>@endif
                @if($students->hasMorePages())<a class="portal-page-link" href="{{ $students->nextPageUrl() }}">Volgende ›</a>
                @else<span class="portal-page-link is-disabled">Volgende ›</span>@endif
            </div>
            @endif
        </nav>
    </section>
</main>
@endsection
