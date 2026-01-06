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
    protected $description = 'Controleer welke leerlingen vandaag 18 worden';

    public function handle(): int
    {
        $this->info('Check gestart…');
        $isTest = $this->option('test') === true;

        // Veiligheidscheck 1x i.p.v. in de loop
        if (!method_exists(Leerling::class, 'wordtVandaag18')) {
            $this->error('Methode wordtVandaag18() bestaat niet op het Leerling-model.');
            Log::error('Methode wordtVandaag18() ontbreekt op Leerling.');
            return self::FAILURE;
        }

        // Laad relaties mee (klas + school)
        $leerlingen = Leerling::with(['klas', 'school'])->get();

        // Filter leerlingen die vandaag 18 worden
        $leerlingenVandaag18 = $leerlingen->filter(fn ($l) => $l->wordtVandaag18());

        if ($leerlingenVandaag18->count() === 0) {
            Log::info('Geen leerlingen worden vandaag 18.');
            $this->info('Geen leerlingen worden vandaag 18.');
            return self::SUCCESS;
        }

        // Testmodus: niets verzenden
        if ($isTest) {
            foreach ($leerlingenVandaag18 as $leerling) {
                $this->line("TEST: {$leerling->voornaam} {$leerling->naam} wordt vandaag 18 ({$leerling->geboortedatum?->format('Y-m-d')})");
            }
            $this->warn('TESTMODUS: geen mail of Smartschoolberichten verzonden.');
            Log::info('TESTMODUS: leerlingen:check-18 heeft geen mail/Smartschoolberichten verzonden.');
            return self::SUCCESS;
        }

        /** @var SmartschoolSoap $ss */
        $ss = app(SmartschoolSoap::class);

        // Onderwerpen (2 berichten)
        $onderwerpCoAccounts     = "Belangrijk: aanpassing van je co-accounts nu je 18 jaar geworden bent!";
        $onderwerpSecretariaat   = "Uitnodiging: even langs bij het leerlingensecretariaat";

        // Groepeer per schoolid
        $perSchool = $leerlingenVandaag18->groupBy('schoolid');

        foreach ($perSchool as $schoolId => $groep) {

            $school = $groep->first()->school;

            if (!$school) {
                Log::warning("Leerlingen met schoolid={$schoolId} maar school ontbreekt. Mail/Smartschool wordt overgeslagen.");
                $this->warn("School ontbreekt voor schoolid={$schoolId} → overgeslagen.");
                continue;
            }

            // ✅ Afzenders per school
            $senderCoAccounts = trim((string)($school->smartschool_verzender ?? ''));
            if ($senderCoAccounts === '') {
                $senderCoAccounts = env('SMARTSCHOOL_SENDER_USER') ?: 'lvs';
            }

            $senderSecretariaat = trim((string)($school->smartschool_verzender_secretariaat ?? ''));
            if ($senderSecretariaat === '') {
                // fallback: als secretariaat niet ingevuld is, gebruik co-account sender
                $senderSecretariaat = $senderCoAccounts;
            }

            // ✅ Beleid (voor later) – geven we al mee aan Blade
            $beleid = trim((string)($school->smartschool_verzender_beleid ?? ''));
            if ($beleid === '') {
                $beleid = null;
            }

            Log::info(
                "School {$school->schoolnaam} (id={$school->id}) → sender_co={$senderCoAccounts} | sender_secr={$senderSecretariaat}"
                . ($beleid ? " | beleid={$beleid}" : '')
            );

            // Ontvangers uit scholen.ontvangers_overzicht_18 (CSV/; gescheiden)
            $raw = (string) ($school->ontvangers_overzicht_18 ?? '');
            $ontvangers = preg_split('/[;,]+/', $raw);
            $ontvangers = array_values(array_filter(array_map('trim', $ontvangers)));

            if (count($ontvangers) === 0) {
                Log::warning("Geen ontvangers_overzicht_18 ingesteld voor school {$school->schoolnaam} (id={$school->id}).");
                $this->warn("Geen mail-ontvangers ingesteld voor {$school->schoolnaam} → mail wordt overgeslagen.");
            }

            // ✅ Templates per school met fallback
            $viewCoSchool = "smartschool.berichten.scholen.{$school->id}.wordt18";
            $viewCoDefault = "smartschool.berichten.default.wordt18";
            $viewCoAccounts = view()->exists($viewCoSchool) ? $viewCoSchool : $viewCoDefault;

            $viewSecrSchool = "smartschool.berichten.scholen.{$school->id}.wordt18_secretariaat";
            $viewSecrDefault = "smartschool.berichten.default.wordt18_secretariaat";
            $viewSecretariaat = view()->exists($viewSecrSchool) ? $viewSecrSchool : $viewSecrDefault;

            // Payload voor mail
            $payload = [];

            foreach ($groep as $leerling) {

                // Klasnaam normaliseren: kolom -> JSON -> relation -> string -> fallback
                $klasNaam =
                    $leerling->klasnaam
                    ?? ($leerling->klas['klasnaam'] ?? null)
                    ?? ($leerling->klas?->klasnaam ?? null)
                    ?? (is_string($leerling->klas) ? $leerling->klas : null)
                    ?? 'onbekend';

                $msg = "{$leerling->voornaam} {$leerling->naam} (klas {$klasNaam}) wordt vandaag 18 ({$leerling->geboortedatum?->format('Y-m-d')})";
                Log::info($msg);
                $this->line($msg);

                // Flags (voor overzichtsmail)
                $smartschoolBerichtVerzonden = false;
                $coaccountsUitgeschakeld = false;
                $secretariaatBerichtVerzonden = false;

                // Smartschool acties
                if (!empty($leerling->gebruikersnaam)) {

                    try {
                        // -------- Bericht 1: co-accounts --------
                        $body1 = view($viewCoAccounts, [
                            'voornaam' => $leerling->voornaam,
                            'naam'     => $leerling->naam,
                            'school'   => $school,
                            'beleid'   => $beleid,
                        ])->render();

                        $ss->sendMessage(
                            $leerling->gebruikersnaam,
                            $onderwerpCoAccounts,
                            $body1,
                            $senderCoAccounts,
                            false
                        );

                        $smartschoolBerichtVerzonden = true;
                        Log::info("Smartschool (18/coaccounts) verzonden naar {$leerling->gebruikersnaam} | sender={$senderCoAccounts}");

                        // Co-accounts uitschakelen
                        $ss->disableCoAccounts($leerling->gebruikersnaam);
                        $coaccountsUitgeschakeld = true;

                        Log::info("Co-accounts uitgeschakeld voor {$leerling->gebruikersnaam}");
                        $this->info("Co-accounts uitgeschakeld voor {$leerling->voornaam} {$leerling->naam}");

                        // -------- Bericht 2: secretariaat --------
                        $body2 = view($viewSecretariaat, [
                            'voornaam' => $leerling->voornaam,
                            'naam'     => $leerling->naam,
                            'school'   => $school,
                        ])->render();

                        $ss->sendMessage(
                            $leerling->gebruikersnaam,
                            $onderwerpSecretariaat,
                            $body2,
                            $senderSecretariaat,
                            false
                        );

                        $secretariaatBerichtVerzonden = true;
                        Log::info("Smartschool (18/secretariaat) verzonden naar {$leerling->gebruikersnaam} | sender={$senderSecretariaat}");

                    } catch (\SoapFault $e) {
                        Log::error("Smartschoolactie mislukt voor {$leerling->gebruikersnaam}: " . $e->getMessage());
                        $this->error("Fout bij Smartschoolactie voor {$leerling->gebruikersnaam}: " . $e->getMessage());
                    }

                } else {
                    Log::warning("Geen Smartschool gebruikersnaam voor {$leerling->voornaam} {$leerling->naam} (id={$leerling->id})");
                    $this->warn("Geen Smartschool gebruikersnaam voor {$leerling->voornaam} {$leerling->naam}");
                }

                $payload[] = [
                    'voornaam'                          => $leerling->voornaam ?? '',
                    'naam'                              => $leerling->naam ?? '',
                    'klas'                              => $klasNaam,
                    'geboortedatum'                     => $leerling->geboortedatum,
                    'gebruikersnaam'                    => $leerling->gebruikersnaam,
                    'smartschool_bericht_verzonden'      => $smartschoolBerichtVerzonden,
                    'coaccounts_uitgeschakeld'          => $coaccountsUitgeschakeld,
                    'secretariaat_bericht_verzonden'    => $secretariaatBerichtVerzonden,
                ];
            }

            // Mail per school (fail-safe: nooit crashen op SMTP)
            if (count($ontvangers) > 0) {
                try {
                    Mail::to($ontvangers)->send(new LeerlingenWorden18($payload));

                    Log::info("Mail verzonden voor school {$school->schoolnaam} naar: " . implode(', ', $ontvangers));
                    $this->info("Mail verzonden voor {$school->schoolnaam}.");

                } catch (\Throwable $e) {
                    // ✅ Blijven doorgaan, enkel loggen/tonen
                    Log::error(
                        "MAIL FOUT (school={$school->schoolnaam}, id={$school->id}) naar [" . implode(', ', $ontvangers) . "]: "
                        . get_class($e) . " - " . $e->getMessage()
                    );

                    $this->error("MAIL FOUT voor {$school->schoolnaam}: " . $e->getMessage());
                    $this->warn("Command gaat verder (Smartschool acties zijn uitgevoerd).");
                }
            } else {
                // Optioneel: ook loggen dat er niemand ingesteld is
                Log::warning("Geen mail-ontvangers ingesteld voor school {$school->schoolnaam} (id={$school->id}).");
            }

        }

        $this->info('Klaar.');
        return self::SUCCESS;
    }
}
