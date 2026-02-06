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
        $leerlingen = Leerling::with(['klas', 'school'])->get();

        // 7 dagen vooraf + vandaag
        $leerlingenBinnenWeek18 = $leerlingen->filter(fn($l) => $l->wordtBinnen7Dagen18());
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
                $this->line("TEST (vooraf): {$l->voornaam} {$l->naam} wordt binnen 7 dagen 18 ({$l->geboortedatum?->format('Y-m-d')})");
            }
            foreach ($leerlingenVandaag18 as $l) {
                $this->line("TEST (vandaag): {$l->voornaam} {$l->naam} wordt vandaag 18 ({$l->geboortedatum?->format('Y-m-d')})");
            }
            $this->warn('TESTMODUS: geen mail/Smartschool en geen co-account wijzigingen.');
            Log::info('TESTMODUS: leerlingen:check-18 heeft niets verzonden of aangepast.');
            return self::SUCCESS;
        }

        // Onderwerpen
        $onderwerpVooraf       = "Belangrijk: je wordt binnenkort 18 — co-accounts worden uitgeschakeld";
        $onderwerpVandaagCo    = "Belangrijk: aanpassing van je co-accounts nu je 18 jaar geworden bent!";
        $onderwerpSecretariaat = "Uitnodiging: even langs bij het leerlingensecretariaat";
        $onderwerpOverzichtSs  = "Overzicht: leerlingen vandaag 18";

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

                // 1A: naar leerling (hoofdaccount)
                try {
                    $bodyLeerling = view($viewVoorafLeerling, [
                        'voornaam' => $leerling->voornaam,
                        'naam'     => $leerling->naam,
                        'school'   => $school,
                        'beleid'   => $beleid,
                    ])->render();

                    $ss->sendMessage(
                        $leerling->gebruikersnaam,
                        $onderwerpVooraf,
                        $bodyLeerling,
                        $senderSecretariaat,
                        false,
                        0
                    );

                    Log::info("VOORAF: bericht naar leerling {$leerling->gebruikersnaam} | sender={$senderSecretariaat}");
                } catch (\SoapFault $e) {
                    Log::error("VOORAF: fout bij bericht naar leerling {$leerling->gebruikersnaam}: " . $e->getMessage());
                    $this->error("VOORAF: fout bij leerling {$leerling->gebruikersnaam}: " . $e->getMessage());
                }

                // 1B: naar co-accounts (1..6)
                try {
                    $bodyCo = view($viewVoorafCo, [
                        'voornaam' => $leerling->voornaam,
                        'naam'     => $leerling->naam,
                        'school'   => $school,
                        'beleid'   => $beleid,
                    ])->render();

                    for ($i = 1; $i <= 6; $i++) {
                        try {
                            $ss->sendMessage(
                                $leerling->gebruikersnaam,
                                $onderwerpVooraf,
                                $bodyCo,
                                $senderSecretariaat,
                                false,
                                $i
                            );
                            Log::info("VOORAF: bericht naar co-account {$i} van {$leerling->gebruikersnaam}");
                        } catch (\SoapFault $e) {
                            Log::info("VOORAF: co-account {$i} niet bereikbaar voor {$leerling->gebruikersnaam}: " . $e->getMessage());
                        }
                    }
                } catch (\Throwable $e) {
                    Log::error("VOORAF: fout bij co-account fase voor {$leerling->gebruikersnaam}: " . $e->getMessage());
                    $this->error("VOORAF: fout bij co-accounts {$leerling->gebruikersnaam}: " . $e->getMessage());
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

                if (!empty($leerling->gebruikersnaam)) {
                    try {
                        // Bericht 1: uitleg + keuze (coaccounts)
                        $body1 = view($viewCo, [
                            'voornaam' => $leerling->voornaam,
                            'naam'     => $leerling->naam,
                            'school'   => $school,
                            'beleid'   => $beleid,
                        ])->render();

                        $ss->sendMessage(
                            $leerling->gebruikersnaam,
                            $onderwerpVandaagCo,
                            $body1,
                            $senderCoAccounts,
                            false,
                            0
                        );
                        $berichtCoVerzonden = true;

                        // Co-accounts uitschakelen
                        $ss->disableCoAccounts($leerling->gebruikersnaam);
                        $coaccountsUit = true;

                        // Bericht 2: secretariaat
                        $body2 = view($viewSecr, [
                            'voornaam' => $leerling->voornaam,
                            'naam'     => $leerling->naam,
                            'school'   => $school,
                        ])->render();

                        $ss->sendMessage(
                            $leerling->gebruikersnaam,
                            $onderwerpSecretariaat,
                            $body2,
                            $senderSecretariaat,
                            false,
                            0
                        );
                        $berichtSecrVerzonden = true;

                        Log::info("VANDAAG: Smartschool OK voor {$leerling->gebruikersnaam} | co-sender={$senderCoAccounts} | secr-sender={$senderSecretariaat}");
                    } catch (\SoapFault $e) {
                        Log::error("VANDAAG: Smartschoolactie mislukt voor {$leerling->gebruikersnaam}: " . $e->getMessage());
                        $this->error("VANDAAG: Smartschoolactie fout voor {$leerling->gebruikersnaam}: " . $e->getMessage());
                    }
                } else {
                    Log::warning("VANDAAG: Geen Smartschool gebruikersnaam voor leerling id={$leerling->id}");
                    $this->warn("VANDAAG: Geen Smartschool gebruikersnaam voor {$leerling->voornaam} {$leerling->naam}");
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
            if (count($ontvangersSs) > 0 && count($payload) > 0) {
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
                                $senderCoAccounts,
                                false,
                                0
                            );
                            Log::info("VANDAAG: Smartschool-overzicht verzonden naar {$ontvanger} (school={$school->id})");
                            $this->info("Smartschool-overzicht verzonden naar {$ontvanger}");
                        } catch (\SoapFault $e) {
                            Log::error("VANDAAG: SS-overzicht mislukt naar {$ontvanger} (school={$school->id}): " . $e->getMessage());
                            $this->error("SS-overzicht mislukt naar {$ontvanger}: " . $e->getMessage());
                        }
                    }
                } catch (\Throwable $e) {
                    Log::error("VANDAAG: SS-overzicht crash (school={$school->id}): " . get_class($e) . " - " . $e->getMessage());
                    $this->error("SS-overzicht crash voor {$school->schoolnaam}: " . $e->getMessage());
                }
            }

            // 2b) Overzichtsmail per school (fail-safe)
            if (count($ontvangersMail) > 0) {
                try {
                    Mail::to($ontvangersMail)->send(new LeerlingenWorden18($payload));
                    Log::info("Mail verzonden voor school {$school->schoolnaam} naar: " . implode(', ', $ontvangersMail));
                    $this->info("Mail verzonden voor {$school->schoolnaam}.");
                } catch (\Throwable $e) {
                    Log::error(
                        "MAIL FOUT (school={$school->schoolnaam}, id={$school->id}) naar [" . implode(', ', $ontvangersMail) . "]: "
                        . get_class($e) . " - " . $e->getMessage()
                    );
                    $this->error("MAIL FOUT voor {$school->schoolnaam}: " . $e->getMessage());
                    $this->warn("Command gaat verder (Smartschool acties zijn uitgevoerd).");
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
