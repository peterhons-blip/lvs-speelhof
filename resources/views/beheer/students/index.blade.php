@extends('layouts.app')
@section('title', 'Leerlingenbeheer')
@section('breadcrumbs')
@include('partials.breadcrumbs', ['items' => [
    ['label' => 'Dashboard', 'url' => route('home')],
    ['label' => 'Beheer', 'url' => route('beheer.users.index')],
    ['label' => 'Leerlingenbeheer'],
]])
@endsection
@push('scripts')
<script src="{{ asset('js/management-live.js') }}?v=1" defer></script>
@endpush
@section('content')
<main class="library-page portal-users-page">
    <div class="library-page-head">
        <div>
            <p class="portal-eyebrow">PORTAALBEHEER / LEERLINGEN</p>
            <h1>Leerlingenbeheer</h1>
            <p>Beheer bestaande leerlinggegevens uit het LVS. Gegevens uit Smartschool worden iedere nacht opnieuw gesynchroniseerd.</p>
        </div>
    </div>
    @if(session('success'))<div class="library-message" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="library-errors" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <section class="library-form-card" data-live-management>
        <div class="portal-users-table-heading">
            <div><h2>Leerlingen</h2><p>Zoek op naam, Smartschool-gebruikersnaam of klas.</p></div>
            <span class="portal-users-total" data-management-total>{{ $students->total() }} leerlingen</span>
        </div>
        <nav class="portal-users-account-filters" style="display:flex;gap:0.75rem;flex-wrap:wrap;margin:1rem 0;">
            <a class="library-button {{ $showInactive ? 'library-button-secondary' : 'library-button-primary' }}" href="{{ route('beheer.students.index', $search !== '' ? ['zoek' => $search] : []) }}">Actieve leerlingen</a>
            <a class="library-button {{ $showInactive ? 'library-button-primary' : 'library-button-secondary' }}" href="{{ route('beheer.students.index', array_merge(['toon_inactief' => 1], $search !== '' ? ['zoek' => $search] : [])) }}">Alle leerlingen</a>
        </nav>
        <form method="get" action="{{ route('beheer.students.index') }}" style="display:flex;gap:0.75rem;flex-wrap:wrap;align-items:end;margin-bottom:1rem;">
            @if($showInactive)<input type="hidden" name="toon_inactief" value="1">@endif
            <div class="library-field" style="flex:1 1 240px;margin:0;">
                <label for="leerlingenbeheer-zoek">Leerling of klas zoeken</label>
                <input id="leerlingenbeheer-zoek" data-live-search type="search" name="zoek" maxlength="100" value="{{ $search }}" placeholder="Naam, gebruikersnaam of klas...">
            </div>
            <button class="library-button library-button-primary" type="submit">Zoeken</button>
            @if($search !== '')
                <a class="library-button library-button-secondary" href="{{ route('beheer.students.index', $showInactive ? ['toon_inactief' => 1] : []) }}">Wissen</a>
            @endif
        </form>
        <form method="post" action="{{ route('beheer.students.bulk') }}" id="students-bulk-form" data-bulk-form style="display:flex;gap:0.75rem;flex-wrap:wrap;align-items:end;margin:1rem 0;">
            @csrf
            <span data-selected-count>0 geselecteerd (deze pagina)</span>
            <div class="library-field" style="margin:0;"><label for="students-active">Bulkactie</label>
                <select id="students-active" name="active" required>
                    <option value="">Kies actie...</option>
                    <option value="1">Als actief markeren</option>
                    <option value="0">Als inactief markeren</option>
                </select>
            </div>
            <button class="library-button library-button-primary" type="submit">Toepassen op selectie</button>
        </form>
        <p class="portal-users-card-intro">Let op: de nachtelijke Smartschool-synchronisatie kan de actieve status van leerlingen opnieuw aanpassen.</p>
        <div data-management-results>
        <div class="library-table-wrap">
            <table class="library-table">
                <thead><tr><th><input type="checkbox" data-select-all aria-label="Selecteer alle leerlingen op deze pagina"></th><th>Leerling</th><th>Klas</th><th>Smartschool-gebruikersnaam</th><th>Status</th><th>Details</th></tr></thead>
                <tbody>
                    @forelse($students as $student)
                    <tr>
                        <td><input type="checkbox" data-bulk-id form="students-bulk-form" name="ids[]" value="{{ $student->id }}" aria-label="Selecteer {{ $student->voornaam }} {{ $student->naam }}"></td>
                        <td><strong>{{ trim(($student->voornaam ?? '').' '.($student->naam ?? '')) }}</strong></td>
                        <td>{{ $student->klasnaam ?: '—' }}</td>
                        <td>{{ $student->gebruikersnaam ?: '—' }}</td>
                        <td>{{ (int) $student->active === 1 ? 'Actief' : 'Inactief' }}</td>
                        <td><a class="library-button library-button-secondary" href="{{ route('beheer.students.show', $student->id) }}">Bekijken / bewerken</a></td>
                    </tr>
                    @empty
                    <tr><td colspan="6">Geen leerlingen gevonden.</td></tr>
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
        </div>
    </section>
</main>
@endsection
