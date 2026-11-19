@extends('layouts.app')

@section('title', 'Bibliotheek')

@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Dashboard', 'url' => route('home')],
        ['label' => 'Bibliotheek'],
    ]])
@endsection

@section('content')
    <main class="portal-library" aria-labelledby="library-title">
        <div class="portal-library-heading">
            <div>
                <p class="portal-eyebrow">ATHENEUM PORTAAL</p>
                <h1 id="library-title">Bibliotheek</h1>
                <p>Beheer boekencatalogus, fysieke exemplaren en uitleningen. Automatische herinneringen volgen later.</p>
            </div>
            <span class="portal-library-status">Catalogus en uitleningen</span>
        </div>

        <div class="portal-library-feature-grid" aria-label="Geplande bibliotheekfuncties">
            <a href="{{ route('bibliotheek.books.index') }}" class="portal-library-feature portal-library-feature-active">
                <span class="portal-library-feature-icon" aria-hidden="true">▤</span>
                <h2>Boekencatalogus</h2>
                <p>Boeken registreren via ISBN, met titel, auteur, omslag en meerdere exemplaren.</p>
                <span class="portal-library-feature-state">Open de catalogus →</span>
            </a>
            <a href="{{ route('bibliotheek.loans.create') }}" class="portal-library-feature portal-library-feature-active">
                <span class="portal-library-feature-icon" aria-hidden="true">↗</span>
                <h2>Boek uitlenen</h2>
                <p>Scan een exemplaar en koppel het aan een actieve leerling.</p>
                <span class="portal-library-feature-state">Start een uitlening →</span>
            </a>
            <a href="{{ route('bibliotheek.loans.index') }}" class="portal-library-feature portal-library-feature-active">
                <span class="portal-library-feature-icon" aria-hidden="true">↩</span>
                <h2>Inleveren en verlengen</h2>
                <p>Openstaande uitleningen verwerken, inleveren en verlengen.</p>
                <span class="portal-library-feature-state">Open uitleningen →</span>
            </a>
            <a href="{{ route('bibliotheek.loans.index', ['filter' => 'overdue']) }}" class="portal-library-feature portal-library-feature-active">
                <span class="portal-library-feature-icon" aria-hidden="true">◎</span>
                <h2>Achterstallige uitleningen</h2>
                <p>Bekijk vervallen inleverdatums en alle openstaande uitleningen.</p>
                <span class="portal-library-feature-state">Bekijk te late boeken →</span>
            </a>
            <section class="portal-library-feature">
                <span class="portal-library-feature-icon" aria-hidden="true">✉</span>
                <h2>Smartschool-berichten</h2>
                <p>Automatische berichten bij uitleen en herinneringen via een aparte bibliotheekafzender.</p>
                <span class="portal-library-feature-state">Binnenkort</span>
            </section>
            <a href="{{ route('bibliotheek.loans.settings') }}" class="portal-library-feature portal-library-feature-active">
                <span class="portal-library-feature-icon" aria-hidden="true">⚙</span>
                <h2>Uitleeninstellingen</h2>
                <p>Termijnen, aantal uitleningen en verlengingen beheren.</p>
                <span class="portal-library-feature-state">Instellingen openen →</span>
            </a>
        </div>
        <p class="portal-library-notice">De uitleenmodule verwerkt nu uitleningen per exemplaar. Automatische Smartschool-herinneringen blijven uitgeschakeld totdat ze afzonderlijk getest zijn.</p>
    </main>
@endsection
