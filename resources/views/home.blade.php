@extends('layouts.app')

@section('title', session('ss_user') ? 'Dashboard' : 'Aanmelden')

@section('content')
    @if(session('error'))
        <div class="portal-alert" role="alert">
            <strong>Aanmelden niet gelukt</strong>
            <p>{{ session('error') }}</p>
        </div>
    @endif

    @if(!session('ss_user'))
        <main class="portal-login" aria-labelledby="login-title">
            <section class="portal-login-panel">
                <img class="portal-login-logo" src="{{ asset('images/logo.jpg') }}" alt="GO! Atheneum Sint-Truiden">
                <div class="portal-accent-line" aria-hidden="true"></div>
                <p class="portal-eyebrow">CAMPUS SPEELHOF</p>
                <h1 id="login-title">Atheneum Portaal</h1>
                <p class="portal-login-lead">Eén centrale plek voor leerlingenbegeleiding, aanvragen en schooladministratie.</p>
                <a class="portal-login-cta" href="{{ route('login') }}">
                    <span class="portal-smartschool-mark" aria-hidden="true">S</span>
                    Aanmelden via Smartschool
                    <span aria-hidden="true">→</span>
                </a>
                <p class="portal-login-help">Meld je aan met je Smartschool-account van GO! Atheneum Sint-Truiden.</p>
            </section>
            <aside class="portal-login-art" aria-hidden="true">
                <div class="portal-art-orbit portal-art-orbit-one"></div>
                <div class="portal-art-orbit portal-art-orbit-two"></div>
                <div class="portal-art-building">
                    <div class="portal-art-roof"></div>
                    <div class="portal-art-windows"></div>
                </div>
                <div class="portal-art-label">Samen werken aan sterke schoolorganisatie.</div>
            </aside>
        </main>
        <footer class="portal-public-footer">GO! Atheneum Sint-Truiden · Campus Speelhof</footer>
    @else
        <main class="portal-dashboard" aria-labelledby="dashboard-title">
            <div class="portal-dashboard-intro">
                <div>
                    <p class="portal-eyebrow">MIJN WERKOMGEVING</p>
                    <h1 id="dashboard-title">Welkom, {{ session('ss_user.voornaam', 'collega') }}!</h1>
                    <p>Welkom in het Atheneum Portaal. Beheer leerlinginformatie, aanvragen en schooladministratie vanuit één centrale omgeving.</p>
                </div>
                <div class="portal-dashboard-brand" aria-hidden="true">
                    <img src="{{ asset('images/atheneum-portaal-icon.png') }}" alt="" class="portal-dashboard-brand-image">
                </div>
            </div>

            <h2 class="portal-section-title">Toepassingen</h2>
            <div class="portal-module-grid">
                @if(app(\App\Services\LibraryAccess::class)->has(request(), 'lvs.dashboard.view'))
                <a class="portal-module portal-module-active" href="{{ route('lvs.dashboard') }}">
                    <span class="portal-module-icon" aria-hidden="true">◎</span>
                    <strong>LVS</strong>
                    <span>Leerlingen opvolgen, meldingen registreren en leerlinginformatie raadplegen.</span>
                    <div class="portal-module-stats" aria-label="LVS-statistieken">
                        <div class="portal-module-stat"><span>Actieve leerlingen</span><strong>{{ isset($lvs['leerlingen']) ? number_format($lvs['leerlingen'], 0, ',', '.') : '—' }}</strong></div>
                        <div class="portal-module-stat"><span>Meldingen totaal</span><strong>{{ isset($lvs['meldingen']) ? number_format($lvs['meldingen'], 0, ',', '.') : '—' }}</strong></div>
                        <div class="portal-module-stat"><span>Meldingen deze maand</span><strong>{{ isset($lvs['meldingen_maand']) ? number_format($lvs['meldingen_maand'], 0, ',', '.') : '—' }}</strong></div>
                        <div class="portal-module-stat"><span>Vandaag jarig</span><strong>{{ isset($lvs['jarig_vandaag']) ? number_format($lvs['jarig_vandaag'], 0, ',', '.') : '—' }}</strong></div>
                    </div>
                    <span class="portal-module-footer">Open de toepassing <span aria-hidden="true">→</span></span>
                </a>
                @endif
                <a class="portal-module portal-module-active" href="{{ route('bibliotheek.index') }}">
                    <span class="portal-module-icon" aria-hidden="true">▤</span>
                    <strong>Bibliotheek</strong>
                    <span>Boeken, exemplaren, uitleningen, herinneringen, QR-codes en rapportages beheren.</span>
                    <div class="portal-module-stats" aria-label="Bibliotheekstatistieken">
                        <div class="portal-module-stat"><span>Boektitels</span><strong>{{ isset($bibliotheek['boeken']) ? number_format($bibliotheek['boeken'], 0, ',', '.') : '—' }}</strong></div>
                        <div class="portal-module-stat"><span>Exemplaren</span><strong>{{ isset($bibliotheek['exemplaren']) ? number_format($bibliotheek['exemplaren'], 0, ',', '.') : '—' }}</strong></div>
                        <div class="portal-module-stat"><span>Beschikbaar</span><strong>{{ isset($bibliotheek['beschikbaar']) ? number_format($bibliotheek['beschikbaar'], 0, ',', '.') : '—' }}</strong></div>
                        <div class="portal-module-stat"><span>Actieve uitleningen</span><strong>{{ isset($bibliotheek['uitleningen']) ? number_format($bibliotheek['uitleningen'], 0, ',', '.') : '—' }}</strong></div>
                    </div>
                    @if(($bibliotheek['te_laat'] ?? 0) > 0)
                        <span class="portal-module-alert portal-module-alert-danger">⚠ {{ $bibliotheek['te_laat'] }} uitlening(en) te laat</span>
                    @endif
                    @if(($bibliotheek['beschadigd'] ?? 0) > 0)
                        <span class="portal-module-alert portal-module-alert-danger">⚠ {{ $bibliotheek['beschadigd'] }} beschadigde exemplaren</span>
                    @endif
                    @if(($bibliotheek['imports_wachten'] ?? 0) > 0)
                        <span class="portal-module-alert portal-module-alert-warning">⏳ {{ $bibliotheek['imports_wachten'] }} ISBN('s) wachten op verwerking</span>
                    @endif
                    @if(!isset($bibliotheek['uitleningen']))
                        <span class="portal-module-hint">Uitleningen en achterstallige boeken: binnenkort beschikbaar</span>
                    @endif
                    <span class="portal-module-footer">Bekijk de module <span aria-hidden="true">→</span></span>
                </a>
                <div class="portal-module portal-module-disabled" aria-disabled="true">
                    <span class="portal-module-icon" aria-hidden="true">▣</span><span class="portal-module-status">Binnenkort</span>
                    <strong>Aanvraag bestelling</strong>
                    <span>Bestellingen aanvragen, laten goedkeuren en opvolgen.</span>
                </div>
                <div class="portal-module portal-module-disabled" aria-disabled="true">
                    <span class="portal-module-icon" aria-hidden="true">◈</span><span class="portal-module-status">Binnenkort</span>
                    <strong>EDBI-instrument</strong>
                    <span>Toekomstige module voor het EDBI-instrument.</span>
                </div>
                <div class="portal-module portal-module-disabled" aria-disabled="true">
                    <span class="portal-module-icon" aria-hidden="true">▦</span><span class="portal-module-status">Binnenkort</span>
                    <strong>EMA's</strong>
                    <span>Extra-murosactiviteiten aanvragen, goedkeuren en opvolgen.</span>
                </div>
                <div class="portal-module portal-module-disabled" aria-disabled="true">
                    <span class="portal-module-icon" aria-hidden="true">⊕</span><span class="portal-module-status">Binnenkort</span>
                    <strong>Onboarding personeel</strong>
                    <span>Nieuwe personeelsleden registreren en hun opstart begeleiden.</span>
                </div>
                <div class="portal-module portal-module-disabled" aria-disabled="true">
                    <span class="portal-module-icon" aria-hidden="true">◇</span><span class="portal-module-status">Binnenkort</span>
                    <strong>Nascholingen</strong>
                    <span>Nascholingen aanvragen, opvolgen en beheren.</span>
                </div>
            </div>

            <div class="portal-information">
                <strong>Binnenkort beschikbaar</strong>
                <span>Aanvraag bestelling, EDBI-instrument, EMA's, Onboarding personeel en Nascholingen worden later toegevoegd.</span>
            </div>
        </main>
    @endif
@endsection
