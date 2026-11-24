<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Leerling;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\LeerlingenWorden18;
use App\Services\SmartschoolSoap;

class CheckLeeftijdLeerlingen extends Command
{
    protected $signature = 'leerlingen:check-18
                            {--test : Testmodus: niets verzenden/wijzigen, enkel loggen}
                            {--test-ss-overzicht : Test: verstuur enkel Smartschool-overzicht (geen mails/geen disable/geen leerling-berichten)}
                            {--dummy : Gebruik dummy-data (3 leerlingen) voor Smartschool-overzicht als er vandaag niemand 18 is}';

    protected $description = 'Controleer welke leerlingen vandaag 18 worden (+ waarschuwing 7 dagen vooraf), verstuurt Smartschool-berichten en overzichten per school';

    public function handle(): int
    {
        $this->info('Check gestart…');

        $warningSettings = app(\App\Services\BirthdaySettings::class)->all();
        $actionLog = app(\App\Services\Birthday18Actions::class);
        $isTest = $this->option('test') === true;
        $testSsOverzichtOnly = $this->option('test-ss-overzicht') === true;
        $useDummy = $this->option('dummy') === true;

        // Veiligheidschecks
        if (!method_exists(Leerling::class, 'wordtVandaag18')) {
            $this->error('Methode wordtVandaag18() bestaat niet op het Leerling-model.');
            Log::error('Methode wordtVandaag18() ontbreekt op Leerling.');
            return self::FAILURE;
        }
        if (!method_exists(Leerling::class, 'wordtBinnen7Dagen18')) {
            $this->error('Methode wordtBinnen7Dagen18() bestaat niet op het Leerling-model.');
            Log::error('Methode wordtBinnen7Dagen18() ontbreekt op Leerling.');
            return self::FAILURE;
        }

        /** @var SmartschoolSoap $ss */
        $ss = app(SmartschoolSoap::class);

        // Laad relaties mee
        $leerlingen = Leerling::query()->where('active', 1)->with(['klas', 'school'])->get();

        // 7 dagen vooraf + vandaag
        $warningDays = max(1, min(30, (int) ($warningSettings['warning_days'] ?? 7)));
        $warningTarget = now('Europe/Brussels')->addDays($warningDays)->toDateString();
        $leerlingenBinnenWeek18 = $warningSettings['warning_enabled'] === '1'
            && ($warningSettings['warning_student'] === '1' || $warningSettings['warning_coaccounts'] === '1')
            ? $leerlingen->filter(fn ($l) => $l->geboortedatum
                && $l->geboortedatum->copy()->addYears(18)->toDateString() === $warningTarget)
            : collect();
        $leerlingenVandaag18    = $leerlingen->filter(fn($l) => $l->wordtVandaag18());

        // === TEST: enkel Smartschool-overzicht ===
        if ($testSsOverzichtOnly) {
            $this->warn('TEST-SS-OVERZICHT: er worden géén mails verstuurd, géén co-accounts gewijzigd, géén leerling-berichten verstuurd.');
            return $this->sendSmartschoolOverzichtOnly($ss, $leerlingenVandaag18, $useDummy);
        }

        // Als niets te doen
        if ($leerlingenBinnenWeek18->count() === 0 && $leerlingenVandaag18->count() === 0) {
            Log::info('Geen leerlingen worden vandaag 18 en geen leerlingen worden binnen 7 dagen 18.');
            $this->info('Geen leerlingen worden vandaag 18 en geen leerlingen worden binnen 7 dagen 18.');
            return self::SUCCESS;
        }

        // Testmodus: niets verzenden/wijzigen
        if ($isTest) {
            foreach ($leerlingenBinnenWeek18 as $l) {
                $this->line("TEST (vooraf): {$l->voornaam} {$l->naam} wordt binnen {$warningDays} dagen 18 ({$l->geboortedatum?->format('Y-m-d')})");
            }
            foreach ($leerlingenVandaag18 as $l) {
                $this->line("TEST (vandaag): {$l->voornaam} {$l->naam} wordt vandaag 18 ({$l->geboortedatum?->format('Y-m-d')})");
            }
            $this->warn('TESTMODUS: geen mail/Smartschool en geen co-account wijzigingen.');
            Log::info('TESTMODUS: leerlingen:check-18 heeft niets verzonden of aangepast.');
            return self::SUCCESS;
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('birthday_18_actions')) {
            $this->error('De veilige 18-jarigenactielog ontbreekt. Voer eerst de gerichte 18-jarigenmigratie uit.');
            return self::FAILURE;
        }

        // Onderwerpen
        $onderwerpVooraf = str_replace('{dagen}', (string) $warningDays,
            (string) ($warningSettings['warning_subject']
                ?? 'Belangrijk: je wordt binnenkort 18 — co-accounts worden uitgeschakeld'));
        $onderwerpVandaagCo = $warningSettings['adult_subject_student'];
        $onderwerpSecretariaat = $warningSettings['adult_subject_secretariat'];
        $onderwerpOverzichtSs = $warningSettings['adult_overview_smartschool_subject'];

        /**
         * 1) VOORAF (7 dagen): berichten naar leerling + coaccounts
         */
        $perSchoolVooraf = $leerlingenBinnenWeek18->groupBy('schoolid');

        foreach ($perSchoolVooraf as $schoolId => $groep) {
            $school = $groep->first()->school;

            if (!$school) {
                Log::warning("VOORAF: leerlingen met schoolid={$schoolId} maar school ontbreekt. Overgeslagen.");
                $this->warn("VOORAF: School ontbreekt voor schoolid={$schoolId} → overgeslagen.");
                continue;
            }

            // Afzender vooraf: secretariaat > verzender > env fallback
            $senderSecretariaat = trim((string)($school->smartschool_verzender_secretariaat ?? ''));
            if ($senderSecretariaat === '') {
                $senderSecretariaat = trim((string)($school->smartschool_verzender ?? ''));
            }
            if ($senderSecretariaat === '') {
                $senderSecretariaat = env('SMARTSCHOOL_SENDER_USER') ?: 'lvs';
            }
            if (trim((string) ($warningSettings['warning_sender'] ?? '')) !== '') {
                $senderSecretariaat = trim((string) $warningSettings['warning_sender']);
            }

            $beleid = trim((string)($school->smartschool_verzender_beleid ?? ''));
            if ($beleid === '') $beleid = null;

            // Templates (per school + fallback)
            $viewVoorafLeerling = $this->pickView(
                "smartschool.berichten.scholen.{$school->id}.wordt18_vooraf",
                "smartschool.berichten.default.wordt18_vooraf"
            );

            $viewVoorafCo = $this->pickView(
                "smartschool.berichten.scholen.{$school->id}.wordt18_vooraf_coaccount",
                "smartschool.berichten.default.wordt18_vooraf_coaccount"
            );

            foreach ($groep as $leerling) {
                if (empty($leerling->gebruikersnaam)) {
                    Log::warning("VOORAF: Geen Smartschool gebruikersnaam voor leerling id={$leerling->id}");
                    $this->warn("VOORAF: Geen Smartschool gebruikersnaam voor {$leerling->voornaam} {$leerling->naam}");
                    continue;
                }

                $targetDate = $leerling->geboortedatum->copy()->addYears(18)->toDateString();
                $studentData = ['voornaam' => $leerling->voornaam, 'naam' => $leerling->naam,
                    'school' => $school->schoolnaam, 'dagen' => $warningDays];
                if ($warningSettings['warning_student'] === '1') {
                    $sent = $actionLog->run((int) $school->id, (int) $leerling->id, $targetDate,
                        'advance_student', (string) $leerling->gebruikersnaam,
                        function () use ($ss, $leerling, $viewVoorafLeerling, $school, $beleid,
                            $warningSettings, $warningDays, $studentData, $onderwerpVooraf, $senderSecretariaat) {
                            $body = view($viewVoorafLeerling, [
                                'voornaam' => $leerling->voornaam, 'naam' => $leerling->naam,
                                'school' => $school, 'beleid' => $beleid,
                            ])->render();
                            if (trim((string) $warningSettings['warning_body_student']) !== '') {
                                $body = nl2br(e(app(\App\Services\BirthdaySettings::class)->render(
                                    $warningSettings['warning_body_student'], $studentData)));
                            } elseif ($warningDays !== 7) {
                                $body = str_replace(['Binnen een week', 'binnen een week'],
                                    ['Binnen '.$warningDays.' dagen', 'binnen '.$warningDays.' dagen'], $body);
                            }
                            $ss->sendMessage($leerling->gebruikersnaam, $onderwerpVooraf,
                                $body, $senderSecretariaat, false, 0);
                        });
                    if (! $sent) $this->warn('Voorafbericht leerling nog niet bevestigd: '.$leerling->gebruikersnaam);
                }
                if ($warningSettings['warning_coaccounts'] === '1') {
                    $coBody = view($viewVoorafCo, [
                        'voornaam' => $leerling->voornaam, 'naam' => $leerling->naam,
                        'school' => $school, 'beleid' => $beleid,
                    ])->render();
                    if (trim((string) $warningSettings['warning_body_coaccount']) !== '') {
                        $coBody = nl2br(e(app(\App\Services\BirthdaySettings::class)->render(
                            $warningSettings['warning_body_coaccount'], $studentData)));
                    } elseif ($warningDays !== 7) {
                        $coBody = str_replace(['Binnen een week', 'binnen een week'],
                            ['Binnen '.$warningDays.' dagen', 'binnen '.$warningDays.' dagen'], $coBody);
                    }
                    for ($i = 1; $i <= 6; $i++) {
                        $actionLog->run((int) $school->id, (int) $leerling->id, $targetDate,
                            'advance_coaccount_'.$i, $leerling->gebruikersnaam.' (co-account '.$i.')',
                            function () use ($ss, $leerling, $onderwerpVooraf, $coBody, $senderSecretariaat, $i) {
                                $ss->sendMessage($leerling->gebruikersnaam, $onderwerpVooraf,
                                    $coBody, $senderSecretariaat, false, $i);
                            });
                    }
                }
            }
        }

        /**
         * 2) VANDAAG (18): disable coaccounts + 2 leerling-berichten + overzichtsmail + Smartschool-overzicht per school
         */
        $perSchoolVandaag = $leerlingenVandaag18->groupBy('schoolid');

        foreach ($perSchoolVandaag as $schoolId => $groep) {
            $school = $groep->first()->school;

            if (!$school) {
                Log::warning("VANDAAG: leerlingen met schoolid={$schoolId} maar school ontbreekt. Overgeslagen.");
                $this->warn("VANDAAG: School ontbreekt voor schoolid={$schoolId} → overgeslagen.");
                continue;
            }

            // senders per school
            $senderCoAccounts = trim((string)($school->smartschool_verzender ?? ''));
            if ($senderCoAccounts === '') {
                $senderCoAccounts = env('SMARTSCHOOL_SENDER_USER') ?: 'lvs';
            }

            $senderSecretariaat = trim((string)($school->smartschool_verzender_secretariaat ?? ''));
            if ($senderSecretariaat === '') {
                $senderSecretariaat = $senderCoAccounts;
            }

            // Voor Smartschool-overzicht: liever beleid als afzender, anders co-sender
            $senderBeleid = trim((string)($school->smartschool_verzender_beleid ?? ''));
            if ($senderBeleid === '') {
                $senderBeleid = $senderCoAccounts;
            }

            $beleid = trim((string)($school->smartschool_verzender_beleid ?? ''));
            if ($beleid === '') $beleid = null;

            // Mail ontvangers per school
            $ontvangersMail = $this->splitList((string)($school->ontvangers_overzicht_18 ?? ''));

            if (count($ontvangersMail) === 0) {
                Log::warning("VANDAAG: Geen ontvangers_overzicht_18 voor {$school->schoolnaam} (id={$school->id}).");
                $this->warn("VANDAAG: Geen mail-ontvangers ingesteld voor {$school->schoolnaam} → mail overgeslagen.");
            }

            // Smartschool ontvangers: scholen.ontvangers_overzicht_18_ssaccount
            $ontvangersSs = $this->splitList((string)($school->ontvangers_overzicht_18_ssaccount ?? ''));

            if (count($ontvangersSs) === 0) {
                Log::warning("VANDAAG: Geen ontvangers_overzicht_18_ssaccount voor {$school->schoolnaam} (id={$school->id}).");
                $this->warn("VANDAAG: Geen Smartschool-overzicht ontvangers ingesteld voor {$school->schoolnaam} → SS-overzicht overgeslagen.");
            }

            // templates (per school + fallback)
            $viewCo = $this->pickView(
                "smartschool.berichten.scholen.{$school->id}.wordt18",
                "smartschool.berichten.default.wordt18"
            );

            $viewSecr = $this->pickView(
                "smartschool.berichten.scholen.{$school->id}.wordt18_secretariaat",
                "smartschool.berichten.default.wordt18_secretariaat"
            );

            $viewOverzicht = $this->pickView(
                "smartschool.berichten.scholen.{$school->id}.overzicht_worden18",
                "smartschool.berichten.default.overzicht_worden18"
            );

            $payload = [];

            foreach ($groep as $leerling) {
                $klasNaam =
                    $leerling->klasnaam
                    ?? ($leerling->klas['klasnaam'] ?? null)
                    ?? ($leerling->klas?->klasnaam ?? null)
                    ?? (is_string($leerling->klas) ? $leerling->klas : null)
                    ?? 'onbekend';

                $msg = "{$leerling->voornaam} {$leerling->naam} (klas {$klasNaam}) wordt vandaag 18 ({$leerling->geboortedatum?->format('Y-m-d')})";
                Log::info($msg);
                $this->line($msg);

                $berichtCoVerzonden = false;
                $coaccountsUit = false;
                $berichtSecrVerzonden = false;
                $actionDate = now('Europe/Brussels')->toDateString();
                $schoolId = (int) $school->id;
                $studentId = (int) $leerling->id;
                $username = trim((string) ($leerling->gebruikersnaam ?? ''));
                $vars = ['voornaam' => $leerling->voornaam, 'naam' => $leerling->naam,
                    'school' => $school->schoolnaam];
                if ($username !== '') {
                    // PRIVACY FIRST: never make co-account deactivation depend on sending a message.
                    $coaccountsUit = $actionLog->run($schoolId, $studentId, $actionDate,
                        'disable_coaccounts', $username,
                        fn () => $ss->disableCoAccounts($username));
                    if (! $coaccountsUit) {
                        $this->error('Controle co-accounts vereist: '.$username.' (zie actiehistoriek).');
                    }

                    // Do not claim deactivation succeeded if Smartschool reported partial failure.
                    if ($coaccountsUit && $warningSettings['adult_student_notice'] === '1') {
                        $sender = trim((string) $warningSettings['adult_sender_student']) ?: $senderCoAccounts;
                        $berichtCoVerzonden = $actionLog->run($schoolId, $studentId, $actionDate,
                            'adult_student_notice', $username,
                            function () use ($ss, $username, $viewCo, $leerling, $school, $beleid,
                                $warningSettings, $vars, $onderwerpVandaagCo, $sender) {
                                $body = trim((string) $warningSettings['adult_body_student']) !== ''
                                    ? nl2br(e(app(\App\Services\BirthdaySettings::class)
                                        ->render($warningSettings['adult_body_student'], $vars)))
                                    : view($viewCo, ['voornaam' => $leerling->voornaam, 'naam' => $leerling->naam,
                                        'school' => $school, 'beleid' => $beleid])->render();
                                $ss->sendMessage($username, $onderwerpVandaagCo, $body, $sender, false, 0);
                            });
                    }

                    if ($warningSettings['adult_secretariat_notice'] === '1') {
                        $sender = trim((string) $warningSettings['adult_sender_secretariat']) ?: $senderSecretariaat;
                        $berichtSecrVerzonden = $actionLog->run($schoolId, $studentId, $actionDate,
                            'adult_secretariat_notice', $username,
                            function () use ($ss, $username, $viewSecr, $leerling, $school,
                                $warningSettings, $vars, $onderwerpSecretariaat, $sender) {
                                $body = trim((string) $warningSettings['adult_body_secretariat']) !== ''
                                    ? nl2br(e(app(\App\Services\BirthdaySettings::class)
                                        ->render($warningSettings['adult_body_secretariat'], $vars)))
                                    : view($viewSecr, ['voornaam' => $leerling->voornaam, 'naam' => $leerling->naam,
                                        'school' => $school])->render();
                                $ss->sendMessage($username, $onderwerpSecretariaat, $body, $sender, false, 0);
                            });
                    }
                } else {
                    $actionLog->flag($schoolId, $studentId, $actionDate,
                        'disable_coaccounts', 'Geen Smartschool-gebruikersnaam; handmatige controle nodig.');
                    Log::warning('18-jaar: geen Smartschool-gebruikersnaam voor leerling '.$studentId);
                    $this->error('Handmatige opvolging vereist: ontbrekende gebruikersnaam voor '.$leerling->voornaam.' '.$leerling->naam);
                }

                $payload[] = [
                    'voornaam'                           => $leerling->voornaam ?? '',
                    'naam'                               => $leerling->naam ?? '',
                    'klas'                               => $klasNaam,
                    'geboortedatum'                      => $leerling->geboortedatum,
                    'gebruikersnaam'                     => $leerling->gebruikersnaam,
                    'smartschool_bericht_verzonden'       => $berichtCoVerzonden,
                    'coaccounts_uitgeschakeld'           => $coaccountsUit,
                    'secretariaat_bericht_verzonden'      => $berichtSecrVerzonden,
                ];
            }

            // 2a) Smartschool-overzicht per school (fail-safe)
            if ($warningSettings['adult_overview_smartschool_enabled'] === '1'
                && count($ontvangersSs) > 0 && count($payload) > 0) {
                try {
                    $bodyOverzicht = view($viewOverzicht, [
                        'school'  => $school,
                        'payload' => $payload,
                    ])->render();

                    foreach ($ontvangersSs as $ontvanger) {
                        $senderOverview = trim((string) $warningSettings['adult_overview_sender'])
                            ?: $senderCoAccounts;
                        $overviewKey = 'overview_ss_'.substr(hash('sha256', mb_strtolower($ontvanger)), 0, 32);
                        $ok = $actionLog->run((int) $school->id, 0,
                            now('Europe/Brussels')->toDateString(), $overviewKey, $ontvanger,
                            function () use ($ss, $ontvanger, $onderwerpOverzichtSs, $bodyOverzicht, $senderOverview) {
                                $ss->sendMessage($ontvanger, $onderwerpOverzichtSs,
                                    $bodyOverzicht, $senderOverview, false, 0);
                            });
                        if (! $ok) $this->warn('Smartschool-overzicht niet bevestigd: '.$ontvanger);
                    }
                } catch (\Throwable $e) {
                    Log::error("VANDAAG: SS-overzicht crash (school={$school->id}): " . get_class($e) . " - " . $e->getMessage());
                    $this->error("SS-overzicht crash voor {$school->schoolnaam}: " . $e->getMessage());
                }
            }

            // 2b) Overzichtsmail per school (fail-safe)
            if ($warningSettings['adult_overview_email_enabled'] === '1' && count($ontvangersMail) > 0) {
                $subjectEmail = trim((string) $warningSettings['adult_overview_email_subject']);
                // Keep the existing template/subject when the setting is empty.
                $mailDate = now('Europe/Brussels')->toDateString();
                foreach ($ontvangersMail as $ontvanger) {
                    if (! filter_var($ontvanger, FILTER_VALIDATE_EMAIL)) {
                        $this->warn('Ongeldig overzichtsmailadres: '.$ontvanger);
                        continue;
                    }
                    $actionKey = 'overview_email_'.substr(hash('sha256', mb_strtolower($ontvanger)), 0, 30);
                    $ok = $actionLog->run((int) $school->id, 0, $mailDate,
                        $actionKey, $ontvanger, function () use ($ontvanger, $payload, $subjectEmail) {
                            Mail::to($ontvanger)->send(new LeerlingenWorden18($payload, $subjectEmail));
                        });
                    if (! $ok) $this->warn('E-mailoverzicht niet bevestigd: '.$ontvanger);
                }
            }
        }

        $this->info('Klaar.');
        return self::SUCCESS;
    }

