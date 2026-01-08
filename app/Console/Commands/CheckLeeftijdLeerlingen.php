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
    protected $signature = 'leerlingen:check-18 {--test}';
    protected $description = 'Controleer welke leerlingen vandaag 18 worden (+ waarschuwing 7 dagen vooraf)';

    public function handle(): int
    {
        $this->info('Check gestart…');
        $isTest = $this->option('test') === true;

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

        // Laad relaties mee
        $leerlingen = Leerling::with(['klas', 'school'])->get();

        // 7 dagen vooraf + vandaag
        $leerlingenBinnenWeek18 = $leerlingen->filter(fn ($l) => $l->wordtBinnen7Dagen18());
        $leerlingenVandaag18    = $leerlingen->filter(fn ($l) => $l->wordtVandaag18());

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

        /** @var SmartschoolSoap $ss */
        $ss = app(SmartschoolSoap::class);

        // Onderwerpen
        $onderwerpVooraf = "Belangrijk: je wordt binnenkort 18 — co-accounts worden uitgeschakeld";
        $onderwerpVandaagCo = "Belangrijk: aanpassing van je co-accounts nu je 18 jaar geworden bent!";
        $onderwerpSecretariaat = "Uitnodiging: even langs bij het leerlingensecretariaat";

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

            // Afzenders per school (vooraf is eerder “beleid/administratie” → secretariaat sender is logisch)
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
            $viewVoorafLeerlingSchool = "smartschool.berichten.scholen.{$school->id}.wordt18_vooraf";
            $viewVoorafLeerlingDefault = "smartschool.berichten.default.wordt18_vooraf";
            $viewVoorafLeerling = view()->exists($viewVoorafLeerlingSchool) ? $viewVoorafLeerlingSchool : $viewVoorafLeerlingDefault;

            $viewVoorafCoSchool = "smartschool.berichten.scholen.{$school->id}.wordt18_vooraf_coaccount";
            $viewVoorafCoDefault = "smartschool.berichten.default.wordt18_vooraf_coaccount";
            $viewVoorafCo = view()->exists($viewVoorafCoSchool) ? $viewVoorafCoSchool : $viewVoorafCoDefault;

            foreach ($groep as $leerling) {
                if (empty($leerling->gebruikersnaam)) {
                    Log::warning("VOORAF: Geen Smartschool gebruikersnaam voor leerling id={$leerling->id}");
                    $this->warn("VOORAF: Geen Smartschool gebruikersnaam voor {$leerling->voornaam} {$leerling->naam}");
                    continue;
                }

                // naar leerling (hoofdaccount)
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
                        0 // hoofdaccount
                    );

                    Log::info("VOORAF: bericht naar leerling {$leerling->gebruikersnaam} | sender={$senderSecretariaat}");
                } catch (\SoapFault $e) {
                    Log::error("VOORAF: fout bij bericht naar leerling {$leerling->gebruikersnaam}: ".$e->getMessage());
                    $this->error("VOORAF: fout bij leerling {$leerling->gebruikersnaam}: ".$e->getMessage());
                }

                // naar co-accounts (1..6)
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
                                $i // coaccount 1..6
                            );
                            Log::info("VOORAF: bericht naar co-account {$i} van {$leerling->gebruikersnaam}");
                        } catch (\SoapFault $e) {
                            // Vaak: coaccount bestaat niet → log als info en ga door
                            Log::info("VOORAF: co-account {$i} niet bereikbaar voor {$leerling->gebruikersnaam}: ".$e->getMessage());
                        }
                    }
                } catch (\Throwable $e) {
                    Log::error("VOORAF: fout bij co-account fase voor {$leerling->gebruikersnaam}: ".$e->getMessage());
                    $this->error("VOORAF: fout bij co-accounts {$leerling->gebruikersnaam}: ".$e->getMessage());
                }
            }
        }

        /**
         * 2) VANDAAG (18): disable coaccounts + 2 leerling-berichten + overzichtsmail per school
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

            $beleid = trim((string)($school->smartschool_verzender_beleid ?? ''));
            if ($beleid === '') $beleid = null;

            // Mail ontvangers per school
            $raw = (string)($school->ontvangers_overzicht_18 ?? '');
            $ontvangers = preg_split('/[;,]+/', $raw);
            $ontvangers = array_values(array_filter(array_map('trim', $ontvangers)));

            if (count($ontvangers) === 0) {
                Log::warning("VANDAAG: Geen ontvangers_overzicht_18 voor {$school->schoolnaam} (id={$school->id}).");
                $this->warn("VANDAAG: Geen mail-ontvangers ingesteld voor {$school->schoolnaam} → mail overgeslagen.");
            }

            // templates (per school + fallback)
            $viewCoSchool = "smartschool.berichten.scholen.{$school->id}.wordt18";
            $viewCoDefault = "smartschool.berichten.default.wordt18";
            $viewCo = view()->exists($viewCoSchool) ? $viewCoSchool : $viewCoDefault;

            $viewSecrSchool = "smartschool.berichten.scholen.{$school->id}.wordt18_secretariaat";
            $viewSecrDefault = "smartschool.berichten.default.wordt18_secretariaat";
            $viewSecr = view()->exists($viewSecrSchool) ? $viewSecrSchool : $viewSecrDefault;

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
                        Log::error("VANDAAG: Smartschoolactie mislukt voor {$leerling->gebruikersnaam}: ".$e->getMessage());
                        $this->error("VANDAAG: Smartschoolactie fout voor {$leerling->gebruikersnaam}: ".$e->getMessage());
                    }
                } else {
                    Log::warning("VANDAAG: Geen Smartschool gebruikersnaam voor leerling id={$leerling->id}");
                    $this->warn("VANDAAG: Geen Smartschool gebruikersnaam voor {$leerling->voornaam} {$leerling->naam}");
                }

                $payload[] = [
                    'voornaam'                          => $leerling->voornaam ?? '',
                    'naam'                              => $leerling->naam ?? '',
                    'klas'                              => $klasNaam,
                    'geboortedatum'                     => $leerling->geboortedatum,
                    'gebruikersnaam'                    => $leerling->gebruikersnaam,
                    'smartschool_bericht_verzonden'      => $berichtCoVerzonden,
                    'coaccounts_uitgeschakeld'          => $coaccountsUit,
                    'secretariaat_bericht_verzonden'    => $berichtSecrVerzonden,
                ];
            }

            // Overzichtsmail per school (fail-safe: mag nooit command crashen)
            if (count($ontvangers) > 0) {
                try {
                    Mail::to($ontvangers)->send(new LeerlingenWorden18($payload));
                    Log::info("Mail verzonden voor school {$school->schoolnaam} naar: " . implode(', ', $ontvangers));
                    $this->info("Mail verzonden voor {$school->schoolnaam}.");
                } catch (\Throwable $e) {
                    Log::error(
                        "MAIL FOUT (school={$school->schoolnaam}, id={$school->id}) naar [" . implode(', ', $ontvangers) . "]: "
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
}