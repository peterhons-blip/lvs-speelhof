@extends('layouts.app')

@section('title', 'Welkom')

@section('content')
    <main class="screen">
        <div class="card">
            <h2>Welkom!</h2>

            {{-- Foutmeldingen uit sessie of querystring --}}
            @if(session('error'))
                <div class="alert">{{ session('error') }}</div>
            @elseif(request('error'))
                <div class="alert">Login mislukt: {{ \Illuminate\Support\Str::of(request('error'))->limit(120) }}</div>
            @endif

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
