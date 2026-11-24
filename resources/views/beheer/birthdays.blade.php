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
            <h2>1. Algemene instellingen</h2>
            <p class="library-help">Deze instellingen gelden voor beide kanalen. De twee verzendkanalen kun je hieronder apart instellen.</p>
            <div class="library-form-grid">
                @foreach([
                    'enabled' => 'Automatische verjaardagsberichten actief',
                    'students_enabled' => 'Leerlingen opnemen',
                    'staff_enabled' => 'Personeelsleden opnemen',
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
                    <label for="school_id">Voor welke school?</label>
                    <select id="school_id" name="school_id">
                        <option value="0" @selected(old('school_id', $settings['school_id']) == 0)>Alle scholen</option>
                        @foreach(\Illuminate\Support\Facades\DB::table('scholen')->orderBy('id')->get(['id','schoolnaam']) as $school)
                            <option value="{{ $school->id }}" @selected(old('school_id', $settings['school_id']) == $school->id)>{{ $school->schoolnaam }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <p class="library-help">De bestaande aparte berichten en co-accountprocedure voor 18-jarige leerlingen blijven behouden. Alleen actieve leerlingen en Smartschool-actieve personeelsleden worden geselecteerd.</p>
        </section>

        <div class="library-birthday-channels">
            <section class="library-form-card">
                <div class="library-birthday-channel-heading"><span aria-hidden="true">✉</span><div>
                    <h2>2. Smartschool-berichten</h2>
                    <p class="library-help">De berichten gaan rechtstreeks naar de Smartschool-gebruikersnaam van de jarige, niet naar ouders of co-accounts.</p>
                </div></div>
                <div class="library-form-grid">
                    @foreach([
                        'smartschool_students' => 'Smartschool-bericht naar jarige leerlingen',
                        'smartschool_staff' => 'Smartschool-bericht naar jarig personeel',
                    ] as $key => $label)
                        <div class="library-field">
                            <label for="{{ $key }}">{{ $label }}</label>
                            <select id="{{ $key }}" name="{{ $key }}">
                                <option value="1" @selected(old($key, $settings[$key]) === '1')>Ja</option>
                                <option value="0" @selected(old($key, $settings[$key]) === '0')>Nee</option>
                            </select>
                        </div>
                    @endforeach
                </div>
                <div class="library-field">
                    <label for="sender">Smartschool-afzender</label>
                    <input type="text" id="sender" name="sender" maxlength="120"
                        value="{{ old('sender', $settings['sender']) }}" placeholder="Automatisch: schoolafzender">
                    <p class="library-help">Leeg = beleid / afzender van de school. Vul anders de Smartschool-gebruikersnaam van de gewenste afzender in.</p>
                </div>
                <div class="library-birthday-explain">
                    <strong>Welke accounts?</strong>
                    <p>Leerlingen: het veld <code>leerlingen.gebruikersnaam</code>. Personeel: <code>users.smartschool_gebruikersnaam</code>.</p>
                </div>
            </section>

            <section class="library-form-card">
                <div class="library-birthday-channel-heading"><span aria-hidden="true">@</span><div>
                    <h2>3. E-mailberichten</h2>
                    <p class="library-help">Een afzonderlijke felicitatie per e-mailadres. E-mail staat standaard uit.</p>
                </div></div>
                <div class="library-form-grid">
                    @foreach([
                        'email_students' => 'E-mail naar jarige leerlingen',
                        'email_staff' => 'E-mail naar jarig personeel',
                    ] as $key => $label)
                        <div class="library-field">
                            <label for="{{ $key }}">{{ $label }}</label>
                            <select id="{{ $key }}" name="{{ $key }}">
                                <option value="1" @selected(old($key, $settings[$key]) === '1')>Ja</option>
                                <option value="0" @selected(old($key, $settings[$key]) === '0')>Nee</option>
                            </select>
                        </div>
                    @endforeach
                </div>
                <div class="library-birthday-explain">
                    <strong>Welke adressen?</strong>
                    <p>Leerlingen: <code>leerlingen.e-mailadres</code>. Personeel: <code>users.email</code>. Ontbrekende en ongeldige adressen worden overgeslagen. Ouders en co-accounts krijgen geen verjaardagsmail.</p>
                    <p><strong>Actieve mailer:</strong> {{ config('mail.default', 'niet ingesteld') }}. Voor echte e-mails moet de mailserver op de server zijn ingesteld.</p>
                </div>
            </section>
        </div>

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
        <h2>4. Ontvangers van het 18-jarigenoverzicht</h2>
        <p class="library-help">Naast de verjaardagsfelicitaties verstuurt de <strong>aparte 18-jarigentaak</strong> een overzicht naar aangewezen medewerkers. Deze ontvangers zijn <strong>per school</strong> instelbaar en staan los van de berichten die leerlingen zelf krijgen.</p>
        @foreach($schools as $school)
            <details class="library-birthday-school" @if((string) request('overzicht_school') === (string) $school->id) open @endif>
                <summary><strong>{{ $school->schoolnaam }}</strong> <span>· Ontvangers aanpassen</span></summary>
                <form method="post" action="{{ route('beheer.birthdays.overview.update') }}" class="library-birthday-overview-form">
                    @csrf
                    <input type="hidden" name="school_id" value="{{ $school->id }}">
                    <div class="library-birthday-overview-field">
                        <div class="library-birthday-channel-heading">
                            <span aria-hidden="true">S</span>
                            <div><h3>Smartschool-overzicht</h3><p class="library-help">Smartschool-gebruikersnamen van medewerkers die het overzicht ontvangen.</p></div>
                        </div>
                        <label for="overview-smartschool-{{ $school->id }}">Smartschool-gebruikersnamen</label>
                        <textarea id="overview-smartschool-{{ $school->id }}" name="overview_smartschool" rows="3" maxlength="3000"
                            placeholder="bijvoorbeeld: medewerker.een; medewerker.twee">{{ old('school_id') == $school->id ? old('overview_smartschool', $school->ontvangers_overzicht_18_ssaccount) : $school->ontvangers_overzicht_18_ssaccount }}</textarea>
                        <p class="library-help">Meerdere gebruikersnamen scheiden met een komma, puntkomma of nieuwe regel. Leeg laten = geen Smartschool-overzicht voor deze school.</p>
                    </div>
                    <div class="library-birthday-overview-field">
                        <div class="library-birthday-channel-heading">
                            <span aria-hidden="true">@</span>
                            <div><h3>E-mailoverzicht</h3><p class="library-help">E-mailadressen van medewerkers die hetzelfde overzicht ontvangen.</p></div>
                        </div>
                        <label for="overview-email-{{ $school->id }}">E-mailadressen</label>
                        <textarea id="overview-email-{{ $school->id }}" name="overview_email" rows="3" maxlength="3000"
                            placeholder="bijvoorbeeld: medewerker@school.be; directie@school.be">{{ old('school_id') == $school->id ? old('overview_email', $school->ontvangers_overzicht_18) : $school->ontvangers_overzicht_18 }}</textarea>
                        <p class="library-help">Meerdere adressen scheiden met een komma, puntkomma of nieuwe regel. Leeg laten = geen e-mailoverzicht voor deze school.</p>
                    </div>
                    <button type="submit" class="library-button library-button-primary">Ontvangers voor {{ $school->schoolnaam }} opslaan</button>
                </form>
            </details>
        @endforeach
        <p class="library-help">De bestaande 18-jarigencontrole gebruikt deze lijsten rechtstreeks. De taak draait om 01.00 uur. Er wordt alleen een Smartschool-overzicht verstuurd wanneer er leerlingen van 18 jaar in het overzicht zitten. Het e-mailoverzicht volgt de bestaande 18-jarigenverwerking. Het opslaan hier verstuurt niets.</p>
    </section>

    <section class="library-form-card" style="margin-top:20px">
        <h2>Ontvangers controleren</h2>
        <p>Bekijk vooraf <strong>wie voor een bepaalde datum een felicitatie zou ontvangen</strong>, met het exacte account en eventuele reden voor overslaan. Dit is alleen een controle; er wordt niets verzonden.</p>
        <form method="get" action="{{ route('beheer.birthdays.index') }}" class="library-birthday-preview-form">
            <div class="library-field">
                <label for="preview-date">Verjaardagsdatum</label>
                <input type="date" name="datum" id="preview-date" value="{{ $previewDate }}" required>
            </div>
            <button class="library-button library-button-secondary" type="submit">Ontvangers bekijken</button>
        </form>
        <p class="library-help">
            {{ $preview['student_count'] }} jarige leerling(en), {{ $preview['staff_count'] }} jarige personeelsleden.
            Het overzicht gebruikt de <strong>opgeslagen instellingen</strong>. Sla wijzigingen bovenaan eerst op voor je opnieuw controleert.
        </p>
        @foreach(['smartschool' => 'Smartschool-berichten', 'email' => 'E-mailberichten'] as $channel => $channelLabel)
            @php $entries = $preview['rows']->where('channel', $channel); @endphp
            <h3>{{ $channelLabel }} <small>({{ $entries->where('will_send', true)->count() }} gepland)</small></h3>
            <div class="library-table-wrap">
                <table class="library-table">
                    <thead><tr><th>Naam</th><th>Wie?</th><th>School</th><th>{{ $channel === 'email' ? 'E-mailadres' : 'Smartschool-gebruikersnaam' }}</th><th>Verzendstatus</th></tr></thead>
                    <tbody>
                        @forelse($entries as $entry)
                            <tr>
                                <td>{{ $entry['name'] }}</td>
                                <td>{{ $entry['type'] }} ({{ $entry['age'] }} jaar)</td>
                                <td>{{ $entry['school_id'] ?: '—' }}</td>
                                <td><code>{{ $entry['target'] !== '' ? $entry['target'] : 'Niet ingevuld' }}</code></td>
                                <td>{{ $entry['status'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5">Geen jarigen voor deze datum gevonden.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endforeach
        <p class="library-help">‘Zou verzonden worden’ betekent dat de huidige instellingen en accountgegevens verzending toelaten. Het garandeert niet dat Smartschool of de mailserver het bericht effectief zal bezorgen. Registraties van eerdere pogingen worden meegetoond.</p>
    </section>

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
