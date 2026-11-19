@extends('layouts.app')
@section('title', 'Leerlingenbeheer · Leerlingdetails')
@section('breadcrumbs')
@include('partials.breadcrumbs', ['items' => [
    ['label' => 'Dashboard', 'url' => route('home')],
    ['label' => 'Beheer', 'url' => route('beheer.users.index')],
    ['label' => 'Leerlingenbeheer', 'url' => route('beheer.students.index')],
    ['label' => trim(($student->voornaam ?? '').' '.($student->naam ?? ''))],
]])
@endsection
@section('content')
<main class="library-page portal-users-page">
    <div class="library-page-head">
        <div>
            <p class="portal-eyebrow">PORTAALBEHEER / LEERLINGDETAILS</p>
            <h1>{{ trim(($student->voornaam ?? '').' '.($student->naam ?? '')) }}</h1>
            <p>Bekijk de synchronisatiegegevens en wijzig de contact- en persoonsgegevens.</p>
        </div>
        <a class="library-button library-button-secondary" href="{{ route('beheer.students.index') }}">Terug naar leerlingenbeheer</a>
    </div>
    @if(session('success'))<div class="library-message" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="library-errors" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <section class="library-form-card">
        <h2>Smartschoolgegevens</h2>
        <p class="portal-users-card-intro">Deze velden worden door de leerlingensynchronisatie beheerd en kunnen hier niet aangepast worden.</p>
        <dl class="lvs-details-grid">
            <div><dt>Smartschool-gebruikersnaam</dt><dd>{{ $student->gebruikersnaam ?: '—' }}</dd></div>
            <div><dt>Klas</dt><dd>{{ $student->klasnaam ?: '—' }}</dd></div>
            <div><dt>Status</dt><dd>{{ (int) $student->active === 1 ? 'Actief' : 'Inactief' }}</dd></div>
            <div><dt>School-ID</dt><dd>{{ $student->schoolid }}</dd></div>
            @if($hasScanCode)<div><dt>QR-scancode</dt><dd>{{ filled($student->scannable_code ?? null) ? 'Beschikbaar' : 'Niet beschikbaar' }}</dd></div>@endif
        </dl>
    </section>

    <section class="library-form-card">
        <h2>Leerlinggegevens bewerken</h2>
        <p class="portal-users-card-intro">Voornaam, familienaam en geboortedatum kunnen door de volgende nachtelijke Smartschool-sync opnieuw worden overschreven. Het e-mailadres wordt bij bestaande leerlingen normaal behouden.</p>
        <form method="post" action="{{ route('beheer.students.update', $student->id) }}" class="portal-sync-form">
            @csrf
            @method('PUT')
            <div class="library-field"><label for="voornaam">Voornaam</label><input id="voornaam" name="voornaam" value="{{ old('voornaam', $student->voornaam) }}" maxlength="120" required></div>
            <div class="library-field"><label for="naam">Familienaam</label><input id="naam" name="naam" value="{{ old('naam', $student->naam) }}" maxlength="120" required></div>
            <div class="library-field"><label for="geboortedatum">Geboortedatum</label><input id="geboortedatum" type="date" name="geboortedatum" value="{{ old('geboortedatum', $student->geboortedatum ? substr((string) $student->geboortedatum, 0, 10) : '') }}"></div>
            <div class="library-field"><label for="email">E-mailadres</label><input id="email" type="email" name="email" maxlength="255" value="{{ old('email', $student->{'e-mailadres'} ?? '') }}"></div>
            <div><button class="library-button library-button-primary" type="submit">Wijzigingen opslaan</button></div>
        </form>
    </section>
</main>
@endsection
