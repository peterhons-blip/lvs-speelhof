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
                <div class="portal-login-platform-brand">
                    <img src="{{ asset('images/atheneum-portaal-logo.png') }}" alt="Atheneum Portaal" class="portal-login-platform-logo">
                </div>
                <p class="portal-eyebrow">CAMPUS SPEELHOF</p>
                <h1 id="login-title" class="portal-login-title">Welkom op het portaal</h1>
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
                <a class="portal-module portal-module-active" href="{{ route('leerlingen.index') }}">
                    <span class="portal-module-icon" aria-hidden="true">◎</span>
                    <strong>LVS</strong>
                    <span>Leerlingen opvolgen, meldingen registreren en leerlinginformatie raadplegen.</span>
                    <span class="portal-module-footer">Open de toepassing <span aria-hidden="true">→</span></span>
                </a>
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
                <span>EMA's, Onboarding personeel en Nascholingen worden later toegevoegd. Je kunt het LVS nu al gebruiken.</span>
            </div>
        </main>
    @endif
@endsection
