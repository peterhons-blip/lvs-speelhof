@extends('layouts.app')
@section('title', 'Smartschool-testbericht')
@section('breadcrumbs')
@include('partials.breadcrumbs', ['items' => [
    ['label' => 'Dashboard', 'url' => route('home')],
    ['label' => 'Beheer', 'url' => route('beheer.users.index')],
    ['label' => 'Smartschool-testbericht'],
]])
@endsection
@section('content')
<main class="library-page portal-users-page portal-test-message-page">
    <div class="library-page-head">
        <div>
            <p class="portal-eyebrow">PORTAALBEHEER / SMARTSCHOOL</p>
            <h1>Smartschool-testbericht</h1>
            <p>Test rechtstreeks de bestaande Smartschool-webservice met een afzender, ontvanger en eigen bericht.</p>
        </div>
        <a href="{{ route('beheer.users.index') }}" class="library-button library-button-secondary">Terug naar beheer</a>
    </div>
    @if(session('success'))<div class="library-message" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="library-errors" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <section class="library-form-card">
        <div class="portal-users-note portal-test-message-warning">
            <span aria-hidden="true">!</span>
            <p><strong>Dit verstuurt een echt Smartschool-bericht.</strong> Gebruik eerst je eigen account als ontvanger. Het gekozen afzenderaccount moet in Smartschool bestaan en door de webservice gebruikt mogen worden.</p>
        </div>

        <form method="post" action="{{ route('beheer.smartschool-test.send') }}" class="portal-test-message-form" autocomplete="off">
            @csrf
            <div class="portal-test-message-two-col">
                <div class="library-field">
                    <label for="sender">Van (Smartschool-gebruikersnaam) *</label>
                    <input id="sender" name="sender" value="{{ old('sender', $sender) }}" maxlength="120" required placeholder="bijvoorbeeld bib">
                    <p class="library-help">Afzender in Smartschool, bijvoorbeeld <strong>bib</strong>.</p>
                </div>
                <div class="library-field">
                    <label for="recipient">Aan (Smartschool-gebruikersnaam) *</label>
                    <input id="recipient" name="recipient" value="{{ old('recipient', $recipient) }}" maxlength="120" required placeholder="bijvoorbeeld jouw.gebruikersnaam">
                    <p class="library-help">Ontvanger van het echte testbericht, bij voorkeur eerst jezelf.</p>
                </div>
            </div>
            <div class="library-field">
                <label for="subject">Onderwerp *</label>
                <input id="subject" name="subject" value="{{ old('subject', 'Testbericht vanuit Atheneum Portaal') }}" maxlength="200" required>
            </div>
            <div class="library-field">
                <label for="message">Berichttekst *</label>
                <textarea id="message" name="message" rows="8" maxlength="5000" required placeholder="Typ hier de tekst die je in Smartschool wilt versturen.">{{ old('message', "Beste,\n\nDit is een testbericht vanuit het Atheneum Portaal.\n\nMet vriendelijke groeten\nBibliotheek") }}</textarea>
                <p class="library-help">Regelafbrekingen worden behouden. Er worden geen bijlagen of co-accountberichten verstuurd.</p>
            </div>
            <label class="portal-test-message-consent">
                <input type="checkbox" name="confirm_send" value="1" required @checked(old('confirm_send') == '1')>
                <span>Ik bevestig dat ik een <strong>echt Smartschool-bericht</strong> wil versturen naar deze ontvanger.</span>
            </label>
            <div class="portal-role-actions">
                <span class="portal-role-help">Alleen voor ICT-coördinatoren. Geen automatische verzending.</span>
                <button type="submit" class="library-button library-button-primary">Testbericht verzenden</button>
            </div>
        </form>
    </section>
</main>
@endsection
