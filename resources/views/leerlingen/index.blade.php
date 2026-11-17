@extends('layouts.app')

@section('title', 'LVS · Leerlingen')

@section('breadcrumbs')
@include('partials.breadcrumbs', ['items' => [
        ['label' => 'Dashboard', 'url' => route('home')],
        ['label' => 'LVS'],
    ]])
@endsection

@section('content')
<main class="screen lvs-screen" id="screenStudents" role="main">
    <div class="lvs-page-heading">
        <div>
            <p class="portal-eyebrow">LEERLINGVOLGSYSTEEM</p>
            <h1>Leerlingen</h1>
            <p>Zoek een leerling of klas om gegevens te bekijken en meldingen op te volgen.</p>
        </div>
    </div>

    <section class="card lvs-panel lvs-search-panel" aria-labelledby="lvs-search-title">
        <div class="lvs-search-panel-heading">
            <div>
                <h2 id="lvs-search-title">Zoek een leerling</h2>
                <p>Zoek op naam of klas en open meteen het leerlingdossier.</p>
            </div>
        </div>
        <label for="q" class="lvs-field-label">Naam of klas</label>
        <div class="lvs-search-input-wrap">
            <input id="q" class="search lvs-search" type="search"
                   placeholder="Bijvoorbeeld: Piet Pieters of 3 EL"
                   aria-label="Zoek klas of leerling"
                   autocomplete="off"
                   spellcheck="false">
        </div>
        <p class="lvs-field-help">Typ minstens twee tekens om te zoeken.</p>
    </section>

    <div id="infoCard" class="lvs-feedback" data-hidden="true" role="status" aria-live="polite"></div>
    <div class="lvs-results-heading" id="resultsHeading" data-hidden="true">
        <h2>Zoekresultaten</h2><span id="resultsCount"></span>
    </div>
    <div class="lvs-student-grid" id="studentGrid" aria-live="polite"></div>

    <section class="card lvs-panel lvs-birthday-panel" aria-labelledby="birthdays-title">
        <div class="lvs-panel-heading lvs-panel-heading-wrap">
            <div>
                <p class="portal-eyebrow">VERJAARDAGEN</p>
                <h2 id="birthdays-title">Vandaag jarig</h2>
            </div>
            <a href="{{ route('leerlingen.verjaardagen') }}" class="lvs-link-button lvs-link-button-subtle">
                Bekijk komende week ({{ $upcomingCount ?? 0 }}) →
            </a>
        </div>

        @if(($vandaagJarig ?? collect())->isEmpty())
            <p class="lvs-empty">Vandaag is er geen leerling jarig.</p>
        @else
            <div class="bday-today-list">
                @foreach($vandaagJarig as $l)
                    <a href="{{ route('leerlingen.show', $l->id) }}" class="bday-today-row-link">
                        <div class="bday-today-row">
                            <div class="bday-today-name">
                                <strong>{{ $l->voornaam }} {{ $l->achternaam }}</strong>
                                <span class="badge badge-klas">{{ optional($l->klas)->klasnaam ?? '—' }}</span>
                            </div>
                            <div class="bday-today-age">Wordt <strong>{{ $l->turns }}</strong> jaar</div>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </section>
</main>
@endsection

@push('scripts')
<script>
    window.LVS_STUDENTS = @json($leerlingen);
</script>
<script src="{{ asset('js/lvs.js') }}?v=3" defer></script>
@endpush
