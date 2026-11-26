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
                <p>Beheer boeken, fysieke exemplaren, uitleningen, Smartschool-berichten en QR-etiketten.</p>
            </div>
            <span class="portal-library-status">Catalogus en uitleningen</span>
        </div>

        <div class="portal-library-feature-grid" aria-label="Bibliotheekfuncties">
            <a href="{{ route('bibliotheek.books.index') }}" class="portal-library-feature portal-library-feature-active">
                <span class="portal-library-feature-icon" aria-hidden="true">▤</span>
                <h2>Boekencatalogus</h2>
                <p>Boeken registreren via ISBN, met titel, auteur, omslag en meerdere exemplaren.</p>
                <span class="portal-library-feature-state">Open de catalogus →</span>
            </a>
            @if(app(\App\Services\LibraryAccess::class)->has(request(), 'bibliotheek.books.view'))
                @php
                    $today = now()->toDateString();
                    $todayCopies = \App\Models\LibraryCopy::whereDate('created_at', $today)->count();
                    $todayBooks = \App\Models\LibraryBook::whereDate('created_at', $today)->count();
                    $todayUnprinted = \App\Models\LibraryCopy::whereDate('created_at', $today)->whereNull('qr_printed_at')->count();
                @endphp
                <a href="{{ route('bibliotheek.books.qr-labels', ['today' => 1, 'qr_status' => 'unprinted', 'sort' => 'registration']) }}" class="portal-library-feature portal-library-feature-active">
                    <span class="portal-library-feature-icon" aria-hidden="true">▥</span>
                    <h2>Vandaag geregistreerd</h2>
                    <p><strong>{{ $todayCopies }}</strong> exemplaren<br>
                       <strong>{{ $todayBooks }}</strong> nieuwe titels<br>
                       <strong>{{ $todayUnprinted }}</strong> etiketten nog niet bevestigd</p>
                    <span class="portal-library-feature-state">Open QR-overzicht van vandaag →</span>
                </a>
            @endif
            <a href="{{ route('bibliotheek.loans.create') }}" class="portal-library-feature portal-library-feature-active">
                <span class="portal-library-feature-icon" aria-hidden="true">↗</span>
                <h2>Boek uitlenen</h2>
                <p>Scan een exemplaar of zoek op titel/ISBN. Leen uit aan leerlingen of personeel.</p>
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
            @if(app(\App\Services\LibraryAccess::class)->has(request(), 'bibliotheek.messages.manage'))
                <a href="{{ route('bibliotheek.messages.index') }}" class="portal-library-feature portal-library-feature-active">
                    <span class="portal-library-feature-icon" aria-hidden="true">✉</span>
                    <h2>Smartschool-berichten</h2>
                    <p>Beheer bevestigingen bij uitlenen en terugbrengen, automatische herinneringen en de afzender bib.</p>
                    <span class="portal-library-feature-state">Berichten instellen →</span>
                </a>
            @endif
            @if(app(\App\Services\LibraryAccess::class)->has(request(), 'bibliotheek.books.view'))
                <a href="{{ route('bibliotheek.books.qr-labels') }}" class="portal-library-feature portal-library-feature-active">
                    <span class="portal-library-feature-icon" aria-hidden="true">▦</span>
                    <h2>QR-codes en etiketten</h2>
                    <p>Selecteer exemplaren, download QR-codes of druk 21 etiketten per A4-vel af.</p>
                    <span class="portal-library-feature-state">QR-codes maken →</span>
                </a>
            @endif
            @if(app(\App\Services\LibraryAccess::class)->has(request(), 'bibliotheek.loans.view'))
                <a href="{{ route('bibliotheek.reports.index') }}" class="portal-library-feature portal-library-feature-active">
                    <span class="portal-library-feature-icon" aria-hidden="true">▥</span>
                    <h2>Rapportages</h2>
                    <p>Voorraad, uitleningen per periode, populaire boeken, QR-status en export naar CSV.</p>
                    <span class="portal-library-feature-state">Rapportages bekijken →</span>
                </a>
            @endif
            <a href="{{ route('bibliotheek.loans.settings') }}" class="portal-library-feature portal-library-feature-active">
                <span class="portal-library-feature-icon" aria-hidden="true">⚙</span>
                <h2>Uitleeninstellingen</h2>
                <p>Termijnen, aantal uitleningen en verlengingen beheren.</p>
                <span class="portal-library-feature-state">Instellingen openen →</span>
            </a>
        </div>
        <p class="portal-library-notice">Smartschool-berichten vertrekken vanuit account bib. Beheer de teksten en schakel verzending in via de tegel Smartschool-berichten.</p>
    </main>
@endsection
