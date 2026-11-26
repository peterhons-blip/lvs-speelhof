@extends('layouts.app')
@section('title', 'Bibliotheekinstellingen')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Dashboard', 'url' => route('home')],
        ['label' => 'Bibliotheek', 'url' => route('bibliotheek.index')],
        ['label' => 'Instellingen'],
    ]])
@endsection
@section('content')
<main class="library-page library-form-page">
    <div class="library-page-head">
        <div><p class="portal-eyebrow">BIBLIOTHEEK / BEHEER</p><h1>Uitleeninstellingen</h1>
        <p>Stel de uitleentermijnen en grenzen in. Nieuwe instellingen gelden voor nieuwe uitleningen en toekomstige verlengingen.</p></div>
        <div class="lvs-dashboard-header-actions">
            <a href="{{ route('bibliotheek.loans.escalations.index') }}" class="library-button library-button-secondary">Opvolging beleid</a>
            <a href="{{ route('bibliotheek.loans.index') }}" class="library-button library-button-secondary">Naar uitleningen</a>
        </div>
    </div>
    @if(session('success'))<div class="library-message" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="library-errors" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form action="{{ route('bibliotheek.loans.settings.save') }}" method="post" class="library-form-card library-form-grid">
        @csrf
        @foreach([
            'loan_days' => ['Standaard uitleentermijn (dagen)', 21],
            'max_loans' => ['Maximaal openstaande uitleningen per ontlener', 3],
            'max_extensions' => ['Maximaal aantal verlengingen', 2],
            'extension_days' => ['Extra dagen per verlenging', 21],
        ] as $key => [$label, $default])
            <div class="library-field">
                <label for="{{ $key }}">{{ $label }}</label>
                <input id="{{ $key }}" name="{{ $key }}" type="number" min="1" max="365" required value="{{ old($key, $settings[$key] ?? $default) }}">
            </div>
        @endforeach
        <div class="library-field">
            <label for="reminder_days_before">Herinnering vóór inleverdatum (dagen)</label>
            <input id="reminder_days_before" name="reminder_days_before" type="number" min="0" max="60" required value="{{ old('reminder_days_before', $settings['reminder_days_before'] ?? 3) }}">
            <p class="library-help">Standaard 3 dagen. Vul 0 in om deze herinnering globaal uit te zetten.</p>
        </div>
        <div class="library-field">
            <label for="remind_on_due_date">Bericht op de inleverdatum</label>
            <select id="remind_on_due_date" name="remind_on_due_date">
                <option value="1" @selected(old('remind_on_due_date', $settings['remind_on_due_date'] ?? '1') == '1')>Ja</option>
                <option value="0" @selected(old('remind_on_due_date', $settings['remind_on_due_date'] ?? '1') == '0')>Neen</option>
            </select>
        </div>
        <div class="library-wide">
            <h2>Herinneringen na de inleverdatum</h2>
            <p class="library-help">Alle nieuwe instellingen staan standaard op 0 (uit). Herinneringen vertrekken alleen als Smartschool-berichten ook globaal ingeschakeld zijn.</p>
        </div>
        <div class="library-field">
            <label for="overdue_first_days">Eerste herinnering na X dagen te laat</label>
            <input id="overdue_first_days" name="overdue_first_days" type="number" min="0" max="365" required value="{{ old('overdue_first_days', $settings['overdue_first_days'] ?? 0) }}">
            <p class="library-help">Bijvoorbeeld 3 = de eerste herinnering drie dagen na de inleverdatum. 0 = uit.</p>
        </div>
        <div class="library-field">
            <label for="overdue_repeat_days">Vervolgens om de X dagen opnieuw</label>
            <input id="overdue_repeat_days" name="overdue_repeat_days" type="number" min="0" max="365" required value="{{ old('overdue_repeat_days', $settings['overdue_repeat_days'] ?? 0) }}">
            <p class="library-help">Bijvoorbeeld 7 = elke 7 dagen na de eerste herinnering. 0 = niet herhalen.</p>
        </div>
        <div class="library-field">
            <label for="overdue_escalate_after">Na X verzonden herinneringen: opvolging beleid</label>
            <input id="overdue_escalate_after" name="overdue_escalate_after" type="number" min="0" max="100" required value="{{ old('overdue_escalate_after', $settings['overdue_escalate_after'] ?? 0) }}">
            <p class="library-help">Maakt een openstaande opvolging aan. 0 = uit. Een optioneel beleidssignaal stel je hieronder in.</p>
        </div>
        <div class="library-field">
            <label for="policy_alert_enabled">Smartschool-bericht naar beleid bij escalatie</label>
            <select id="policy_alert_enabled" name="policy_alert_enabled">
                <option value="0" @selected(old('policy_alert_enabled', $settings['policy_alert_enabled'] ?? '0') == '0')>Uitgeschakeld</option>
                <option value="1" @selected(old('policy_alert_enabled', $settings['policy_alert_enabled'] ?? '0') == '1')>Ingeschakeld</option>
            </select>
            <p class="library-help">Maximaal één bericht per uitlening. Ook bestaande openstaande opvolgingen kunnen een bericht krijgen na inschakelen.</p>
        </div>
        <div class="library-field">
            <label for="policy_alert_recipient">Ontvanger beleid (actief personeelsaccount)</label>
            <select id="policy_alert_recipient" name="policy_alert_recipient">
                <option value="">Geen ontvanger</option>
                @foreach($staffOptions as $staff)
                    <option value="{{ $staff->smartschool_gebruikersnaam }}" @selected(old('policy_alert_recipient', $settings['policy_alert_recipient'] ?? '') === $staff->smartschool_gebruikersnaam)>{{ $staff->voornaam }} {{ $staff->name }} ({{ $staff->smartschool_gebruikersnaam }})</option>
                @endforeach
            </select>
        </div>
        <div class="library-wide"><button type="submit" class="library-button library-button-primary">Instellingen opslaan</button></div>
    </form>
    <p class="library-help">De tekst en verzending van Smartschool-berichten beheer je via de aparte tegel Smartschool-berichten. Automatische herinneringen lopen via de Laravel scheduler.</p>
</main>
@endsection
