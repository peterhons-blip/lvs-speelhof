@extends('layouts.app')
@section('title', 'LVS · Nieuwe melding')
@section('breadcrumbs')
@include('partials.breadcrumbs', ['items' => [
    ['label' => 'Dashboard', 'url' => route('home')],
    ['label' => 'LVS', 'url' => route('lvs.dashboard')],
    ['label' => 'Nieuwe melding'],
]])
@endsection
@section('content')
<main class="screen lvs-screen" role="main">
    <div class="lvs-page-heading">
        <div><p class="portal-eyebrow">LEERLINGVOLGSYSTEEM / NIEUWE MELDING</p>
            <h1>Voor welke leerling wil je een melding maken?</h1>
            <p>Zoek een leerling op naam of klas. Vervolgens kun je de categorie, soort, datum en omschrijving ingeven.</p>
        </div>
    </div>
    <section class="card lvs-panel lvs-search-panel" aria-labelledby="lvs-search-title">
        <div class="lvs-search-panel-heading">
            <div><h2 id="lvs-search-title">Leerling kiezen</h2><p>Alleen actieve leerlingen staan in de zoeklijst.</p></div>
        </div>
        <label class="lvs-field-label" for="q">Naam of klas</label>
        <div class="lvs-search-input-wrap">
            <input class="search lvs-search" type="search" id="q" name="q"
                placeholder="Bijv. Piet Pieters of 3 EL" autocomplete="off" autofocus>
        </div>
        <p class="lvs-field-help">Typ minstens twee tekens om te zoeken.</p>
    </section>
    <div id="infoCard" class="lvs-feedback" data-hidden="true" role="status" aria-live="polite"></div>
    <div class="lvs-results-heading" id="resultsHeading" data-hidden="true">
        <h2>Leerlingen</h2><span id="resultsCount"></span>
    </div>
    <div class="lvs-student-grid" id="studentGrid" data-link-base="{{ url('/meldingen/add') }}/" data-link-label="Melding maken voor deze leerling" aria-live="polite"></div>
</main>
@endsection
@push('scripts')
<script>window.LVS_PHOTO_BASE = @json(url("/leerlingfoto")."/"); window.LVS_STUDENTS = @json($leerlingen);</script>
<script src="{{ asset('js/lvs.js') }}?v=4" defer></script>
@endpush
