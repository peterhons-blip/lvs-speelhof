@extends('layouts.app')

@section('title', 'Bibliotheek')

@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Dashboard', 'route' => 'home'],
        ['label' => 'Bibliotheek'],
    ]])
@endsection

@section('content')
    <main class="portal-library" aria-labelledby="library-title">
        <div class="portal-library-heading">
            <div>
                <p class="portal-eyebrow">ATHENEUM PORTAAL</p>
                <h1 id="library-title">Bibliotheek</h1>
                <p>Beheer binnenkort alle boeken, uitleenbeurten en herinneringen vanuit één centrale omgeving.</p>
            </div>
            <span class="portal-library-status">In voorbereiding</span>
        </div>

        <div class="portal-library-feature-grid" aria-label="Geplande bibliotheekfuncties">
            <section class="portal-library-feature">
                <span class="portal-library-feature-icon" aria-hidden="true">▤</span>
                <h2>Boekencatalogus</h2>
                <p>Boeken registreren via ISBN, met titel, auteur, omslag en meerdere exemplaren.</p>
                <span class="portal-library-feature-state">Binnenkort</span>
            </section>
            <section class="portal-library-feature">
                <span class="portal-library-feature-icon" aria-hidden="true">↗</span>
                <h2>Boek uitlenen</h2>
                <p>Leerling en boek koppelen, uitleentermijn instellen en uitleenbewijs registreren.</p>
                <span class="portal-library-feature-state">Binnenkort</span>
            </section>
            <section class="portal-library-feature">
                <span class="portal-library-feature-icon" aria-hidden="true">↩</span>
                <h2>Inleveren en verlengen</h2>
                <p>Teruggebrachte boeken verwerken, verlengen en de beschikbaarheid opvolgen.</p>
                <span class="portal-library-feature-state">Binnenkort</span>
            </section>
            <section class="portal-library-feature">
                <span class="portal-library-feature-icon" aria-hidden="true">◎</span>
                <h2>Uitleningen opvolgen</h2>
                <p>Een overzicht van actieve uitleningen, vervaldatums en achterstallige boeken.</p>
                <span class="portal-library-feature-state">Binnenkort</span>
            </section>
            <section class="portal-library-feature">
                <span class="portal-library-feature-icon" aria-hidden="true">✉</span>
                <h2>Smartschool-berichten</h2>
                <p>Automatische berichten bij uitleen en herinneringen via een aparte bibliotheekafzender.</p>
                <span class="portal-library-feature-state">Binnenkort</span>
            </section>
            <section class="portal-library-feature">
                <span class="portal-library-feature-icon" aria-hidden="true">⚙</span>
                <h2>Instellingen</h2>
                <p>Uitleentermijnen, herinneringsmomenten, rechten en bibliotheekbeheer instellen.</p>
                <span class="portal-library-feature-state">Binnenkort</span>
            </section>
        </div>
        <p class="portal-library-notice">Dit is voorlopig een startpagina. Er worden nog geen boeken, uitleningen of Smartschool-berichten verwerkt.</p>
    </main>
@endsection
