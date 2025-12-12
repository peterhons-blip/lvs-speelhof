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
            <h2>Welkom!</h2>

            <p style="
                margin: 0 0 18px;
                font-size: 0.97rem;
                color: #555;
                line-height: 1.55;
            ">
                Het Leerlingvolgsysteem (LVS) van GO! Atheneum Sint-Truiden, campus Speelhof,
                ondersteunt leerkrachten bij het opvolgen en begeleiden van leerlingen.
                Je kan hiermee eenvoudig <strong>leerlingen opzoeken</strong>,
                <strong>gedragsmeldingen registreren en opvolgen</strong>
                en <strong>belangrijke leerlinginformatie raadplegen</strong>.
                <br><br>
                Daarnaast kunnen leerkrachten via het LVS ook <strong>EMA’s (extra-murosactiviteiten)</strong>
                digitaal aanvragen en opvolgen. De goedkeuringsflow gebeurt centraal,
                waardoor aanvragen sneller en overzichtelijker verwerkt kunnen worden.
                <br><br>
                Het LVS automatiseert bovendien enkele administratieve en privacy-gebonden processen,
                zoals het <strong>automatisch informeren van leerlingen</strong> en het
                <strong>uitschakelen van ouderaccounts wanneer een leerling 18 wordt</strong>,
                conform de geldende regelgeving.
            </p>
        </div>
    </main>
@endsection
