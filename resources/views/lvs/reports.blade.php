@extends('layouts.app')
@section('title', 'LVS · Klassenraad')
@section('breadcrumbs')
@include('partials.breadcrumbs', ['items' => [
    ['label' => 'Dashboard', 'url' => route('home')],
    ['label' => 'LVS', 'url' => route('lvs.dashboard')],
    ['label' => 'Klassenraadoverzicht'],
]])
@endsection
@section('content')
<main class="library-page lvs-reports-page">
    <div class="library-page-head">
        <div><p class="portal-eyebrow">LVS / OPVOLGING</p><h1>Klassenraadoverzicht</h1><p>Een voorbereidende telling van meldingen per klas en categorie. Geen automatische sancties of meldingen naar ouders.</p></div>
        <a href="{{ route('meldingen.index') }}" class="library-button library-button-secondary">Alle meldingen</a>
    </div>
    <section class="library-form-card">
        <h2>Periode en klas</h2>
        <form method="get" class="lvs-report-filters">
            <div class="library-field"><label for="start">Van</label><input type="date" id="start" name="start" value="{{ $start }}" required></div>
            <div class="library-field"><label for="end">Tot en met</label><input type="date" id="end" name="end" value="{{ $end }}" required></div>
            <div class="library-field"><label for="klas">Klas (optioneel)</label><input type="search" id="klas" name="klas" value="{{ $data['klas'] ?? '' }}" placeholder="Bijv. 5 WEWI"></div>
            <button class="library-button library-button-primary" type="submit">Toon overzicht</button>
        </form>
    </section>
    <div class="lvs-reports-overview">
        <section class="card lvs-panel">
            <div class="lvs-panel-heading"><h2>Meldingen per klas en categorie</h2><span class="lvs-count-pill">{{ $total }} meldingen</span></div>
            <div class="library-table-wrap"><table class="library-table">
                <thead><tr><th>Klas</th><th>Categorie</th><th>Aantal</th></tr></thead>
                <tbody>
                    @forelse($perClass as $item)
                        <tr><td>{{ $item->klasnaam ?: 'Onbekend' }}</td><td>{{ $item->categorie }}</td><td>{{ $item->aantal }}</td></tr>
                    @empty
                        <tr><td colspan="3">Geen meldingen in deze periode.</td></tr>
                    @endforelse
                </tbody>
            </table></div>
        </section>
        <section class="card lvs-panel">
            <div class="lvs-panel-heading"><h2>Leerlingen met meldingen</h2></div>
            <div class="library-table-wrap"><table class="library-table">
                <thead><tr><th>Leerling</th><th>Klas</th><th>Aantal</th></tr></thead>
                <tbody>
                    @forelse($topStudents as $student)
                        <tr><td><a href="{{ route('leerlingen.show', $student->id) }}">{{ $student->voornaam }} {{ $student->naam }}</a></td><td>{{ $student->klasnaam ?: '—' }}</td><td>{{ $student->aantal }}</td></tr>
                    @empty
                        <tr><td colspan="3">Geen meldingen in deze periode.</td></tr>
                    @endforelse
                </tbody>
            </table></div>
            <p class="lvs-field-help">Maximaal 50 leerlingen getoond, gerangschikt op aantal. Dit is een administratief hulpmiddel en geen automatische gedragsscore.</p>
        </section>
    </div>
</main>
@endsection