    /**
     * TEST-helper: enkel Smartschool-overzicht sturen (zonder andere acties).
     * Met --dummy: maak 3 dummy leerlingen zodat je layout kan testen.
     */
    private function sendSmartschoolOverzichtOnly(SmartschoolSoap $ss, $leerlingenVandaag18, bool $useDummy): int
    {
        if ($leerlingenVandaag18->count() === 0 && !$useDummy) {
            $this->warn('Niemand wordt vandaag 18. Gebruik --dummy om toch een testbericht te sturen.');
            return self::SUCCESS;
        }

        // Als er echte leerlingen zijn vandaag: normaal groeperen
        $perSchoolVandaag = $leerlingenVandaag18->groupBy('schoolid');

        // Dummy: maak 3 “virtuele” leerlingen op basis van 1 bestaande school
        if ($leerlingenVandaag18->count() === 0 && $useDummy) {
            $anchor = Leerling::with(['klas', 'school'])->whereNotNull('schoolid')->first();

            if (!$anchor || !$anchor->school) {
                $this->error('Geen leerling/school gevonden om dummy test mee te maken.');
                return self::FAILURE;
            }

            $schoolId = $anchor->schoolid;

            $perSchoolVandaag = collect([
                $schoolId => collect([$anchor]) // we gebruiken anchor enkel om school/klas te kennen
            ]);
        }

        $onderwerpOverzichtSs = "TEST Overzicht: leerlingen vandaag 18";

        foreach ($perSchoolVandaag as $schoolId => $groep) {
            // In dummy-mode is $groep 1 anchor leerling
            $school = $groep->first()->school ?? null;

            if (!$school) {
                $this->warn("TEST-SS: school ontbreekt voor schoolid={$schoolId}");
                continue;
            }

            $ontvangersSs = $this->splitList((string)($school->ontvangers_overzicht_18_ssaccount ?? ''));
            if (count($ontvangersSs) === 0) {
                $this->warn("TEST-SS: geen ontvangers_overzicht_18_ssaccount ingesteld voor {$school->schoolnaam}");
                continue;
            }

            // Afzender: zelfde logica als 'vandaag 18' → co-accounts verzender
            $sender = trim((string)($school->smartschool_verzender ?? ''));
            if ($sender === '') {
                $sender = env('SMARTSCHOOL_SENDER_USER') ?: 'lvs';
            }

            $viewOverzicht = $this->pickView(
                "smartschool.berichten.scholen.{$school->id}.overzicht_worden18",
                "smartschool.berichten.default.overzicht_worden18"
            );

            // Payload
            $payload = [];

            if ($leerlingenVandaag18->count() === 0 && $useDummy) {
                // 3 dummy leerlingen
                $payload = [
                    [
                        'voornaam' => 'Test',
                        'naam' => 'Leerling Eén',
                        'klas' => $groep->first()->klas?->klasnaam ?? '3 EL',
                        'geboortedatum' => now('Europe/Brussels')->subYears(18),
                        'gebruikersnaam' => 'dummy.leerling1',
                        'smartschool_bericht_verzonden' => false,
                        'coaccounts_uitgeschakeld' => false,
                        'secretariaat_bericht_verzonden' => false,
                    ],
                    [
                        'voornaam' => 'Test',
                        'naam' => 'Leerling Twee',
                        'klas' => $groep->first()->klas?->klasnaam ?? '3 EL',
                        'geboortedatum' => now('Europe/Brussels')->subYears(18)->subDays(1),
                        'gebruikersnaam' => 'dummy.leerling2',
                        'smartschool_bericht_verzonden' => true,
                        'coaccounts_uitgeschakeld' => true,
                        'secretariaat_bericht_verzonden' => true,
                    ],
                    [
                        'voornaam' => 'Test',
                        'naam' => 'Leerling Drie',
                        'klas' => $groep->first()->klas?->klasnaam ?? '3 EL',
                        'geboortedatum' => now('Europe/Brussels')->subYears(18)->subDays(2),
                        'gebruikersnaam' => 'dummy.leerling3',
                        'smartschool_bericht_verzonden' => true,
                        'coaccounts_uitgeschakeld' => false,
                        'secretariaat_bericht_verzonden' => true,
                    ],
                ];
            } else {
                foreach ($groep as $leerling) {
                    $klasNaam =
                        $leerling->klasnaam
                        ?? ($leerling->klas['klasnaam'] ?? null)
                        ?? ($leerling->klas?->klasnaam ?? null)
                        ?? (is_string($leerling->klas) ? $leerling->klas : null)
                        ?? 'onbekend';

                    $payload[] = [
                        'voornaam' => $leerling->voornaam ?? '',
                        'naam' => $leerling->naam ?? '',
                        'klas' => $klasNaam,
                        'geboortedatum' => $leerling->geboortedatum,
                        'gebruikersnaam' => $leerling->gebruikersnaam,
                        'smartschool_bericht_verzonden' => false,
                        'coaccounts_uitgeschakeld' => false,
                        'secretariaat_bericht_verzonden' => false,
                    ];
                }
            }

            try {
                $bodyOverzicht = view($viewOverzicht, [
                    'school'  => $school,
                    'payload' => $payload,
                ])->render();

                foreach ($ontvangersSs as $ontvanger) {
                    try {
                        $ss->sendMessage(
                            $ontvanger,
                            $onderwerpOverzichtSs,
                            $bodyOverzicht,
                            $sender,
                            false,
                            0
                        );
                        $this->info("TEST-SS: overzicht verstuurd naar {$ontvanger} (school={$school->id})");
                        Log::info("TEST-SS: overzicht verstuurd naar {$ontvanger} (school={$school->id})");
                    } catch (\SoapFault $e) {
                        $this->error("TEST-SS: fout naar {$ontvanger}: " . $e->getMessage());
                        Log::error("TEST-SS: fout naar {$ontvanger}: " . $e->getMessage());
                    }
                }
            } catch (\Throwable $e) {
                $this->error("TEST-SS: crash bij opbouw/verzenden overzicht: " . $e->getMessage());
                Log::error("TEST-SS: crash: " . get_class($e) . " - " . $e->getMessage());
            }
        }

        return self::SUCCESS;
    }

    private function pickView(string $preferred, string $fallback): string
    {
        return view()->exists($preferred) ? $preferred : $fallback;
    }

    private function splitList(string $raw): array
    {
        $items = preg_split('/[;,]+/', $raw);
        $items = array_map('trim', $items ?: []);
        $items = array_values(array_filter($items, fn($v) => $v !== ''));
        return $items;
    }
}
