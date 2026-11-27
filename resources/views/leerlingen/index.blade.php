@extends('layouts.app')

@section('title', 'LVS · Leerlingen')

@section('breadcrumbs')
@include('partials.breadcrumbs', ['items' => [
        ['label' => 'Dashboard', 'url' => route('home')],
        ['label' => 'LVS', 'url' => route('lvs.dashboard')],
        ['label' => 'Leerlingen'],
    ]])
@endsection

@section('content')
<main class="screen lvs-screen" id="screenStudents" role="main">
    <div class="lvs-page-heading">
        <div>
            <p class="portal-eyebrow">LEERLINGVOLGSYSTEEM</p>
            <h1>{{ $historical ? 'Historische leerlingen' : 'Leerlingen' }}</h1>
            <p>Zoek een leerling of klas om gegevens te bekijken en meldingen op te volgen.</p>
        </div>
    </div>

    @if($canHistory)
    <nav class="lvs-student-tabs" aria-label="Leerlingstatus">
        <a class="{{ !$historical ? 'is-selected' : '' }}" @if(!$historical) aria-current="page" @endif href="{{ route('leerlingen.index') }}">Actieve leerlingen</a>
        <a class="{{ $historical ? 'is-selected' : '' }}" @if($historical) aria-current="page" @endif href="{{ route('leerlingen.index',['historisch'=>1]) }}">Historische leerlingen</a>
    </nav>
    @endif
    <div class="lvs-overview-grid">
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

    @unless($historical)
    <section class="card lvs-panel lvs-birthday-panel" aria-labelledby="birthdays-title">
        <div class="lvs-panel-heading lvs-panel-heading-wrap">
            <div>
                <p class="portal-eyebrow">VERJAARDAGEN</p>
                <h2 id="birthdays-title">Vandaag jarig</h2>
            </div>
            <a href="{{ route('leerlingen.verjaardagen') }}" class="lvs-inline-link">
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
                                @include('partials.student-photo',['studentId'=>$l->id])
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
    @endunless
    </div>

    <div id="infoCard" class="lvs-feedback" data-hidden="true" role="status" aria-live="polite"></div>
    <div class="lvs-results-heading" id="resultsHeading" data-hidden="true">
        <h2>Zoekresultaten</h2><span id="resultsCount"></span>
    </div>
    <div class="lvs-student-grid" id="studentGrid" aria-live="polite"></div>


</main>
@endsection

@push('scripts')
<script>
    window.LVS_PHOTO_BASE = @json(url("/leerlingfoto")."/");
    window.LVS_STUDENTS = @json($leerlingen);
</script>
<script src="{{ asset('js/lvs.js') }}?v=4" defer></script>
@endpush
