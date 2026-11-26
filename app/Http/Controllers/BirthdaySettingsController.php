<?php

namespace App\Http\Controllers;

use App\Services\BirthdaySettings;
use App\Services\LibraryAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class BirthdaySettingsController extends Controller
{
    public function index(Request $request, LibraryAccess $access, BirthdaySettings $birthdaySettings): View
    {
        $access->requirePermission($request, 'beheer.birthdays.manage');
        $settings = $birthdaySettings->all();
        $previewDate = (string) $request->query('datum', now('Europe/Brussels')->toDateString());
        $validatedDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $previewDate);
        if (! $validatedDate || $validatedDate->format('Y-m-d') !== $previewDate) {
            $previewDate = now('Europe/Brussels')->toDateString();
        }
        $preview = $birthdaySettings->preview($previewDate, $settings);
        $schools = DB::table('scholen')->orderBy('schoolnaam')->get(['id', 'schoolnaam', 'ontvangers_overzicht_18', 'ontvangers_overzicht_18_ssaccount', 'smartschool_verzender_secretariaat', 'smartschool_verzender', 'smartschool_verzender_beleid']);
        $birthdaySenders = $schools->mapWithKeys(static function ($school) {
            $resolved = trim((string) ($school->smartschool_verzender_beleid ?? ''))
                ?: trim((string) ($school->smartschool_verzender ?? ''))
                ?: config('services.smartschool.sender')
                ?: env('SMARTSCHOOL_SENDER_USER')
                ?: 'lvs';
            return [$school->id => $resolved];
        });
        $birthdaySelectedSchool = (int) ($settings['school_id'] ?? 0);
        $birthdaySenderFallback = $birthdaySelectedSchool > 0
            ? ($birthdaySenders->get($birthdaySelectedSchool) ?? '')
            : ($birthdaySenders->unique()->count() === 1 ? (string) $birthdaySenders->first() : '');
        $warningPreviewSchool = $schools->firstWhere('id', (int) ($settings['school_id'] ?? 0));
        $warningSenderFallback = $warningPreviewSchool
            ? (trim((string) $warningPreviewSchool->smartschool_verzender_secretariaat)
                ?: trim((string) $warningPreviewSchool->smartschool_verzender)
                ?: config('services.smartschool.sender')
                ?: env('SMARTSCHOOL_SENDER_USER')
                ?: 'lvs')
            : null;
        $warningStudentDefault = "Beste {voornaam} {naam},\n\n"
            ."Binnen {dagen} dagen word je 18 jaar. 🎉\n\n"
            ."Vanaf je 18e verjaardag beslis jij zelf wie toegang krijgt tot jouw gegevens in Smartschool. "
            ."Daarom worden je ouder-/voogdaccounts op je 18e verjaardag uitgeschakeld.\n\n"
            ."Je ouders of voogd kunnen dan niet meer inloggen via hun account. "
            ."Als je dat later wenst, kun je toestemming geven om die toegang opnieuw te activeren.\n\n"
            ."Op je 18e verjaardag krijg je hierover nog een bericht.\n\n"
            ."Met vriendelijke groeten\n{school}";
        $warningCoaccountDefault = "Beste ouder/voogd van {voornaam} {naam},\n\n"
            ."Dit is een automatische melding vanuit {school}.\n\n"
            ."Binnen {dagen} dagen wordt {voornaam} {naam} 18 jaar. Vanaf dan beslist de leerling "
            ."zelf wie toegang krijgt tot zijn of haar gegevens in Smartschool.\n\n"
            ."De ouder-/voogdaccounts worden daarom op de 18e verjaardag uitgeschakeld. "
            ."Als de leerling nadien toestemming geeft, kan het account opnieuw geactiveerd worden.\n\n"
            ."Met vriendelijke groeten\n{school}";
        $staffOptions = DB::table('users')->whereNotNull('smartschool_gebruikersnaam')
            ->where('smartschool_gebruikersnaam', '<>', '')
            ->where(function ($query) {
                $query->whereNull('smartschool_active')->orWhere('smartschool_active', 1);
            })
            ->select('schoolid', 'voornaam', 'name', 'smartschool_gebruikersnaam')
            ->orderBy('name')->orderBy('voornaam')->get()
            ->groupBy('schoolid');
        $schoolSmartschoolChoices = [];
        foreach ($schools as $school) {
            $options = collect($staffOptions->get($school->id, collect()))
                ->mapWithKeys(fn ($person) => [
                    (string) $person->smartschool_gebruikersnaam =>
                        trim(($person->voornaam ?? '').' '.($person->name ?? '')).' ('.$person->smartschool_gebruikersnaam.')',
                ]);
            $existing = preg_split('/[;,\\r\\n]+/', (string) $school->ontvangers_overzicht_18_ssaccount, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($existing as $username) {
                $username = trim($username);
                if ($username !== '' && ! $options->has($username)) {
                    $options->put($username, $username.' (bestaande ontvanger, niet in actieve personeelslijst)');
                }
            }
            $schoolSmartschoolChoices[$school->id] = $options;
        }
        $canManagePrivacy = in_array($access->role($request), ['directie', 'ict_coordinator'], true);
        $privacyAudit = $canManagePrivacy && Schema::hasTable('birthday_18_privacy_audit')
            ? DB::table('birthday_18_privacy_audit')->orderByDesc('id')->limit(20)->get()
            : collect();
        $adultDate = (string) $request->query('adult_date', now('Europe/Brussels')->toDateString());
        $adultDateValue = \DateTimeImmutable::createFromFormat('!Y-m-d', $adultDate);
        if (! $adultDateValue || $adultDateValue->format('Y-m-d') !== $adultDate) {
            $adultDate = now('Europe/Brussels')->toDateString();
        }
        $adultActions = Schema::hasTable('birthday_18_actions')
            ? DB::table('birthday_18_actions as actions')
                ->leftJoin('leerlingen as students', 'students.id', '=', 'actions.student_id')
                ->select('actions.*', 'students.voornaam', 'students.naam', 'students.gebruikersnaam')
                ->where('actions.target_date', $adultDate)
                ->orderBy('students.naam')->orderBy('actions.action_key')
                ->limit(250)->get()
            : collect();
        $adultUpcoming = DB::table('leerlingen')
            ->where('active', 1)
            ->whereNotNull('geboortedatum')
            ->whereRaw('MONTH(geboortedatum) = MONTH(?) AND DAY(geboortedatum) = DAY(?) AND YEAR(geboortedatum) = YEAR(?) - 18',
                [$adultDate, $adultDate, $adultDate])
            ->orderBy('naam')->limit(250)
            ->get(['id', 'voornaam', 'naam', 'gebruikersnaam', 'schoolid']);
        // Read-only message examples with dummy data; never sends or changes accounts.
        $exampleSchool = \App\Models\School::query()
            ->when((int) $settings['school_id'] > 0,
                fn ($query) => $query->whereKey((int) $settings['school_id']))
            ->first() ?: (object) ['id' => 0, 'schoolnaam' => 'GO! Atheneum Sint-Truiden'];
        $exampleData = [
            'voornaam' => 'Voorbeeld', 'naam' => 'Leerling',
            'school' => $exampleSchool->schoolnaam, 'dagen' => $settings['warning_days'],
        ];
        $exampleDefinitions = [
            ['title' => 'Waarschuwing leerling', 'subject' => $settings['warning_subject'],
                'override' => 'warning_body_student', 'template' => 'wordt18_vooraf'],
            ['title' => 'Waarschuwing ouder-/voogdaccount', 'subject' => $settings['warning_subject'],
                'override' => 'warning_body_coaccount', 'template' => 'wordt18_vooraf_coaccount'],
            ['title' => 'Bericht op 18e verjaardag', 'subject' => $settings['adult_subject_student'],
                'override' => 'adult_body_student', 'template' => 'wordt18'],
            ['title' => 'Uitnodiging secretariaat', 'subject' => $settings['adult_subject_secretariat'],
                'override' => 'adult_body_secretariat', 'template' => 'wordt18_secretariaat'],
            ['title' => 'Smartschool-overzicht voor medewerkers', 'subject' => $settings['adult_overview_smartschool_subject'],
                'override' => '', 'template' => 'overzicht_worden18'],
        ];
        // Display each school's real fallback without turning it into a global override.
        $adultSenderDefaults = $schools->map(function ($school) {
            $general = trim((string) $school->smartschool_verzender) ?: (env('SMARTSCHOOL_SENDER_USER') ?: 'lvs');
            return ['school'=>$school->schoolnaam, 'general'=>$general,
                'secretariat'=>trim((string) $school->smartschool_verzender_secretariaat) ?: $general];
        });
        $adultBodyDefaults = [];
        foreach (['adult_body_student'=>'wordt18','adult_body_secretariat'=>'wordt18_secretariaat'] as $key=>$template) {
            foreach ($schools as $school) {
                $preferred='smartschool.berichten.scholen.'.$school->id.'.'.$template;
                $html=view(view()->exists($preferred) ? $preferred : 'smartschool.berichten.default.'.$template, [
                    'voornaam'=>'{voornaam}', 'naam'=>'{naam}',
                    'school'=>(object)['schoolnaam'=>'{school}'], 'beleid'=>null,
                ])->render();
                $plain=preg_replace('~<br\s*/?>~i', "\n", $html);
                $plain=preg_replace('~</p\s*>~i', "\n\n", $plain);
                $plain=preg_replace('~<li\s*>~i', '• ', $plain);
                $plain=preg_replace('~</li\s*>~i', "\n", $plain);
                $plain=html_entity_decode(strip_tags($plain), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $plain=preg_replace('/[ \t]+/u',' ', $plain);
                $plain=preg_replace('/ *\n */u',"\n",$plain);
                $plain=trim(preg_replace('/\n{3,}/u',"\n\n",$plain));
                $adultBodyDefaults[$key][]=['school'=>$school->schoolnaam,'text'=>$plain];
            }
        }
        $adultEmailSubjectDefault=config('app.name').' - Leerlingen die vandaag 18 worden – ';
        $exampleMessages = [];
        foreach ($exampleDefinitions as $definition) {
            $message = ['title' => $definition['title'], 'subject' => $definition['subject'], 'html' => ''];
            try {
                $custom = $definition['override'] !== '' ? trim((string) $settings[$definition['override']]) : '';
                if ($custom !== '') {
                    $message['html'] = nl2br(e($birthdaySettings->render($custom, $exampleData)));
                } else {
                    $preferred = 'smartschool.berichten.scholen.'.$exampleSchool->id.'.'.$definition['template'];
                    $template = view()->exists($preferred)
                        ? $preferred : 'smartschool.berichten.default.'.$definition['template'];
                    $payload = [['voornaam' => 'Voorbeeld', 'naam' => 'Leerling',
                        'klas' => '5A', 'geboortedatum' => now('Europe/Brussels')->subYears(18),
                        'smartschool_bericht_verzonden' => true,
                        'coaccounts_uitgeschakeld' => true, 'secretariaat_bericht_verzonden' => true]];
                    $message['html'] = view($template, [
                        'voornaam' => 'Voorbeeld', 'naam' => 'Leerling',
                        'school' => $exampleSchool, 'beleid' => null, 'payload' => $payload,
                    ])->render();
                    if (str_starts_with($definition['template'], 'wordt18_vooraf') &&
                        (int) $settings['warning_days'] !== 7) {
                        $days = (int) $settings['warning_days'];
                        $message['html'] = str_replace(['Binnen een week', 'binnen een week'],
                            ['Binnen '.$days.' dagen', 'binnen '.$days.' dagen'], $message['html']);
                    }
                }
            } catch (\Throwable $error) {
                $message['html'] = '<p>Voorbeeld kon niet worden geladen.</p>';
            }
            $exampleMessages[] = $message;
        }

        try {
            $emailSample = [['voornaam' => 'Voorbeeld', 'naam' => 'Leerling',
                'klas' => '5A', 'geboortedatum' => now('Europe/Brussels')->subYears(18),
                'smartschool_bericht_verzonden' => true, 'coaccounts_uitgeschakeld' => true,
                'secretariaat_bericht_verzonden' => true]];
            $exampleMessages[] = [
                'title' => 'E-mailoverzicht voor medewerkers',
                'subject' => trim((string) $settings['adult_overview_email_subject'])
                    ?: config('app.name').' - Leerlingen die vandaag 18 worden',
                'html' => view('emails.leerlingen-worden-18', [
                    'leerlingen' => $emailSample, 'appName' => config('app.name'),
                    'logoLvsCid' => '', 'logoSchoolCid' => '',
                ])->render(),
            ];
        } catch (\Throwable $error) {
            $exampleMessages[] = [
                'title' => 'E-mailoverzicht voor medewerkers',
                'subject' => trim((string) $settings['adult_overview_email_subject'])
                    ?: 'Leerlingen die vandaag 18 worden',
                'html' => '<p>Voorbeeld kon niet worden geladen.</p>',
            ];
        }

        return view('beheer.birthdays', compact('settings', 'previewDate', 'preview', 'schools', 'schoolSmartschoolChoices', 'warningPreviewSchool', 'warningSenderFallback', 'warningStudentDefault', 'warningCoaccountDefault', 'adultDate', 'adultActions', 'adultUpcoming', 'birthdaySenderFallback', 'birthdaySenders', 'exampleMessages', 'adultSenderDefaults', 'adultBodyDefaults', 'adultEmailSubjectDefault', 'canManagePrivacy', 'privacyAudit'));
    }


    /**
     * 18-year overview recipients are already read directly from scholen
     * by CheckLeeftijdLeerlingen. Update that existing source of truth.
     */
    public function updateOverviewRecipients(Request $request, LibraryAccess $access): RedirectResponse
    {
        $access->requirePermission($request, 'beheer.birthdays.manage');
        $data = $request->validate([
            'school_id' => ['required', 'integer', 'min:1'],
            'overview_smartschool_users' => ['sometimes', 'array', 'max:150'],
            'overview_smartschool_users.*' => ['string', 'max:120', 'regex:/^[a-zA-Z0-9._-]{2,120}$/'],
            'overview_email' => ['nullable', 'string', 'max:3000'],
        ]);

        $normalize = static function (?string $raw, bool $email): string {
            $items = preg_split('/[;,\\r\\n]+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY);
            $items = array_values(array_unique(array_filter(array_map('trim', $items))));
            foreach ($items as $item) {
                if ($email) {
                    if (! filter_var($item, FILTER_VALIDATE_EMAIL)) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'overview_email' => 'Ongeldig e-mailadres: '.$item,
                        ]);
                    }
                } elseif (! preg_match('/^[a-zA-Z0-9._-]{2,120}$/', $item)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'overview_smartschool' => 'Ongeldige Smartschool-gebruikersnaam: '.$item,
                    ]);
                }
            }
            return implode('; ', $items);
        };

        $smartschool = $normalize(implode('; ', $data['overview_smartschool_users'] ?? []), false);
        $emails = $normalize($data['overview_email'] ?? '', true);

        $updated = DB::table('scholen')->where('id', $data['school_id'])
            ->update([
                'ontvangers_overzicht_18_ssaccount' => $smartschool,
                'ontvangers_overzicht_18' => $emails,
            ]);
        if (! $updated && ! DB::table('scholen')->where('id', $data['school_id'])->exists()) {
            return back()->withErrors(['school_id' => 'School niet gevonden.']);
        }

        return redirect()->route('beheer.birthdays.index', ['tab' => 'overzichten', 'overzicht_school' => $data['school_id']])
            ->with('success', 'Ontvangers van het 18-jarigenoverzicht opgeslagen voor de geselecteerde school.');
    }

    /**
     * Exceptionally allow the general privacy action to be paused by directie/ICT.
     * Any change must be explicitly confirmed and recorded.
     */
    public function updatePrivacy(Request $request, LibraryAccess $access): RedirectResponse
    {
        $role = $access->role($request);
        abort_unless(in_array($role, ['directie', 'ict_coordinator'], true), 403,
            'Uitsluitend directie en ICT kunnen de co-accountdeactivatie wijzigen.');

        $data = $request->validate([
            'adult_disable_coaccounts' => ['required', 'in:0,1'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'confirm' => ['accepted'],
        ]);

        if (! Schema::hasTable('birthday_settings') || ! Schema::hasTable('birthday_18_privacy_audit')) {
            return back()->withErrors(['database' => 'Voer eerst de migraties voor verjaardagsinstellingen en privacyaudit uit.']);
        }
        $username = $access->username($request);
        abort_unless($username !== '', 403);

        $changed = false;
        DB::transaction(function () use ($data, $username, $role, &$changed) {
            // Serialize edits on the settings row and preserve an auditable change.
            DB::table('birthday_settings')->insertOrIgnore([
                'setting_key' => 'adult_disable_coaccounts', 'setting_value' => '1',
            ]);
            $current = (string) DB::table('birthday_settings')
                ->where('setting_key', 'adult_disable_coaccounts')->lockForUpdate()
                ->value('setting_value');
            $new = $data['adult_disable_coaccounts'];
            if ($current === $new) return;
            DB::table('birthday_settings')->where('setting_key', 'adult_disable_coaccounts')
                ->update(['setting_value' => $new]);
            DB::table('birthday_18_privacy_audit')->insert([
                'actor_username' => $username, 'actor_role' => $role,
                'old_value' => $current, 'new_value' => $new,
                'reason' => trim($data['reason']), 'created_at' => now(),
            ]);
            $changed = true;
        });

        return redirect()->route('beheer.birthdays.index', ['tab' => 'privacy', 'privacy' => '1'])
            ->with('success', $changed
                ? 'Instelling voor automatisch uitschakelen van co-accounts aangepast en geregistreerd.'
                : 'Geen wijziging: de gekozen instelling was al actief.');
    }

    public function updateAdult(Request $request, LibraryAccess $access): RedirectResponse
    {
        $access->requirePermission($request, 'beheer.birthdays.manage');
        $data = $request->validate([
            'adult_student_notice' => ['required', 'in:0,1'],
            'adult_secretariat_notice' => ['required', 'in:0,1'],
            'adult_subject_student' => ['required', 'string', 'max:200'],
            'adult_subject_secretariat' => ['required', 'string', 'max:200'],
            'adult_body_student' => ['nullable', 'string', 'max:5000'],
            'adult_body_secretariat' => ['nullable', 'string', 'max:5000'],
            'adult_sender_student' => ['nullable', 'string', 'max:120', 'regex:/^[a-zA-Z0-9._-]*$/'],
            'adult_sender_secretariat' => ['nullable', 'string', 'max:120', 'regex:/^[a-zA-Z0-9._-]*$/'],
            'adult_overview_smartschool_enabled' => ['required', 'in:0,1'],
            'adult_overview_email_enabled' => ['required', 'in:0,1'],
            'adult_overview_smartschool_subject' => ['required', 'string', 'max:200'],
            'adult_overview_sender' => ['nullable', 'string', 'max:120', 'regex:/^[a-zA-Z0-9._-]*$/'],
            'adult_overview_email_subject' => ['nullable', 'string', 'max:200'],
        ]);
        if (! Schema::hasTable('birthday_settings')) {
            return back()->withErrors(['database' => 'Voer eerst de verjaardagsmigratie uit.']);
        }
        DB::transaction(function () use ($data) {
            foreach ($data as $key => $value) {
                DB::table('birthday_settings')->updateOrInsert(
                    ['setting_key' => $key], ['setting_value' => trim((string) ($value ?? ''))]
                );
            }
        });
        return redirect()->route('beheer.birthdays.index', ['tab' => 'meerderjarig'])
            ->with('success', 'Instellingen voor de 18e verjaardag en overzichten opgeslagen.');
    }

    public function updateWarning(Request $request, LibraryAccess $access): RedirectResponse
    {
        $access->requirePermission($request, 'beheer.birthdays.manage');
        $data = $request->validate([
            'warning_enabled' => ['required', 'in:0,1'],
            'warning_days' => ['required', 'integer', 'min:1', 'max:30'],
            'warning_student' => ['required', 'in:0,1'],
            'warning_coaccounts' => ['required', 'in:0,1'],
            'warning_sender' => ['nullable', 'string', 'max:120', 'regex:/^[a-zA-Z0-9._-]*$/'],
            'warning_subject' => ['required', 'string', 'max:200'],
            'warning_body_student' => ['nullable', 'string', 'max:5000'],
            'warning_body_coaccount' => ['nullable', 'string', 'max:5000'],
        ]);
        if (! Schema::hasTable('birthday_settings')) {
            return back()->withErrors(['database' => 'Voer eerst de verjaardagsmigratie uit.']);
        }
        DB::transaction(function () use ($data) {
            foreach ($data as $key => $value) {
                DB::table('birthday_settings')->updateOrInsert(
                    ['setting_key' => $key], ['setting_value' => trim((string) ($value ?? ''))]
                );
            }
        });
        return redirect()->route('beheer.birthdays.index', ['tab' => 'waarschuwing'])
            ->with('success', 'Waarschuwingen voor 18-jarigen opgeslagen.');
    }

    public function update(Request $request, LibraryAccess $access, BirthdaySettings $birthdaySettings): RedirectResponse
    {
        $access->requirePermission($request, 'beheer.birthdays.manage');
        $data = $request->validate([
            'enabled' => ['required', 'in:0,1'],
            'students_enabled' => ['required', 'in:0,1'],
            'staff_enabled' => ['required', 'in:0,1'],
            'smartschool_students' => ['required', 'in:0,1'],
            'smartschool_staff' => ['required', 'in:0,1'],
            'email_students' => ['required', 'in:0,1'],
            'email_staff' => ['required', 'in:0,1'],
            'school_id' => ['required', 'integer', 'min:0', 'max:999999'],
            'sender' => ['nullable', 'string', 'max:120', 'regex:/^[a-zA-Z0-9._-]*$/'],
            'subject_students' => ['required', 'string', 'max:200'],
            'subject_staff' => ['required', 'string', 'max:200'],
            'body_students' => ['required', 'string', 'max:4000'],
            'body_staff' => ['required', 'string', 'max:4000'],
        ]);
        if (! Schema::hasTable('birthday_settings') || ! Schema::hasTable('birthday_deliveries')) {
            return back()->withErrors(['database' => 'Voer eerst de verjaardagsmigratie uit.'])->withInput();
        }
        if ((int) $data['school_id'] !== 0 && ! DB::table('scholen')->where('id', $data['school_id'])->exists()) {
            return back()->withErrors(['school_id' => 'Deze school bestaat niet.'])->withInput();
        }
        if (($data['email_students'] === '1' || $data['email_staff'] === '1')
            && in_array(config('mail.default'), ['log', 'array'], true)) {
            return back()->withErrors(['email_students' => 'Configureer eerst een echte Laravel-mailer voordat e-mail wordt ingeschakeld.'])->withInput();
        }

        DB::transaction(function () use ($data) {
            foreach ($data as $key => $value) {
                DB::table('birthday_settings')->updateOrInsert(
                    ['setting_key' => $key], ['setting_value' => trim((string) ($value ?? ''))]
                );
            }
        });
        return redirect()->route('beheer.birthdays.index', ['tab' => 'felicitaties'])
            ->with('success', 'Verjaardagsinstellingen opgeslagen. De volgende geplande uitvoering gebruikt deze instellingen.');
    }
}
