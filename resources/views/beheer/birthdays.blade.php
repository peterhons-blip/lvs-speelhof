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
                        value="{{ old('sender', $settings['sender'] !== '' ? $settings['sender'] : $birthdaySenderFallback) }}" placeholder="Leeg = automatische schoolafzender">
                    <p class="library-help">
                        @if($birthdaySenderFallback !== '')
                            Automatische afzender: <strong>{{ $birthdaySenderFallback }}</strong>. De actuele waarde is alvast in het veld gezet; je kunt ze aanpassen.
                        @else
                            De automatische afzender verschilt per school. Laat dit veld leeg om de bestaande schoolgebonden accounts te behouden, of selecteer één school en sla dan de gewenste afzender op.
                            @foreach($birthdaySenders as $schoolId => $senderName)
                                <span style="display:inline-block;margin-right:10px">School {{ $schoolId }}: <strong>{{ $senderName }}</strong></span>
                            @endforeach
                        @endif
                    </p>
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
    <section class="library-form-card library-birthday-privacy" id="coaccounts-beheer">
        <h2>Automatisch uitschakelen co-accounts op 18 jaar</h2>
        @if(($settings['adult_disable_coaccounts'] ?? '1') === '1')
            <p><strong>Huidige status: ingeschakeld.</strong> De cronjob probeert op de 18e verjaardag de ouder-/voogdaccounts automatisch uit te schakelen.</p>
        @else
            <p class="library-birthday-privacy-alert" role="alert"><strong>LET OP: automatische uitschakeling staat UIT.</strong> Co-accounts blijven mogelijk toegankelijk. Controleer elke meerderjarige leerling handmatig en schakel co-accounts waar nodig rechtstreeks in Smartschool uit.</p>
        @endif
        <p class="library-help">Dit regelt alleen de automatische deactivatie. Waarschuwingsberichten, het secretariaatsbericht en de overzichten hebben afzonderlijke instellingen. Bij pauzering meldt het systeem de actie als handmatig op te volgen.</p>
        @if($canManagePrivacy)
            <form method="post" action="{{ route('beheer.birthdays.privacy.update') }}" class="library-form library-birthday-privacy-form">
                @csrf
                <div class="library-form-grid">
                    <div class="library-field">
                        <label for="adult_disable_coaccounts">Automatisch uitschakelen op de 18e verjaardag</label>
                        <select id="adult_disable_coaccounts" name="adult_disable_coaccounts" required>
                            <option value="1" @selected(old('adult_disable_coaccounts', $settings['adult_disable_coaccounts'] ?? '1') === '1')>Ingeschakeld</option>
                            <option value="0" @selected(old('adult_disable_coaccounts', $settings['adult_disable_coaccounts'] ?? '1') === '0')>Uitgeschakeld — handmatige opvolging noodzakelijk</option>
                        </select>
                    </div>
                    <div class="library-field library-wide">
                        <label for="privacy_reason">Reden voor deze wijziging (verplicht)</label>
                        <textarea id="privacy_reason" name="reason" rows="3" maxlength="1000" minlength="10" required
                            placeholder="Waarom wordt deze instelling gewijzigd?">{{ old('reason') }}</textarea>
                    </div>
                </div>
                <label class="library-birthday-privacy-confirm">
                    <input type="checkbox" name="confirm" value="1" required>
                    <span>Ik bevestig dat ik begrijp dat het uitschakelen van deze automatisering extra handmatige opvolging vereist, en dat mijn wijziging geregistreerd wordt.</span>
                </label>
                <button type="submit" class="library-button library-button-primary">Instelling opslaan en registreren</button>
            </form>
            <h3>Laatste wijzigingen</h3>
            <div class="library-table-wrap">
                <table class="library-table">
                    <thead><tr><th>Wanneer</th><th>Door</th><th>Nieuwe status</th><th>Reden</th></tr></thead>
                    <tbody>
                        @forelse($privacyAudit as $entry)
                            <tr>
                                <td>{{ $entry->created_at }}</td>
                                <td>{{ $entry->actor_username }} ({{ $entry->actor_role }})</td>
                                <td>{{ $entry->new_value === '1' ? 'Ingeschakeld' : 'Uitgeschakeld' }}</td>
                                <td>{{ $entry->reason }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4">Nog geen wijzigingen geregistreerd.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @else
            <p class="library-help">Deze instelling is uitsluitend wijzigbaar door directie en ICT.</p>
        @endif
    </section>

    <section class="library-form-card library-birthday-warning">
        <h2>Waarschuwing vóór de 18e verjaardag</h2>
        <p class="library-help">De aparte 18-jarigentaak kan de leerling en de gekoppelde ouder-/voogdaccounts vooraf via Smartschool waarschuwen. De co-accounts worden op de 18e verjaardag volgens de bestaande procedure uitgeschakeld. Deze instellingen regelen <strong>alleen de voorafgaande waarschuwing</strong>, niet de verplichte opvolging op de verjaardag zelf.</p>
        <form method="post" action="{{ route('beheer.birthdays.warning.update') }}" id="warning-form" class="library-form">
            @csrf
            <div class="library-form-grid">
                @foreach([
                    'warning_enabled' => 'Waarschuwingen vooraf inschakelen',
                    'warning_student' => 'Smartschool-bericht naar leerling',
                    'warning_coaccounts' => 'Smartschool-bericht naar ouder-/voogdaccounts',
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
                    <label for="warning_days">Aantal dagen vooraf</label>
                    <input type="number" id="warning_days" name="warning_days" min="1" max="30"
                        value="{{ old('warning_days', $settings['warning_days']) }}" required>
                    <p class="library-help">Standaard 7 dagen. De taak controleert dagelijks om 01.00 uur.</p>
                </div>
                <div class="library-field library-wide">
                    <label for="warning_sender">Smartschool-afzender waarschuwing</label>
                    <input id="warning_sender" name="warning_sender" maxlength="120"
                        value="{{ old('warning_sender', $settings['warning_sender']) }}" placeholder="Leeg = bestaande schoolafzender gebruiken">
                    <p class="library-help">
                        @if($warningSenderFallback)
                            Huidig automatisch account voor {{ $warningPreviewSchool->schoolnaam }}: <strong>{{ $warningSenderFallback }}</strong>.
                        @else
                            De effectieve afzender verschilt per school: eerst het ingestelde secretariaatsaccount, dan de algemene schoolafzender en als laatste het standaardaccount (vaak <code>lvs</code>). Kies hierboven een school om de automatische afzender te zien.
                        @endif
                        Laat het veld leeg om deze bestaande instelling te behouden.
                    </p>
                </div>
                <div class="library-field library-wide">
                    <label for="warning_subject">Onderwerp van de waarschuwing</label>
                    <input id="warning_subject" name="warning_subject" maxlength="200" required
                        value="{{ old('warning_subject', $settings['warning_subject']) }}">
                </div>
                <div class="library-field library-wide">
                    <label for="warning_body_student">Berichttekst leerling (optioneel)</label>
                    <textarea id="warning_body_student" name="warning_body_student" rows="6" maxlength="5000" placeholder="Leeg laten = bestaande schooltemplate gebruiken">{{ old('warning_body_student', $settings['warning_body_student'] !== '' ? $settings['warning_body_student'] : $warningStudentDefault) }}</textarea>
                </div>
                <div class="library-field library-wide">
                    <label for="warning_body_coaccount">Berichttekst ouders/voogden (optioneel)</label>
                    <textarea id="warning_body_coaccount" name="warning_body_coaccount" rows="6" maxlength="5000" placeholder="Leeg laten = bestaande schooltemplate gebruiken">{{ old('warning_body_coaccount', $settings['warning_body_coaccount'] !== '' ? $settings['warning_body_coaccount'] : $warningCoaccountDefault) }}</textarea>
                </div>
            </div>
            <p class="library-help">De tekstvakken zijn vooraf ingevuld met bewerkbare voorbeeldteksten, gebaseerd op de huidige schooltemplates. <strong>Ze worden pas gebruikt nadat je op ‘Waarschuwingsinstellingen opslaan’ klikt.</strong> Wil je de originele schoolspecifieke template blijven gebruiken, maak het tekstvak dan leeg.</p>
            <p class="library-help">Beschikbare variabelen voor de aangepaste teksten: <code>{voornaam}</code>, <code>{naam}</code>, <code>{school}</code> en <code>{dagen}</code>. Leeg laten behoudt de bestaande standaard- of schoolteksten. Bij een andere termijn dan 7 dagen wordt de standaardtekst automatisch aangepast.</p>
            <button type="submit" class="library-button library-button-primary">Waarschuwingsinstellingen opslaan</button>
        </form>
    </section>

    <section class="library-form-card library-birthday-adult">
        <h2>Berichten op de 18e verjaardag</h2>
        <p class="library-help">De privacyprocedure schakelt gekoppelde co-accounts automatisch uit, los van de berichten hieronder. Je kunt de communicatie aanpassen, maar <strong>niet</strong> de verplichte uitschakeling uitschakelen.</p>
        <form method="post" action="{{ route('beheer.birthdays.adult.update') }}" class="library-form">
            @csrf
            <h3>Bericht over ouderaccounts aan de leerling</h3>
            <div class="library-form-grid">
                <div class="library-field"><label for="adult_student_notice">Bericht verzenden</label>
                    <select id="adult_student_notice" name="adult_student_notice">
                        <option value="1" @selected(old('adult_student_notice', $settings['adult_student_notice']) === '1')>Ja</option>
                        <option value="0" @selected(old('adult_student_notice', $settings['adult_student_notice']) === '0')>Nee</option>
                    </select></div>
                <div class="library-field"><label for="adult_sender_student">Afzender Smartschool</label>
                    <input id="adult_sender_student" name="adult_sender_student" maxlength="120" value="{{ old('adult_sender_student', $settings['adult_sender_student']) }}" placeholder="Automatisch: algemene schoolafzender"></div>
                <div class="library-field library-wide"><label for="adult_subject_student">Onderwerp</label>
                    <input id="adult_subject_student" name="adult_subject_student" maxlength="200" required value="{{ old('adult_subject_student', $settings['adult_subject_student']) }}"></div>
                <div class="library-field library-wide"><label for="adult_body_student">Berichttekst leerling</label>
                    <textarea id="adult_body_student" name="adult_body_student" maxlength="5000" rows="5" placeholder="Leeg laten = bestaande Smartschool-template van de school">{{ old('adult_body_student', $settings['adult_body_student']) }}</textarea>
                    <p class="library-help">Leeg laten behoudt het huidige schoolbericht. Gebruik indien gewenst {voornaam}, {naam} en {school}.</p>
                </div>
            </div>
            <h3>Uitnodiging leerlingensecretariaat</h3>
            <div class="library-form-grid">
                <div class="library-field"><label for="adult_secretariat_notice">Uitnodiging versturen</label>
                    <select id="adult_secretariat_notice" name="adult_secretariat_notice">
                        <option value="1" @selected(old('adult_secretariat_notice', $settings['adult_secretariat_notice']) === '1')>Ja</option>
                        <option value="0" @selected(old('adult_secretariat_notice', $settings['adult_secretariat_notice']) === '0')>Nee</option>
                    </select></div>
                <div class="library-field"><label for="adult_sender_secretariat">Afzender Smartschool</label>
                    <input id="adult_sender_secretariat" name="adult_sender_secretariat" maxlength="120" value="{{ old('adult_sender_secretariat', $settings['adult_sender_secretariat']) }}" placeholder="Automatisch: secretariaatsaccount"></div>
                <div class="library-field library-wide"><label for="adult_subject_secretariat">Onderwerp</label>
                    <input id="adult_subject_secretariat" name="adult_subject_secretariat" maxlength="200" required value="{{ old('adult_subject_secretariat', $settings['adult_subject_secretariat']) }}"></div>
                <div class="library-field library-wide"><label for="adult_body_secretariat">Berichttekst secretariaat</label>
                    <textarea id="adult_body_secretariat" name="adult_body_secretariat" maxlength="5000" rows="5" placeholder="Leeg laten = bestaande Smartschool-template van de school">{{ old('adult_body_secretariat', $settings['adult_body_secretariat']) }}</textarea>
                </div>
            </div>
            <h3>Dagelijkse overzichten voor medewerkers</h3>
            <div class="library-form-grid">
                @foreach([
                    'adult_overview_smartschool_enabled' => 'Overzicht via Smartschool versturen',
                    'adult_overview_email_enabled' => 'Overzicht via e-mail versturen',
                ] as $key => $label)
                    <div class="library-field"><label for="{{ $key }}">{{ $label }}</label>
                        <select id="{{ $key }}" name="{{ $key }}">
                            <option value="1" @selected(old($key, $settings[$key]) === '1')>Ja</option>
                            <option value="0" @selected(old($key, $settings[$key]) === '0')>Nee</option>
                        </select></div>
                @endforeach
                <div class="library-field library-wide"><label for="adult_overview_smartschool_subject">Onderwerp Smartschool-overzicht</label>
                    <input id="adult_overview_smartschool_subject" name="adult_overview_smartschool_subject" maxlength="200" required value="{{ old('adult_overview_smartschool_subject', $settings['adult_overview_smartschool_subject']) }}"></div>
                <div class="library-field"><label for="adult_overview_sender">Afzender Smartschool-overzicht</label>
                    <input id="adult_overview_sender" name="adult_overview_sender" maxlength="120" value="{{ old('adult_overview_sender', $settings['adult_overview_sender']) }}" placeholder="Automatisch: algemene schoolafzender"></div>
                <div class="library-field"><label for="adult_overview_email_subject">Onderwerp e-mailoverzicht</label>
                    <input id="adult_overview_email_subject" name="adult_overview_email_subject" maxlength="200" value="{{ old('adult_overview_email_subject', $settings['adult_overview_email_subject']) }}" placeholder="Leeg = bestaand onderwerp behouden"></div>
            </div>
            <p class="library-help">De ontvangerslijsten voor de overzichten wijzig je hieronder, per school. De inhoud van het overzicht blijft de bestaande leerling- en actiegegevens tonen.</p>
            <button type="submit" class="library-button library-button-primary">Berichten en overzichten opslaan</button>
        </form>
    </section>

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
                        @php
                            $existingUsers = array_values(array_filter(array_map('trim', preg_split('/[;,\\r\\n]+/', (string) $school->ontvangers_overzicht_18_ssaccount))));
                            $selectedUsers = old('school_id') == $school->id
                                ? old('overview_smartschool_users', $existingUsers) : $existingUsers;
                        @endphp
                        <label for="account-search-{{ $school->id }}">Zoek personeelslid of Smartschool-gebruikersnaam</label>
                        <input type="search" id="account-search-{{ $school->id }}" class="library-birthday-staff-search"
                            data-list="accounts-{{ $school->id }}" placeholder="Zoek op naam of gebruikersnaam..." autocomplete="off">
                        <div class="library-birthday-staff-options" id="accounts-{{ $school->id }}">
                            @forelse($schoolSmartschoolChoices[$school->id] ?? [] as $username => $label)
                                <label class="library-birthday-staff-option">
                                    <input type="checkbox" name="overview_smartschool_users[]" value="{{ $username }}"
                                        @checked(in_array($username, $selectedUsers, true))>
                                    <span>{{ $label }}</span>
                                </label>
                            @empty
                                <p class="library-help">Geen actieve Smartschool-personeelsaccounts gevonden voor deze school.</p>
                            @endforelse
                        </div>
                        <p class="library-help"><strong>Vink de ontvangers aan.</strong> Je kunt zoeken op naam of gebruikersnaam. Reeds ingestelde ontvangers die niet meer in de actieve personeelslijst staan, blijven herkenbaar zodat je ze kunt nakijken.</p>
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

    <section id="ontvangers-controleren" class="library-form-card" style="margin-top:20px; scroll-margin-top: 100px;">
        <h2>Ontvangers controleren</h2>
        <p>Bekijk vooraf <strong>wie voor een bepaalde datum een felicitatie zou ontvangen</strong>, met het exacte account en eventuele reden voor overslaan. Dit is alleen een controle; er wordt niets verzonden.</p>
        <form method="get" action="{{ route('beheer.birthdays.index') }}#ontvangers-controleren" class="library-birthday-preview-form">
            <input type="hidden" name="adult_date" value="{{ $adultDate }}">
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

    <section class="library-form-card library-birthday-samples" style="margin-top:24px">
        <h2>Voorbeelden van de berichten</h2>
        <p class="library-help">Hier zie je met fictieve leerlinggegevens hoe de huidige, <strong>opgeslagen</strong> berichten eruitzien. Alleen-lezen: deze voorbeeldweergaven versturen niets en wijzigen geen co-accounts. Sla gewijzigde teksten eerst op.</p>
        @foreach($exampleMessages as $message)
            <details class="library-birthday-school">
                <summary><strong>{{ $message['title'] }}</strong><span>Voorbeeld bekijken</span></summary>
                <div class="library-birthday-example">
                    <p><strong>Onderwerp:</strong> {{ $message['subject'] }}</p>
                    <iframe title="Voorbeeld {{ $message['title'] }}" sandbox="" loading="lazy"
                        srcdoc="{{ $message['html'] }}"></iframe>
                </div>
            </details>
        @endforeach
    </section>

    <section id="opvolging-18-jarigen" class="library-form-card library-birthday-history" style="margin-top:24px; scroll-margin-top: 100px;">
        <h2>Opvolging 18-jarigen</h2>
        <p class="library-help">Bekijk per datum welke leerlingen 18 worden en de afzonderlijke acties. Een mislukte of onzekere actie wordt niet automatisch opnieuw uitgevoerd; controleer zulke gevallen handmatig. Deze pagina verstuurt niets.</p>
        <form method="get" action="{{ route('beheer.birthdays.index') }}#opvolging-18-jarigen" class="library-birthday-preview-form">
            <input type="hidden" name="datum" value="{{ $previewDate }}">
            <div class="library-field"><label for="adult_date">18e verjaardag op</label>
                <input type="date" name="adult_date" id="adult_date" value="{{ $adultDate }}" required></div>
            <button type="submit" class="library-button library-button-secondary">Opvolging bekijken</button>
        </form>
        <h3>Leerlingen die op deze datum 18 worden</h3>
        <div class="library-table-wrap"><table class="library-table">
            <thead><tr><th>Leerling</th><th>Smartschool</th><th>School</th></tr></thead>
            <tbody>
                @forelse($adultUpcoming as $student)
                    <tr><td>{{ $student->voornaam }} {{ $student->naam }}</td><td>{{ $student->gebruikersnaam ?: 'Ontbreekt' }}</td><td>{{ $student->schoolid }}</td></tr>
                @empty
                    <tr><td colspan="3">Geen actieve leerlingen gevonden.</td></tr>
                @endforelse
            </tbody>
        </table></div>
        <h3>Actiehistoriek ({{ $adultActions->count() }})</h3>
        <div class="library-table-wrap"><table class="library-table">
            <thead><tr><th>Leerling</th><th>Actie</th><th>Ontvanger</th><th>Status</th><th>Foutmelding</th></tr></thead>
            <tbody>
                @forelse($adultActions as $entry)
                    <tr>
                        <td>{{ $entry->student_id ? trim(($entry->voornaam ?? '').' '.($entry->naam ?? '')) : 'Overzicht naar medewerkers' }}</td>
                        <td>{{ str_replace('_', ' ', $entry->action_key) }}</td>
                        <td>{{ $entry->recipient ?: '—' }}</td>
                        <td>{{ ['sent'=>'Uitgevoerd','failed'=>'Mislukt','processing'=>'In verwerking'][$entry->status] ?? $entry->status }}</td>
                        <td>{{ $entry->error_message ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5">Geen geregistreerde acties op deze datum.</td></tr>
                @endforelse
            </tbody>
        </table></div>
        <p class="library-help"><strong>Veilige controle:</strong> <code>php artisan leerlingen:check-18 --test</code> verstuurt niets en schakelt geen accounts uit. Gebruik niet <code>--test-ss-overzicht</code> voor een verzendvrije test: die optie verstuurt echte Smartschool-berichten.</p>
        <p class="library-help">Maximaal 250 records per overzicht. Oudere uitvoeringen van vóór de invoering van deze log verschijnen hier niet.</p>
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
@push('scripts')
<script>
document.querySelectorAll('.library-birthday-staff-search').forEach(search => {
    const container = document.getElementById(search.dataset.list);
    if (!container) return;

    const collator = new Intl.Collator('nl-BE', { sensitivity: 'base', numeric: true });
    const sortedOptions = () => [...container.querySelectorAll('.library-birthday-staff-option')]
        .sort((a, b) => {
            const aChecked = a.querySelector('input[type="checkbox"]').checked;
            const bChecked = b.querySelector('input[type="checkbox"]').checked;
            if (aChecked !== bChecked) return aChecked ? -1 : 1;
            return collator.compare(a.querySelector('span').textContent.trim(), b.querySelector('span').textContent.trim());
        });

    const refresh = () => {
        const query = search.value.trim().toLocaleLowerCase('nl-BE');
        sortedOptions().forEach(label => {
            container.appendChild(label);
            label.hidden = query !== '' && !label.textContent.toLocaleLowerCase('nl-BE').includes(query);
        });
    };

    search.addEventListener('input', refresh);
    container.addEventListener('change', event => {
        if (event.target.matches('input[type="checkbox"]')) refresh();
    });
    refresh();
});
</script>
@endpush
@endsection
