<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>@yield('title', 'LVS Speelhof – Leerling Meldingssysteem')</title>
    <link rel="stylesheet" href="{{ asset('css/lvs.css') }}">
    <style>
        .groupname {
            font-size: 0.85em;
            font-style: italic;
            color: #666;
            margin-left: 0.25em;
        }
    </style>
</head>
<body>
<div class="app" id="app">
    <header class="app-header">
        <div class="title">
            <a href="/">
                <img class="logo" src="{{ asset('images/logo.png') }}" alt="Atheneum Sint-Truiden — Speelhof" />
            </a>
            <h1>LVS</h1>
        </div>
        @php
            $ss_user = session('ss_user');
        @endphp

        @if($ss_user)
            <!-- Rechts: login info + uitloggen -->
            <div class="user" id="userBox" aria-label="Ingelogde leerkracht">
                <span class="avatar" aria-hidden="" style="display: none;"></span>
                <span class="userline">
                    <small class="muted">Aangemeld via Smartschool als</small><br>
                    <strong id="userName">
                        {{ $ss_user['voornaam'] ?? '' }} {{ $ss_user['naam'] ?? '' }}
                    </strong>
                    @if(!empty($ss_user['groupname']))
                        <span class="groupname">({{ ucwords($ss_user['groupname']) }})</span>
                    @endif
                </span>
                <form action="{{ route('logout') }}" method="get" style="display:inline;">
                    <button id="logoutBtn" type="submit" class="btn-logout">
                        Uitloggen
                    </button>
                </form>

            </div>
        @else
            <!-- Rechts: Smartschool login-knop (afbeelding) -->
            <div style="display:flex; align-items:center;">
                <a href="{{ route('login') }}" style="display:inline-block;">
                    <img src="{{ asset('images/btn_aanmelden_met_smartschool_290x40.png') }}"
                        alt="Aanmelden via Smartschool"
                        style="height:40px; width:auto; display:block;">
                </a>
            </div>
        @endif
    </header>
    {{-- Tweede witte balk met navigatie (alleen bij ingelogde gebruiker) --}}
    @php $ss_user = session('ss_user'); @endphp

    @if($ss_user)
        <div class="sub-header"
            style="
                background:#ffffff;
                border-bottom:1px solid #E5E7EB;
                padding:10px 18px;
                display:flex;
                gap:10px;
            ">
            
            <a href="{{ route('leerlingen.index') }}" class="btn btn-green btn--sm">
                Leerlingen
            </a>

            <a href="{{ url('/ema') }}" class="btn btn-green btn--sm">
                EMA's
            </a>

        </div>
    @endif

    @yield('content')
</div>
<script src="{{ asset('js/lvs.js') }}"></script>
</body>
</html>
