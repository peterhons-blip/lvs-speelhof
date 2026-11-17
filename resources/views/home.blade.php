@extends('layouts.app')

@section('title', 'Welkom')

@section('content')
    <main class="screen">

        {{-- DUIDELIJK ERRORBLOK BOVENAAN --}}
        @if(session('error'))
            <div
                style="
                    max-width: 860px;
                    margin: 0 auto 16px auto;
                    background: #fff1f2;
                    border: 1px solid #fecdd3;
                    color: #9f1239;
                    padding: 14px 16px;
                    border-radius: 12px;
                    font-size: 0.95rem;
                    line-height: 1.4;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.06);
                "
                role="alert"
            >
                <strong>⚠️ Aanmelden niet gelukt</strong><br>
                {{ session('error') }}
            </div>
        @endif

        {{-- NORMALE WELKOMSTCARD --}}

        <div class="card">
            <h2>Welkom bij het Atheneum Portaal!</h2>

            <p>
                Welkom op het centrale digitale platform van
                <strong>GO! Atheneum Sint-Truiden – campus Speelhof</strong>.
            </p>

            <p>
                Het Atheneum Portaal bundelt verschillende toepassingen
                voor leerkrachten, secretariaat, leerlingenbegeleiding
                en directie op één overzichtelijke plaats.
                Het ondersteunt de dagelijkse schoolwerking en maakt
                administratieve processen eenvoudiger en efficiënter.
            </p>

            <h3>Beschikbare toepassingen</h3>
            <p>
                Via het <strong>Leerlingvolgsysteem (LVS)</strong>
                kunnen leerkrachten leerlingen opzoeken,
                gedragsmeldingen registreren en opvolgen en
                belangrijke leerlinginformatie raadplegen.
                Daarnaast automatiseert het platform onder andere
                verjaardagsberichten en de opvolging van leerlingen
                die 18 jaar worden.
            </p>

            <h3>Binnenkort beschikbaar</h3>
            <p>
                Het Atheneum Portaal wordt verder uitgebreid met:
            </p>

            <ul>
                <li>
                    <strong>EMA's:</strong> digitaal aanvragen,
                    goedkeuren en opvolgen van extra-murosactiviteiten.
                </li>
                <li>
                    <strong>Onboarding personeel:</strong>
                    nieuwe personeelsleden digitaal registreren
                    en hun opstart administratief begeleiden.
                </li>
                <li>
                    <strong>Nascholingen:</strong> aanvragen,
                    goedkeuren en beheren van nascholingen.
                </li>
            </ul>

            <p>
                Zo groeit het Atheneum Portaal uit tot één centrale
                digitale werkomgeving voor onze school.
            </p>
        </div>
    </main>
@endsection
