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
        <div class="library-field">
            <label for="smartschool_enabled">Smartschool-berichten vanuit bib</label>
            @if(!app(\App\Services\LibraryAccess::class)->has(request(), 'bibliotheek.messages.manage'))
                <input type="hidden" name="smartschool_enabled" value="{{ $settings['smartschool_enabled'] ?? '0' }}">
            @endif
            <select id="smartschool_enabled" name="smartschool_enabled" @disabled(!app(\App\Services\LibraryAccess::class)->has(request(), 'bibliotheek.messages.manage'))>
                <option value="0" @selected(old('smartschool_enabled', $settings['smartschool_enabled'] ?? '0') == '0')>Uitgeschakeld (veilig voor testen)</option>
                <option value="1" @selected(old('smartschool_enabled', $settings['smartschool_enabled'] ?? '0') == '1')>Ingeschakeld</option>
            </select>
            <p class="library-help">Alle bibliotheekberichten vertrekken vanuit Smartschool-account <strong>bib</strong>. Kies <strong>Ingeschakeld</strong> en sla op om de verzending te activeren. Dan worden uitleningen, terugbrengingen, automatische herinneringen en handmatige herinneringen via dit account verzonden (enkel naar het hoofdaccount).</p>
        </div>
        <div class="library-wide"><button type="submit" class="library-button library-button-primary">Instellingen opslaan</button></div>
    </form>
    <p class="library-help">Instellingen opslaan verstuurt zelf geen berichten. Automatische herinneringen lopen via de Laravel scheduler, maximaal één keer per uitleenmoment.</p>
</main>
@endsection
