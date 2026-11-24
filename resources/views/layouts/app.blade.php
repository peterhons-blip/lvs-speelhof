<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Atheneum Portaal') · GO! Atheneum Sint-Truiden</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('atheneum-portaal-favicon.svg') }}?v=1">
    <link rel="shortcut icon" type="image/svg+xml" href="{{ asset('atheneum-portaal-favicon.svg') }}?v=1">
    <link rel="apple-touch-icon" href="{{ asset('images/atheneum-portaal-icon.png') }}">
    <link rel="stylesheet" href="{{ asset('css/lvs.css') }}">
    <link rel="stylesheet" href="{{ asset('css/portal.css') }}?v=34">
    <link rel="stylesheet" href="{{ asset('css/lvs-modern.css') }}?v=4">
</head>
<body class="portal-body">
@php($ss_user = session('ss_user'))
@if($ss_user)
    <div class="app portal-app">
        <header class="portal-topbar">
            <a class="portal-brand" href="{{ route('home') }}" aria-label="Atheneum Portaal, naar dashboard">
                <img src="{{ asset('images/logo.jpg') }}" alt="Atheneum Sint-Truiden" class="portal-brand-logo">
                <span class="portal-brand-divider" aria-hidden="true"></span>
                <span class="portal-brand-platform">
                    <img class="portal-brand-mark" src="{{ asset('images/atheneum-portaal-icon.png') }}" alt="" aria-hidden="true">
                    <span class="portal-brand-title">Atheneum Portaal</span>
                </span>
            </a>
            <div class="portal-account">
                <span class="portal-account-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($ss_user['voornaam'] ?? 'G', 0, 1)) }}</span>
                <span class="portal-account-copy">
                    <strong>{{ trim(($ss_user['voornaam'] ?? '') . ' ' . ($ss_user['naam'] ?? '')) }}</strong>
                    <small>{{ $ss_user['groupname'] ?? 'Personeelslid' }}</small>
                </span>
                <a href="{{ route('logout') }}" class="portal-logout">Uitloggen</a>
            </div>
        </header>

        <div class="portal-workspace">
            <aside class="portal-sidebar" aria-label="Hoofdnavigatie">
                <span class="portal-sidebar-label">WERKOMGEVING</span>
                <nav class="portal-nav" aria-label="Modules">
                    <a href="{{ route('home') }}" class="portal-nav-link {{ request()->routeIs('home') ? 'is-current' : '' }}" @if(request()->routeIs('home')) aria-current="page" @endif>
                        <span class="portal-nav-icon" aria-hidden="true">⌂</span> Dashboard
                    </a>
                    @php($lvsActive = request()->routeIs('leerlingen.*') || request()->routeIs('meldingen.*'))
                    <div class="portal-nav-module">
                        <a href="{{ route('leerlingen.index') }}" class="portal-nav-link {{ $lvsActive ? 'is-current' : '' }}" @if($lvsActive) aria-expanded="true" @endif>
                            <span class="portal-nav-icon" aria-hidden="true">◎</span> LVS
                        </a>
                        @if($lvsActive)
                            <nav class="portal-nav-submenu" aria-label="LVS-subnavigatie">
                                <a href="{{ route('leerlingen.index') }}" class="portal-nav-subitem {{ request()->routeIs('leerlingen.index') || request()->routeIs('leerlingen.show') || request()->routeIs('meldingen.*') ? 'is-active' : '' }}" @if(request()->routeIs('leerlingen.index') || request()->routeIs('leerlingen.show') || request()->routeIs('meldingen.*')) aria-current="page" @endif>Leerlingenoverzicht</a>
                                <a href="{{ route('leerlingen.verjaardagen') }}" class="portal-nav-subitem {{ request()->routeIs('leerlingen.verjaardagen') ? 'is-active' : '' }}" @if(request()->routeIs('leerlingen.verjaardagen')) aria-current="page" @endif>Verjaardagen</a>
                            </nav>
                        @endif
                    </div>
                    @php($bibliotheekActive = request()->routeIs('bibliotheek.*'))
                    <div class="portal-nav-module">
                        <a href="{{ route('bibliotheek.index') }}" class="portal-nav-link {{ $bibliotheekActive ? 'is-current' : '' }}" @if($bibliotheekActive) aria-expanded="true" @endif>
                            <span class="portal-nav-icon" aria-hidden="true">▤</span> Bibliotheek
                        </a>
                        @if($bibliotheekActive)
                            <nav class="portal-nav-submenu" aria-label="Bibliotheek-subnavigatie">
                                <a href="{{ route('bibliotheek.index') }}" class="portal-nav-subitem {{ request()->routeIs('bibliotheek.index') ? 'is-active' : '' }}" @if(request()->routeIs('bibliotheek.index')) aria-current="page" @endif>Overzicht</a>
                                <a href="{{ route('bibliotheek.loans.index') }}" class="portal-nav-subitem {{ request()->routeIs('bibliotheek.loans.index') ? 'is-active' : '' }}" @if(request()->routeIs('bibliotheek.loans.index')) aria-current="page" @endif>Uitleningen</a>
                                @if(app(\App\Services\LibraryAccess::class)->has(request(), 'bibliotheek.loans.view'))
                                <a href="{{ route('bibliotheek.reports.index') }}" class="portal-nav-subitem {{ request()->routeIs('bibliotheek.reports.*') ? 'is-active' : '' }}" @if(request()->routeIs('bibliotheek.reports.*')) aria-current="page" @endif>Rapportages</a>
                                @endif
                                <a href="{{ route('bibliotheek.loans.create') }}" class="portal-nav-subitem {{ request()->routeIs('bibliotheek.loans.create') ? 'is-active' : '' }}" @if(request()->routeIs('bibliotheek.loans.create')) aria-current="page" @endif>Boek uitlenen</a>
                                @if(app(\App\Services\LibraryAccess::class)->has(request(), 'bibliotheek.settings.manage'))
                                <a href="{{ route('bibliotheek.loans.settings') }}" class="portal-nav-subitem {{ request()->routeIs('bibliotheek.loans.settings') ? 'is-active' : '' }}" @if(request()->routeIs('bibliotheek.loans.settings')) aria-current="page" @endif>Uitleeninstellingen</a>
                                @endif
                                @if(app(\App\Services\LibraryAccess::class)->has(request(), 'bibliotheek.messages.manage'))
                                <a href="{{ route('bibliotheek.messages.index') }}" class="portal-nav-subitem {{ request()->routeIs('bibliotheek.messages.*') ? 'is-active' : '' }}" @if(request()->routeIs('bibliotheek.messages.*')) aria-current="page" @endif>Smartschool-berichten</a>
                                @endif
                                <a href="{{ route('bibliotheek.books.index') }}" class="portal-nav-subitem {{ request()->routeIs('bibliotheek.books.index') || request()->routeIs('bibliotheek.books.show') || request()->routeIs('bibliotheek.books.edit') ? 'is-active' : '' }}" @if(request()->routeIs('bibliotheek.books.index') || request()->routeIs('bibliotheek.books.show')) aria-current="page" @endif>Boekencatalogus</a>
                                @if(app(\App\Services\LibraryAccess::class)->has(request(), 'bibliotheek.books.view'))
                                <a href="{{ route('bibliotheek.books.qr-labels') }}" class="portal-nav-subitem {{ request()->routeIs('bibliotheek.books.qr-labels') ? 'is-active' : '' }}" @if(request()->routeIs('bibliotheek.books.qr-labels')) aria-current="page" @endif>QR-codes en etiketten</a>
                                @endif
                                <a href="{{ route('bibliotheek.books.import.create') }}" class="portal-nav-subitem {{ request()->routeIs('bibliotheek.books.import.create') ? 'is-active' : '' }}" @if(request()->routeIs('bibliotheek.books.import.create')) aria-current="page" @endif>ISBN-lijst importeren</a>
                                <a href="{{ route('bibliotheek.books.import.history') }}" class="portal-nav-subitem {{ request()->routeIs('bibliotheek.books.import.history') || request()->routeIs('bibliotheek.books.import.show') ? 'is-active' : '' }}" @if(request()->routeIs('bibliotheek.books.import.history') || request()->routeIs('bibliotheek.books.import.show')) aria-current="page" @endif>Importhistoriek</a>
                                <a href="{{ route('bibliotheek.books.create') }}" class="portal-nav-subitem {{ request()->routeIs('bibliotheek.books.create') ? 'is-active' : '' }}" @if(request()->routeIs('bibliotheek.books.create')) aria-current="page" @endif>Boek toevoegen</a>
                            </nav>
                        @endif
                    </div>
                    @if(app(\App\Services\LibraryAccess::class)->isManager(request()))
                    <div class="portal-nav-module">
                        <a href="{{ route('beheer.dashboard') }}" class="portal-nav-link {{ request()->routeIs('beheer.*') ? 'is-current' : '' }}" @if(request()->routeIs('beheer.*')) aria-expanded="true" @endif><span class="portal-nav-icon" aria-hidden="true">⚙</span> Beheer</a>
                        @if(request()->routeIs('beheer.*'))
                        <nav class="portal-nav-submenu" aria-label="Portaalbeheer">
                            <a href="{{ route('beheer.dashboard') }}" class="portal-nav-subitem {{ request()->routeIs('beheer.dashboard') ? 'is-active' : '' }}">Overzicht</a>
                            @if(app(\App\Services\LibraryAccess::class)->has(request(), 'beheer.staff.manage'))
                            <a href="{{ route('beheer.users.index') }}" class="portal-nav-subitem {{ request()->routeIs('beheer.users.*') ? 'is-active' : '' }}">Personeelsbeheer</a>
                            @endif
                            @if(app(\App\Services\LibraryAccess::class)->has(request(), 'beheer.students.manage'))
                            <a href="{{ route('beheer.students.index') }}" class="portal-nav-subitem {{ request()->routeIs('beheer.students.*') ? 'is-active' : '' }}">Leerlingenbeheer</a>
                            @endif
                            @if(app(\App\Services\LibraryAccess::class)->has(request(), 'beheer.permissions.manage'))
                            <a href="{{ route('beheer.permissions.index') }}" class="portal-nav-subitem {{ request()->routeIs('beheer.permissions.*') ? 'is-active' : '' }}">Rollen en rechten</a>
                            @endif
                            @if(app(\App\Services\LibraryAccess::class)->has(request(), 'beheer.smartschool_test.send'))
                            <a href="{{ route('beheer.smartschool-test.create') }}" class="portal-nav-subitem {{ request()->routeIs('beheer.smartschool-test.*') ? 'is-active' : '' }}">Smartschool-testbericht</a>
                            @endif
                            @if(app(\App\Services\LibraryAccess::class)->has(request(), 'beheer.student_sync.manage'))
                            <a href="{{ route('beheer.smartschool-sync.index') }}" class="portal-nav-subitem {{ request()->routeIs('beheer.smartschool-sync.*') ? 'is-active' : '' }}">Leerlingensynchronisatie</a>
                            @endif
                            @if(app(\App\Services\LibraryAccess::class)->has(request(), 'beheer.staff_sync.manage'))
                            <a href="{{ route('beheer.staff-sync.index') }}" class="portal-nav-subitem {{ request()->routeIs('beheer.staff-sync.*') ? 'is-active' : '' }}">Personeelssynchronisatie</a>
                            @endif
                            @if(app(\App\Services\LibraryAccess::class)->has(request(), 'beheer.birthdays.manage'))
                            <a href="{{ route('beheer.birthdays.index') }}" class="portal-nav-subitem {{ request()->routeIs('beheer.birthdays.*') ? 'is-active' : '' }}" @if(request()->routeIs('beheer.birthdays.*')) aria-current="page" @endif>Verjaardagsberichten</a>
                            @endif
                            @if(app(\App\Services\LibraryAccess::class)->has(request(), 'beheer.scheduled_tasks.view'))
                            <a href="{{ route('beheer.scheduled-tasks.index') }}" class="portal-nav-subitem {{ request()->routeIs('beheer.scheduled-tasks.*') ? 'is-active' : '' }}">Geplande taken</a>
                            @endif
                        </nav>
                        @endif
                    </div>
                    @endif
                    <span class="portal-sidebar-label portal-sidebar-label-secondary">BINNENKORT</span>
                    <span class="portal-nav-link is-disabled" aria-disabled="true"><span class="portal-nav-icon" aria-hidden="true">▣</span> Aanvraag bestelling <span class="portal-nav-lock" aria-hidden="true">⌁</span></span>
                    <span class="portal-nav-link is-disabled" aria-disabled="true"><span class="portal-nav-icon" aria-hidden="true">◈</span> EDBI-instrument <span class="portal-nav-lock" aria-hidden="true">⌁</span></span>
                    <span class="portal-nav-link is-disabled" aria-disabled="true"><span class="portal-nav-icon" aria-hidden="true">▦</span> EMA's <span class="portal-nav-lock" aria-hidden="true">⌁</span></span>
                    <span class="portal-nav-link is-disabled" aria-disabled="true"><span class="portal-nav-icon" aria-hidden="true">⊕</span> Onboarding personeel <span class="portal-nav-lock" aria-hidden="true">⌁</span></span>
                    <span class="portal-nav-link is-disabled" aria-disabled="true"><span class="portal-nav-icon" aria-hidden="true">◇</span> Nascholingen <span class="portal-nav-lock" aria-hidden="true">⌁</span></span>
                </nav>
                <div class="portal-sidebar-foot">GO! Atheneum Sint-Truiden<br><span>Campus Speelhof</span></div>
            </aside>
            <div class="portal-content">
                <div class="portal-page-breadcrumb">
                    @hasSection('breadcrumbs')
                        @yield('breadcrumbs')
                    @else
                        @include('partials.breadcrumbs', ['items' => [
                            ['label' => 'Dashboard'],
                        ]])
                    @endif
                </div>
                @yield('content')
            </div>
        </div>
    </div>
@else
    <div class="app portal-public">
        @yield('content')
    </div>
@endif

{{-- Elke pagina laadt alleen de eigen scripts, na de HTML-inhoud. --}}
@stack('scripts')
</body>
</html>
