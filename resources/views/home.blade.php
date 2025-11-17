<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>LVS Speelhof – Welkom</title>
    <style>
        :root{
            --brand-bg:#FFF7D1;
            --btn-green:#22C55E;
            --btn-orange:#F97316;
            --ink:#0b132b;
            --border:#E5E7EB;
        }
        *{box-sizing:border-box}
        html,body{height:100%}
        body{
            margin:0;
            font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,Helvetica Neue,Arial;
            color:var(--ink);
            background:var(--brand-bg);
        }
        .app{max-width:860px;margin:0 auto;min-height:100%;display:flex;flex-direction:column}
        .app-header{
            position:sticky;top:0;background:rgba(255,255,255,.9);
            backdrop-filter:saturate(140%) blur(6px);
            border-bottom:1px solid var(--border);
            padding:14px 18px;display:flex;align-items:center;justify-content:space-between;gap:12px;z-index:10
        }
        .title{display:flex;align-items:center;gap:12px}
        .app-header h1{margin:0;font-size:clamp(1.2rem,2.4vw,1.6rem)}
        .logo{height:40px;width:auto}
        .screen{padding:24px 18px;display:flex;justify-content:center}
        .card{
            background:#fff;border:1px solid var(--border);border-radius:20px;
            padding:22px;box-shadow:0 6px 18px rgba(0,0,0,.08);max-width:none;width:100%;
        }
        .card h2{margin:.2em 0 .6em;font-size:1.6rem}
        .lead{font-size:1.05rem;margin:0 0 14px}
        .btn{
            display:inline-block;padding:12px 18px;border-radius:12px;border:none;
            text-decoration:none;font-weight:800;cursor:pointer;box-shadow:0 6px 18px rgba(0,0,0,.08);
            transition:background .2s, transform .02s ease;letter-spacing:.2px
        }
        .btn:active{transform:scale(.995)}
        .btn-green{background:var(--btn-green);color:#fff}
        .btn-green:hover{background:#16A34A}
        .btn-outline{
            background:transparent;color:var(--ink);border:1px solid var(--border)
        }
        .alert{
            margin:0 0 14px;padding:12px 14px;border-radius:12px;border:1px solid #fecaca;
            background:#fee2e2;color:#991b1b;font-weight:600
        }
        .actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px}
        .muted{color:#475569}
    </style>
</head>
<body>
<div class="app">
    <header class="app-header">
        <div class="title">
            <img class="logo" src="{{ asset('images/logo.png') }}" alt="Atheneum Sint-Truiden — Speelhof" />
            <h1>LVS Speelhof</h1>
        </div>
    </header>

    <main class="screen">
        <div class="card">
            <h2>Welkom!</h2>

            {{-- Foutmelding uit sessie of uit querystring tonen --}}
            @if(session('error'))
                <div class="alert">{{ session('error') }}</div>
            @elseif(request('error'))
                <div class="alert">Login mislukt: {{ Str::of(request('error'))->limit(120) }}</div>
            @endif

            <p class="lead">
                Dit is het Leerlingvolgsysteem (LVS) van GO! Atheneum Sint-Truiden campus Speelhof 2de-3de graad SO.
                Leerkrachten kunnen hier eenvoudig <span class="muted">leerlingen zoeken</span> en
                <span class="muted">gedragsmeldingen</span> registreren en opvolgen.
            </p>

            @php
                $ss = session('ss_user');
            @endphp

            @if($ss)
                <p class="muted">
                    Aangemeld via Smartschool als
                    <strong>{{ trim(($ss['voornaam'] ?? '') . ' ' . ($ss['naam'] ?? '')) }}</strong>
                    @if(!empty($ss['groupname']))
                        <span class="groupname">({{ ucwords($ss['groupname']) }})</span>
                    @endif
                    .
                </p>

                <div style="margin-top:12px; display:flex; gap:10px; flex-wrap:wrap;">
                    <a href="{{ route('leerlingen.index') }}" class="btn btn-green btn--sm">→ Ga naar leerlingen</a>
                    <form action="{{ route('logout') }}" method="get" style="display:inline;">
                        <button type="submit" class="btn btn-red btn--sm">Uitloggen</button>
                    </form>
                </div>
            @else
                <div style="margin-top:12px;">
                    <a href="{{ route('login') }}" class="btn btn-green btn--sm">Aanmelden via Smartschool</a>
                </div>
            @endif



        </div>
    </main>
</div>
</body>
</html>
