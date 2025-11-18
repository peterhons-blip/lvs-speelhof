<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Speelhof — Leerling kiezen</title>
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
            <a href="/leerlingen">
                <img class="logo" src="{{ asset('images/logo.png') }}" alt="Atheneum Sint-Truiden — Speelhof" />
            </a>
            <h1>Leerling Meldingssysteem</h1>
        </div>

        <!-- Rechts: login info + uitloggen -->
        <div class="user" id="userBox" aria-label="Ingelogde leerkracht">
            <span class="avatar" aria-hidden="" style="display: none;"></span>
            <span class="userline">
                        <small class="muted">Aangemeld via Smartschool als</small><br>
                        <strong id="userName">{{ $ss_user['voornaam'] ?? '' }} {{ $ss_user['naam'] ?? '' }}</strong><span class="groupname">({{ ucwords($ss_user['groupname']) }})</span>
                    </span>
            <button id="logoutBtn" class="btn-logout" type="button">Uitloggen</button>
        </div>
    </header>
    @yield('content')
</div>
<script src="{{ asset('js/lvs.js') }}"></script>
</body>
</html>
