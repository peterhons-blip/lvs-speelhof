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
        <a href="{{ route('leerlingen.index') }}" class="library-button library-button-primary">Leerling zoeken</a>
    </div>

    <section class="lvs-dashboard-stats" aria-label="LVS in cijfers">
        @foreach([
            ['value' => $stats['students'], 'label' => 'Actieve leerlingen', 'hint' => 'Volgens de laatste leerlingensynchronisatie'],
            ['value' => $stats['notifications'], 'label' => 'Meldingen totaal', 'hint' => 'Alle geregistreerde meldingen'],
            ['value' => $stats['this_month'], 'label' => 'Meldingen deze maand', 'hint' => 'Op basis van de datum van het voorval'],
            ['value' => $stats['last_7_days'], 'label' => 'Meldingen laatste 7 dagen', 'hint' => 'Op basis van de datum van het voorval'],
        ] as $stat)
            <div class="card lvs-stat-card">
                <span class="lvs-stat-value">{{ number_format($stat['value'], 0, ',', '.') }}</span>
                <strong>{{ $stat['label'] }}</strong>
                <small>{{ $stat['hint'] }}</small>
            </div>
        @endforeach
    </section>

    <section class="lvs-dashboard-quick-links">
        <a href="{{ route('leerlingen.index') }}" class="card lvs-dashboard-action">
            <span class="lvs-dashboard-action-icon" aria-hidden="true">⌕</span><strong>Leerlingen</strong><small>Zoek op naam of klas en open het leerlingdossier.</small>
        </a>
        <a href="{{ route('meldingen.index') }}" class="card lvs-dashboard-action">
            <span class="lvs-dashboard-action-icon" aria-hidden="true">☷</span><strong>Alle meldingen</strong><small>Zoeken, filteren en opvolgen per categorie of datum.</small>
        </a>
        <a href="{{ route('leerlingen.verjaardagen') }}" class="card lvs-dashboard-action">
            <span class="lvs-dashboard-action-icon" aria-hidden="true">✦</span><strong>Verjaardagen</strong><small>Leerlingen die binnenkort jarig zijn.</small>
        </a>
        @if($canSeeReports)
            <a href="{{ route('lvs.reports.index') }}" class="card lvs-dashboard-action">
                <span class="lvs-dashboard-action-icon" aria-hidden="true">▥</span><strong>Klassenraadoverzicht</strong><small>Overzicht per klas en categorie, zonder automatische sancties.</small>
            </a>
        @endif
    </section>

    <div class="lvs-dashboard-columns">
        <section class="card lvs-panel">
            <div class="lvs-panel-heading lvs-panel-heading-wrap"><h2>Recente meldingen</h2><a class="lvs-inline-link" href="{{ route('meldingen.index') }}">Alle meldingen →</a></div>
            @forelse($recent as $m)
                <a class="lvs-dashboard-recent" href="{{ route('leerlingen.show', $m->leerlingId) }}">
                    <span><strong>{{ $m->voornaam }} {{ $m->naam }}</strong><small>{{ $m->klasnaam ?: 'Geen klas' }} · {{ $m->categorie ?: 'Zonder categorie' }} / {{ $m->soort ?: 'Onbekend' }}</small></span>
                    <time datetime="{{ $m->gebeurdop }}">{{ $m->gebeurdop ? \Illuminate\Support\Carbon::parse($m->gebeurdop)->format('d/m/Y') : '—' }}</time>
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
</main>
@endsection
