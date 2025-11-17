@extends('layouts.app')

@section('title', 'Leerlingen')

@section('content')
    <main class="screen" id="screenStudents" role="main">

        {{-- 🔹 Bovenste kader: titel + zoekfunctie + instructie --}}
        <div class="card" style="margin-bottom: 20px;">
            <h2 style="margin-top:0;">👩‍🏫 Leerlingen</h2>
            <p class="lead" style="margin-bottom:16px;">
                Zoek een leerling om <b>details te bekijken</b> of een <b>melding te maken</b>.
            </p>

            <div class="controls" style="margin-bottom:10px;">
                <input id="q" class="search" type="search"
                       placeholder="Zoek: ‘3 EL’ of ‘Piet Pieters’…"
                       aria-label="Zoek klas of leerling"
                       autocomplete="off"/>
            </div>

            <p class="muted" style="font-size:0.95rem; margin:0;">
                Typ minstens 2 tekens of een geldige klas (bv. <i>3 EL</i>) om resultaten te zien.
            </p>
        </div>

        {{-- 🔹 Placeholder voor dynamische meldingen vanuit JS --}}
        <div id="infoCard" class="card" data-hidden="true"></div>

        {{-- 🔹 Dynamisch leerlingenoverzicht --}}
        <div class="grid" id="studentGrid" aria-live="polite"></div>

        {{-- 🎂 Verjaardagen-widget onderaan --}}
        <div class="card" style="margin-top: 28px;">
            <div class="card-header" style="margin-bottom: 8px;">
                <h2 style="margin:0;">🎂 Vandaag jarig</h2>
                <a href="{{ url('/leerlingen/verjaardagen') }}" class="btn btn-green btn--sm">
                    Verjaardagen binnen de week ({{ $upcomingCount ?? 0 }})
                </a>
            </div>

            @if(($vandaagJarig ?? collect())->isEmpty())
                <p class="muted">Niemand jarig vandaag.</p>
            @else
                <div class="bday-today-list">
                    @foreach($vandaagJarig as $l)
                        <a href="{{ url('/leerlingen/' . $l->id) }}" class="bday-today-row-link">
                            <div class="bday-today-row">
                                <div class="bday-today-name">
                                    {{ $l->voornaam }} {{ $l->achternaam }}
                                    <span class="badge badge-klas">{{ $l->klas->klasnaam }}</span>
                                </div>
                                <div class="bday-today-age">
                                    Wordt <strong>{{ $l->turns }}</strong>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

    </main>

    <script>
        const LEERLINGEN_DATA = {!! json_encode($leerlingen, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) !!};
    </script>
@endsection
