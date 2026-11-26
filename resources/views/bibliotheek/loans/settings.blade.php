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
        <a href="{{ route('bibliotheek.loans.index') }}" class="library-button library-button-secondary">Naar uitleningen</a>
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
        <div class="library-wide library-reminder-heading">
            <h2>Herinneringen na de inleverdatum</h2>
            <p class="library-help">Bepaal wanneer een ontlener voor het eerst een extra herinnering ontvangt en hoe vaak die herhaald wordt. Deze functie staat standaard UIT en vereist ook dat Smartschool-berichten aanstaan.</p>
        </div>
        <div class="library-field">
            <label for="overdue_reminders_enabled">Herinneringen na vervaldatum</label>
            <select id="overdue_reminders_enabled" name="overdue_reminders_enabled">
                <option value="0" @selected(old('overdue_reminders_enabled', $settings['overdue_reminders_enabled'] ?? '0') == '0')>Uitgeschakeld</option>
                <option value="1" @selected(old('overdue_reminders_enabled', $settings['overdue_reminders_enabled'] ?? '0') == '1')>Ingeschakeld</option>
            </select>
        </div>
        <div class="library-field">
            <label for="overdue_first_days">Eerste extra herinnering na (dagen)</label>
            <input type="number" id="overdue_first_days" name="overdue_first_days" min="1" max="90" required
                value="{{ old('overdue_first_days', $settings['overdue_first_days'] ?? 3) }}">
        </div>
        <div class="library-field">
            <label for="overdue_repeat_days">Daarna elke X dagen opnieuw</label>
            <input type="number" id="overdue_repeat_days" name="overdue_repeat_days" min="1" max="90" required
                value="{{ old('overdue_repeat_days', $settings['overdue_repeat_days'] ?? 7) }}">
        </div>
        <div class="library-wide library-reminder-heading">
            <h2>Escalatie naar beleid</h2>
            <p class="library-help">Verstuur maximaal één Smartschool-overzicht naar het geselecteerde beleidsaccount zodra voldoende herinneringen <strong>succesvol verzonden</strong> zijn en het boek nog niet is ingeleverd. Er wordt geen automatische sanctie opgelegd.</p>
        </div>
        <div class="library-field">
            <label for="escalation_enabled">Automatische escalatie</label>
            <select id="escalation_enabled" name="escalation_enabled">
                <option value="0" @selected(old('escalation_enabled', $settings['escalation_enabled'] ?? '0') == '0')>Uitgeschakeld</option>
                <option value="1" @selected(old('escalation_enabled', $settings['escalation_enabled'] ?? '0') == '1')>Ingeschakeld</option>
            </select>
        </div>
        <div class="library-field">
            <label for="escalation_after_reminders">Escaleren na hoeveel verzonden herinneringen?</label>
            <input type="number" id="escalation_after_reminders" name="escalation_after_reminders" min="1" max="30" required
                value="{{ old('escalation_after_reminders', $settings['escalation_after_reminders'] ?? 3) }}">
        </div>
        <div class="library-field library-wide">
            <label for="escalation_recipient">Ontvanger beleid / leerlingbegeleiding (Smartschool)</label>
            <select id="escalation_recipient" name="escalation_recipient">
                <option value="">Selecteer een personeelslid</option>
                @foreach($staffOptions as $person)
                    @php($username = (string) $person->smartschool_gebruikersnaam)
                    <option value="{{ $username }}" @selected(old('escalation_recipient', $settings['escalation_recipient'] ?? '') === $username)>
                        {{ trim(($person->voornaam ?? '').' '.($person->name ?? '')) }} ({{ $username }})
                    </option>
                @endforeach
            </select>
            <p class="library-help">Alleen actieve personeelsaccounts. Afzender van de escalatie is <strong>bib</strong>. De ontvanger krijgt boek, ontlener, barcode en inleverdatum te zien.</p>
        </div>
        <div class="library-wide"><button type="submit" class="library-button library-button-primary">Instellingen opslaan</button></div>
    </form>
    <section class="library-form-card" style="margin-top:22px">
        <h2>Recente escalaties naar beleid</h2>
        <p class="library-help">Dit overzicht toont maximaal 30 escalaties. Een mislukte of onzekere Smartschool-verzending wordt om dubbele berichten te voorkomen niet automatisch opnieuw verstuurd. Controleer die handmatig.</p>
        <div class="library-table-wrap"><table class="library-table">
            <thead><tr><th>Datum</th><th>Boek / exemplaar</th><th>Ontvanger</th><th>Status</th></tr></thead>
            <tbody>
                @forelse($recentEscalations as $record)
                    <tr>
                        <td>{{ $record->created_at }}</td>
                        <td>{{ $record->title ?: 'Onbekend boek' }} <small>{{ $record->barcode }}</small></td>
                        <td>{{ $record->recipient }}</td>
                        <td>{{ ['sent'=>'Aangeboden','failed'=>'Mislukt','processing'=>'Onzekere verwerking'][$record->status] ?? $record->status }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4">Nog geen escalaties geregistreerd.</td></tr>
                @endforelse
            </tbody>
        </table></div>
    </section>
    <p class="library-help">De tekst en verzending van Smartschool-berichten beheer je via de aparte tegel Smartschool-berichten. Automatische herinneringen lopen via de Laravel scheduler.</p>
</main>
@endsection
