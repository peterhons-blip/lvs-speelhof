@extends('layouts.app')

@section('title', 'Leerlingen')

@section('content')
    <main class="screen">
        <div class="card">
            <h2>{{ $leerling->voornaam }} {{ $leerling->achternaam }}</h2>
            <p><b>Klas:</b> {{ optional($leerling->klas)->klasnaam ?? '—' }}</p>
            <p><b>Gebruikersnaam:</b> {{ $leerling->gebruikersnaam }}</p>
            <p><b>Email:</b> {{ $leerling->email }}</p>
            <p><b>Geboortedatum:</b> {{ optional($leerling->geboortedatum)->format('d/m/Y') ?? '—' }}</p>
            <p><b>Leeftijd:</b> {{ optional($leerling->geboortedatum)->age ?? '—' }} jaar</p>

            <div class="card-footer">
                <a href="{{ url('/meldingen/add/' . $leerling->id) }}" class="btn btn-orange btn--sm">
                    Maak melding
                </a>
            </div>
        </div>

        {{-- Bestaande leerling-info hier boven --}}

        {{-- Meldingenoverzicht --}}
        <div class="card" style="margin-top: 20px;">
            <h2>Meldingen voor deze leerling</h2>

            @if($meldingen->isEmpty())
                <p class="muted">Nog geen meldingen gevonden.</p>
            @else
                <div class="meldingen-list">
                    @foreach($meldingen as $melding)
                        <div class="melding-row">
                            <div class="melding-meta">
                                <div class="melding-datum">
                                    {{ optional($melding->created_at)->format('d/m/Y H:i') }}
                                </div>
                                <div class="melding-badges">
                            <span class="badge badge-cat">
                                {{ optional(optional($melding->soort)->categorie)->naam ?? '—' }}
                            </span>
                                    <span class="badge badge-soort">
                                {{ optional($melding->soort)->naam ?? '—' }}
                            </span>
                                </div>
                            </div>
                            <div class="melding-comment">
                                {{ $melding->comment }}
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>


        <br>
        <a href="{{ url('/leerlingen') }}" class="back-btn">← Terug naar lijst</a>
    </main>
@endsection
