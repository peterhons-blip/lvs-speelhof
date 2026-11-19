@extends('layouts.app')
@section('title', 'Boek uitlenen')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Dashboard', 'url' => route('home')],
        ['label' => 'Bibliotheek', 'url' => route('bibliotheek.index')],
        ['label' => 'Uitleningen', 'url' => route('bibliotheek.loans.index')],
        ['label' => 'Boek uitlenen'],
    ]])
@endsection
@section('content')
<main class="library-page library-form-page">
    <div class="library-page-head">
        <div><p class="portal-eyebrow">BIBLIOTHEEK / UITLEENBEHEER</p><h1>Boek uitlenen</h1>
            <p>Scan of typ de unieke barcode van het exemplaar en zoek de actieve leerling op gebruikersnaam.</p>
        </div>
        <a class="library-button library-button-secondary" href="{{ route('bibliotheek.loans.index') }}">Terug naar uitleningen</a>
    </div>
    @if($errors->any())<div class="library-errors" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form action="{{ route('bibliotheek.loans.store') }}" method="post" class="library-form-card" autocomplete="off">
        @csrf
        <div class="library-field">
            <label for="loan-barcode">Barcode exemplaar *</label>
            <input id="loan-barcode" name="barcode" value="{{ old('barcode', $barcode) }}" maxlength="60" list="available-copies" placeholder="BIB-..." required autofocus>
            <datalist id="available-copies">
                @foreach($copies as $availableCopy)
                    <option value="{{ $availableCopy->barcode }}">{{ $availableCopy->title }}</option>
                @endforeach
            </datalist>
            @if($copy)
                <p class="library-help">Geselecteerd: {{ $copy->title }} ({{ $copy->status }})</p>
            @endif
            <p class="library-help">Je kunt een USB-barcodelezer gebruiken. De lijst toont maximaal 250 beschikbare exemplaren; andere barcodes kun je rechtstreeks scannen.</p>
        </div>
        <div class="library-field">
            <label for="loan-student-search">Leerling zoeken</label>
            <input id="loan-student-search" type="search" placeholder="Typ minstens 2 letters van een naam of gebruikersnaam" aria-label="Leerling zoeken">
            <div id="loan-student-results" class="library-loan-results" role="status"></div>
        </div>
        <div class="library-field">
            <label for="loan-student">Smartschool-gebruikersnaam leerling *</label>
            <input id="loan-student" name="leerling" value="{{ old('leerling', $username) }}" required maxlength="200" list="active-students" placeholder="Gebruikersnaam">
            <datalist id="active-students">
                @foreach($leerlingen as $student)
                    <option value="{{ $student->gebruikersnaam }}">{{ $student->voornaam }} {{ $student->naam }}</option>
                @endforeach
            </datalist>
            @if($leerling)
                <p class="library-help">Geselecteerd: {{ $leerling->voornaam }} {{ $leerling->naam }}</p>
            @endif
            <p class="library-help">Alleen actieve leerlingen kunnen uitlenen. Zoek hierboven om hun gebruikersnaam in te vullen.</p>
        </div>
        <button type="submit" class="library-button library-button-primary">Uitlening registreren</button>
    </form>
</main>
@endsection
@push('scripts')
<script>
(() => {
    const input = document.getElementById('loan-student-search');
    const results = document.getElementById('loan-student-results');
    const username = document.getElementById('loan-student');
    const endpoint = @json(route('bibliotheek.loans.students'));
    let requestNo = 0;
    input.addEventListener('input', async () => {
        const q = input.value.trim();
        const current = ++requestNo;
        results.replaceChildren();
        if (q.length < 2) return;
        try {
            const response = await fetch(endpoint + '?q=' + encodeURIComponent(q), {headers: {Accept: 'application/json'}});
            if (!response.ok) return;
            const students = await response.json();
            if (current !== requestNo) return;
            if (!students.length) {
                results.textContent = 'Geen actieve leerlingen gevonden.';
                return;
            }
            students.forEach(student => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'library-loan-result';
                button.textContent = student.voornaam + ' ' + student.naam + ' (' + student.gebruikersnaam + ')';
                button.addEventListener('click', () => {
                    username.value = student.gebruikersnaam;
                    input.value = student.voornaam + ' ' + student.naam;
                    results.replaceChildren();
                });
                results.appendChild(button);
            });
        } catch { results.textContent = 'Zoeken is tijdelijk niet beschikbaar. Vul de gebruikersnaam rechtstreeks in.'; }
    });
})();
</script>
@endpush
