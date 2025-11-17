@extends('layouts.app')

@section('title', 'Pagina niet gevonden')

@section('content')
    <main class="screen">
        <div class="card">
            <h2>Oeps — pagina niet gevonden</h2>
            <p class="error-text">
                De opgevraagde pagina of leerling kon niet worden gevonden.<br>
                Mogelijk bestaat deze niet meer of heb je een verkeerd adres ingevoerd.
            </p>

            <div class="card-footer" style="justify-content: flex-end;">
                <a href="{{ url('/leerlingen') }}" class="back-btn">← Terug naar leerlingenlijst</a>
            </div>
        </div>
    </main>
@endsection
