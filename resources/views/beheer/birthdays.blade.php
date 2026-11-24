@extends('layouts.app')
@section('title', 'Verjaardagsberichten beheren')
@section('breadcrumbs')
@include('partials.breadcrumbs', ['items' => [
    ['label' => 'Dashboard', 'url' => route('home')],
    ['label' => 'Beheer', 'url' => route('beheer.dashboard')],
    ['label' => 'Verjaardagsberichten'],
]])
@endsection
@section('content')
<main class="library-page library-form-page">
    <div class="library-page-head">
        <div>
            <p class="portal-eyebrow">BEHEER / GEAUTOMATISEERDE BERICHTEN</p>
            <h1>Verjaardagsberichten</h1>
            <p>Beheer wie een felicitatie ontvangt, via welk kanaal en welke tekst wordt verstuurd.</p>
        </div>
        <a class="library-button library-button-secondary" href="{{ route('beheer.scheduled-tasks.index') }}">Geplande taken</a>
    </div>
    @if(session('success'))<div class="library-message" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="library-errors" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form method="post" action="{{ route('beheer.birthdays.update') }}" class="library-form">
        @csrf
        <section class="library-form-card">
            <h2>Verzending en ontvangers</h2>
            <div class="library-form-grid">
                @foreach([
                    'enabled' => 'Automatische verjaardagsverwerking',
                    'students_enabled' => 'Leerlingen feliciteren',
                    'staff_enabled' => 'Personeel feliciteren',
                    'smartschool_students' => 'Leerlingen: Smartschool-bericht',
                    'smartschool_staff' => 'Personeel: Smartschool-bericht',
                    'email_students' => 'Leerlingen: e-mail',
                    'email_staff' => 'Personeel: e-mail',
                ] as $key => $label)
                    <div class="library-field">
                        <label for="{{ $key }}">{{ $label }}</label>
                        <select id="{{ $key }}" name="{{ $key }}">
                            <option value="1" @selected(old($key, $settings[$key]) === '1')>Ja</option>
                            <option value="0" @selected(old($key, $settings[$key]) === '0')>Nee</option>
                        </select>
                    </div>
                @endforeach
                <div class="library-field">
                    <label for="school_id">School</label>
                    <select id="school_id" name="school_id">
                        <option value="0" @selected(old('school_id', $settings['school_id']) == 0)>Alle scholen</option>
                        @foreach(\Illuminate\Support\Facades\DB::table('scholen')->orderBy('id')->get(['id','schoolnaam']) as $school)
                            <option value="{{ $school->id }}" @selected(old('school_id', $settings['school_id']) == $school->id)>{{ $school->schoolnaam }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="library-field">
                    <label for="sender">Smartschool-afzender</label>
                    <input type="text" id="sender" name="sender" maxlength="120"
                        value="{{ old('sender', $settings['sender']) }}" placeholder="Leeg = afzender van de school">
                    <p class="library-help">Geef een bestaande Smartschool-gebruikersnaam in, of laat leeg om de schoolafzender te gebruiken.</p>
                </div>
            </div>
            <p class="library-help">E-mail gaat rechtstreeks naar het e-mailadres van de leerling of medewerker in de databank. Er worden geen verjaardagsmails naar ouders of co-accounts gestuurd. Voor 18-jarigen blijft de bestaande afzonderlijke procedure van kracht.</p>
            <p class="library-help"><strong>E-mail staat standaard uit.</strong> Een werkende mailserverconfiguratie is nodig; bij een ontbrekend of ongeldig adres wordt de verzending overgeslagen.</p>
        </section>
        <section class="library-form-card">
            <h2>Tekst voor leerlingen</h2>
            <div class="library-field"><label for="subject_students">Onderwerp</label>
                <input type="text" id="subject_students" name="subject_students" maxlength="200" required value="{{ old('subject_students', $settings['subject_students']) }}"></div>
            <div class="library-field"><label for="body_students">Bericht</label>
                <textarea id="body_students" name="body_students" rows="8" maxlength="4000" required>{{ old('body_students', $settings['body_students']) }}</textarea></div>
        </section>
        <section class="library-form-card">
            <h2>Tekst voor personeel</h2>
            <div class="library-field"><label for="subject_staff">Onderwerp</label>
                <input type="text" id="subject_staff" name="subject_staff" maxlength="200" required value="{{ old('subject_staff', $settings['subject_staff']) }}"></div>
            <div class="library-field"><label for="body_staff">Bericht</label>
                <textarea id="body_staff" name="body_staff" rows="8" maxlength="4000" required>{{ old('body_staff', $settings['body_staff']) }}</textarea></div>
            <p class="library-help">Beschikbare variabelen: <code>{voornaam}</code>, <code>{naam}</code>, <code>{school}</code> en <code>{leeftijd}</code>. Je kunt gewone tekst gebruiken; opmaak in HTML wordt niet uitgevoerd.</p>
        </section>
        <section class="library-form-card">
            <h2>Planning en veiligheid</h2>
            <p>De bestaande taak draait dagelijks om <strong>02.00 uur (Europe/Brussels)</strong>. De verzendtijd wijzig je niet hier, maar in de Laravel-scheduler / Windows Taakplanner. Alleen jarigen van die dag komen in aanmerking.</p>
            <p>Per persoon, datum en kanaal wordt verzending geregistreerd. Ook onzekere of mislukte verzendpogingen worden niet automatisch opnieuw uitgevoerd om dubbele berichten te voorkomen.</p>
            <p><strong>Veilig controleren op de server:</strong> <code>php artisan verjaardagen:check --test</code>. Dit verstuurt niets.</p>
        </section>
        <button type="submit" class="library-button library-button-primary">Verjaardagsinstellingen opslaan</button>
    </form>
    <section class="library-form-card" style="margin-top:20px">
        <h2>Recente verzendingen</h2>
        <div class="library-table-wrap">
            <table class="library-table">
                <thead><tr><th>Datum</th><th>Kanaal</th><th>Status</th><th>Aantal</th></tr></thead>
                <tbody>
                    @forelse($recent as $item)
                        <tr><td>{{ $item->birthday_date }}</td><td>{{ $item->channel }}</td><td>{{ $item->status }}</td><td>{{ $item->total }}</td></tr>
                    @empty
                        <tr><td colspan="4">Nog geen verzendhistoriek geregistreerd.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</main>
@endsection
