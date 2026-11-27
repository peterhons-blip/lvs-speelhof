@extends('layouts.app')
@section('title', 'LVS · Dashboard')
@section('breadcrumbs')
@include('partials.breadcrumbs', ['items' => [
    ['label' => 'Portaal', 'url' => route('home')],
    ['label' => 'LVS-dashboard'],
]])
@endsection
@section('content')
<main class="library-page lvs-dashboard-page">
    <div class="library-page-head">
        <div>
            <p class="portal-eyebrow">LEERLINGVOLGSYSTEEM</p>
            <h1>LVS-dashboard</h1>
            <p>Een centraal overzicht van leerlingen, gedragsmeldingen en de opvolging. Gegevens komen rechtstreeks uit het bestaande LVS.</p>
        </div>
        <div class="lvs-dashboard-header-actions">
            @if($canSeeStudents)<a href="{{ route('leerlingen.index') }}" class="library-button library-button-secondary">Leerling zoeken</a>@endif
            @if($canCreate)<a href="{{ route('meldingen.select-student') }}" class="library-button library-button-primary">+ Nieuwe melding</a>@endif
        </div>
    </div>

    @if($canSeeStudents)
    <section class="card lvs-panel lvs-dashboard-search" data-lvs-quick-search="{{ route('lvs.students.search') }}">
        <div class="lvs-panel-heading lvs-panel-heading-wrap"><h2>Leerling zoeken</h2>@if(app(\App\Services\LibraryAccess::class)->has(request(),'lvs.my_classes.view'))<a class="library-button library-button-secondary" href="{{ route('lvs.my-classes.index') }}">Mijn klassen</a>@endif</div>
        <label for="dashboard-student-search" class="lvs-field-label">Naam of klas</label>
        <input id="dashboard-student-search" class="lvs-search" type="search" placeholder="Typ de naam of klas van een leerling…" autocomplete="off">
        <p class="lvs-field-help" data-search-status role="status">Typ minstens twee tekens.</p>
        <div class="class-roster" data-search-results></div>
    </section>
    @endif
    <section class="lvs-dashboard-stats" aria-label="LVS in cijfers">
        @foreach([
            ['value' => $stats['students'], 'label' => 'Actieve leerlingen', 'hint' => 'Volgens de laatste leerlingensynchronisatie'],
            ['value' => $stats['notifications'], 'label' => 'Meldingen totaal', 'hint' => 'Alle geregistreerde meldingen'],
            ['value' => $stats['this_month'], 'label' => 'Meldingen deze maand', 'hint' => 'Op basis van de datum van het voorval'],
            ['value' => $stats['last_7_days'], 'label' => 'Meldingen laatste 7 dagen', 'hint' => 'Op basis van de datum van het voorval'],
        ] as $stat)
            <div class="card lvs-stat-card">
                <span class="lvs-stat-value">{{ $stat['value'] === null ? '—' : number_format($stat['value'], 0, ',', '.') }}</span>
                <strong>{{ $stat['label'] }}</strong>
                <small>{{ $stat['hint'] }}</small>
            </div>
        @endforeach
    </section>

    <section class="lvs-dashboard-quick-links">
        @if($canSeeStudents)
        <a href="{{ route('leerlingen.index') }}" class="card lvs-dashboard-action">
            <span class="lvs-dashboard-action-icon" aria-hidden="true">⌕</span><strong>Leerlingen</strong><small>Zoek op naam of klas en open het leerlingdossier.</small>
        </a>
        @endif
        @if($canSeeNotifications)
        <a href="{{ route('meldingen.index') }}" class="card lvs-dashboard-action">
            <span class="lvs-dashboard-action-icon" aria-hidden="true">☷</span><strong>Alle meldingen</strong><small>Zoeken, filteren en opvolgen per categorie of datum.</small>
        </a>
        @endif
        @if($canSeeStudents)
        <a href="{{ route('leerlingen.verjaardagen') }}" class="card lvs-dashboard-action">
            <span class="lvs-dashboard-action-icon" aria-hidden="true">✦</span><strong>Verjaardagen</strong><small>Leerlingen die binnenkort jarig zijn.</small>
        </a>
        @endif
        @if($canSeeFollowups)
            <a href="{{ route('lvs.followups.index') }}" class="card lvs-dashboard-action">
                <span class="lvs-dashboard-action-icon" aria-hidden="true">◎</span><strong>Opvolging</strong>
                <small>Signalen en sanctievoorstellen beoordelen.</small>
            </a>
        @endif
        @if(app(\App\Services\LibraryAccess::class)->has(request(),'lvs.secretariat_visits.manage'))
            <a href="{{ route('secretariat-visits.index') }}" class="card lvs-dashboard-action">
                <span class="lvs-dashboard-action-icon" aria-hidden="true">✓</span><strong>Secretariaatsbezoeken (18+)</strong>
                <small>Volg op welke meerderjarige leerlingen zich bij het secretariaat hebben gemeld.</small>
            </a>
        @endif
        @if($canSeeReports)
            <a href="{{ route('lvs.reports.index') }}" class="card lvs-dashboard-action">
                <span class="lvs-dashboard-action-icon" aria-hidden="true">▥</span><strong>Klassenraadoverzicht</strong><small>Overzicht per klas en categorie, zonder automatische sancties.</small>
            </a>
        @endif
    </section>

    @if($canSeeNotifications)
    <div class="lvs-dashboard-columns">
        <section class="card lvs-panel">
            <div class="lvs-panel-heading lvs-panel-heading-wrap"><h2>Recente meldingen</h2><a class="lvs-inline-link" href="{{ route('meldingen.index') }}">Alle meldingen →</a></div>
            @forelse($recent as $m)
                <a class="lvs-dashboard-recent" href="{{ route('leerlingen.show', $m->leerlingId) }}">
                    <span class="student-photo-inline">@include('partials.student-photo',['studentId'=>$m->leerlingId])<span><strong>{{ $m->voornaam }} {{ $m->naam }}</strong><small>{{ $m->klasnaam ?: 'Geen klas' }} · {{ $m->categorie ?: 'Zonder categorie' }} / {{ $m->soort ?: 'Onbekend' }}</small></span></span>
                    <time datetime="{{ $m->gebeurdop }}">{{ $m->gebeurdop && preg_match('/^[1-9][0-9]{3}-/', (string)$m->gebeurdop) ? \Illuminate\Support\Carbon::parse($m->gebeurdop)->format('d/m/Y') : 'Datum onbekend' }}</time>
                </a>
            @empty
                <p class="lvs-empty">Nog geen meldingen geregistreerd.</p>
            @endforelse
        </section>

        <section class="card lvs-panel">
            <div class="lvs-panel-heading lvs-panel-heading-wrap"><h2>Meldingen per categorie</h2><span class="lvs-count-pill">{{ $categories->count() }}</span></div>
            @forelse($categories as $category)
                <a class="lvs-dashboard-category" href="{{ route('meldingen.index', ['categorie' => $category->id]) }}">
                    <span>{{ $category->naam }}</span><strong>{{ $category->totaal }}</strong>
                </a>
            @empty
                <p class="lvs-empty">Nog geen categorieën beschikbaar.</p>
            @endforelse
            <p class="lvs-field-help">Deze aantallen zijn informatief. Er worden geen sancties of automatische berichten geactiveerd.</p>
        </section>
    </div>
    @else
        <section class="card lvs-panel">
            <h2>Geen toegang tot meldingsdetails</h2>
            <p>Je kunt het LVS-dashboard openen, maar je hebt geen recht om meldingen of categorieoverzichten te bekijken. Vraag de ICT-coördinator om je rechten na te kijken.</p>
        </section>
    @endif
</main>
@endsection

@push('scripts')
<script src="{{ asset('js/lvs-quick-search.js') }}?v=1" defer></script>
@endpush
