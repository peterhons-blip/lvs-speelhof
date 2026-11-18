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
    <link rel="stylesheet" href="{{ asset('css/portal.css') }}?v=10">
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
                    <a href="{{ route('leerlingen.index') }}" class="portal-nav-link {{ request()->routeIs('leerlingen.*') || request()->routeIs('meldingen.*') ? 'is-current' : '' }}" @if(request()->routeIs('leerlingen.*') || request()->routeIs('meldingen.*')) aria-current="page" @endif>
                        <span class="portal-nav-icon" aria-hidden="true">◎</span> LVS
                    </a>
                    <a href="{{ route('bibliotheek.index') }}" class="portal-nav-link {{ request()->routeIs('bibliotheek.*') ? 'is-current' : '' }}" @if(request()->routeIs('bibliotheek.*')) aria-current="page" @endif>
                        <span class="portal-nav-icon" aria-hidden="true">▤</span> Bibliotheek
                    </a>
                    <span class="portal-sidebar-label portal-sidebar-label-secondary">BINNENKORT</span>
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
