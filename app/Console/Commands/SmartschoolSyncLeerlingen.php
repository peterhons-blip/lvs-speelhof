<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Services\SmartschoolSoap;
use App\Models\Leerling;
use App\Models\Klas;
use App\Models\School;

class SmartschoolSyncLeerlingen extends Command
{
    protected $signature = 'smartschool:sync-leerlingen
                            {--schoolid= : School ID (default: 1)}
                            {--dry-run : Niets schrijven in DB, enkel loggen}
                            {--limit=0 : Maximum # leerlingen om te verwerken (0 = geen limiet)}
                            {--only-class= : Alleen deze smartschool_code syncen (vb. officlass_xxx)}';

    protected $description = 'Synct leerlingen uit Smartschool (per klascode) naar DB: upsert, active vlag, klas auto-aanmaken.';

    public function handle(): int
    {
        $schoolId = (int)($this->option('schoolid') ?: 1);
        $dryRun   = $this->option('dry-run') === true;
        $limit    = (int)($this->option('limit') ?: 0);
        $onlyCode = trim((string)($this->option('only-class') ?: ''));

        $this->info("Smartschool sync leerlingen gestart (schoolid={$schoolId})".($dryRun ? " [DRY-RUN]" : ""));
        Log::info("Smartschool sync leerlingen gestart (schoolid={$schoolId})".($dryRun ? " [DRY-RUN]" : ""));

        $school = School::find($schoolId);
        if (!$school) {
            $this->error("School {$schoolId} niet gevonden.");
            return self::FAILURE;
        }

        // Verwacht dat je per school wsdl/accesscode kolommen hebt (zoals je eerder wou).
        // Als je die nog niet hebt: zet dan tijdelijk in config/services + gebruik defaults.
        $wsdl = $school->smartschool_wsdl ?? config('services.smartschool.wsdl');
        $accesscode = $school->smartschool_accesscode ?? config('services.smartschool.accesscode');

        $ss = new SmartschoolSoap($wsdl, $accesscode);

        // Probeer namenmap (optioneel)
        $codeToName = [];
        try {
            $codeToName = $ss->getAllGroupsAndClasses();
        } catch (\Throwable $e) {
            $this->warn("Kon groups/classes niet ophalen (ok, we gaan door).");
        }

        // Klassen uit DB
        $klassenQuery = Klas::query()
            ->where('schoolid', $schoolId)
            ->whereNotNull('smartschool_code');

        if ($onlyCode !== '') {
            $klassenQuery->where('smartschool_code', $onlyCode);
        }

        $klassen = $klassenQuery->get();

        if ($klassen->count() === 0) {
            $this->warn("Geen klassen gevonden voor schoolid={$schoolId} (of filter --only-class).");
            return self::SUCCESS;
        }

        $seenUsernames = [];
        $processed = 0;
        $created = 0;
        $updated = 0;

        foreach ($klassen as $klas) {
            $classCode = trim((string)$klas->smartschool_code);
            if ($classCode === '') continue;

            $this->line("→ Sync klas {$klas->klasnaam} ({$classCode})");

            try {
                $accounts = $ss->getAllAccountsExtended($classCode, false);
            } catch (\Throwable $e) {
                $this->error("Fout bij ophalen accounts voor {$classCode}: ".$e->getMessage());
                continue;
            }

            // Smartschool geeft soms wrapper-keys; we maken dit defensief “vlak”
            $list = $accounts;

            // Als er een duidelijke array-key bestaat met items, probeer die
            foreach (['accounts','users','data','items','result'] as $k) {
                if (isset($accounts[$k]) && is_array($accounts[$k])) {
                    $list = $accounts[$k];
                    break;
                }
            }

            if (!is_array($list)) $list = [];

            foreach ($list as $row) {
                if (!is_array($row)) continue;

                // Filter op leerlingen (defensief): basisrol/role kan variëren
                $role = strtolower((string)($row['basisrol'] ?? $row['role'] ?? $row['basisRole'] ?? ''));
                if ($role !== '' && !str_contains($role, 'leerling')) {
                    continue; // skip leerkrachten e.d.
                }

                $username = trim((string)($row['username'] ?? $row['gebruikersnaam'] ?? $row['userIdentifier'] ?? ''));
                if ($username === '') {
                    continue;
                }

                // Naamvelden (Smartschool naming verschilt soms)
                $voornaam = trim((string)($row['name'] ?? $row['voornaam'] ?? ''));
                $achternaam = trim((string)($row['surname'] ?? $row['naam'] ?? ''));

                // Geboortedatum
                $birthdayRaw = (string)($row['birthday'] ?? $row['geboortedatum'] ?? '');
                $geboortedatum = null;
                if ($birthdayRaw !== '') {
                    // ondersteunt 'YYYY-MM-DD' of 'DD-MM-YYYY'
                    $birthdayRaw = trim($birthdayRaw);
                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthdayRaw)) {
                        $geboortedatum = $birthdayRaw;
                    } elseif (preg_match('/^\d{2}-\d{2}-\d{4}$/', $birthdayRaw)) {
                        [$d,$m,$y] = explode('-', $birthdayRaw);
                        $geboortedatum = "{$y}-{$m}-{$d}";
                    }
                }

                // e-mailadres: <gebruikersnaam>@atheneumsinttruiden.be
                $email = strtolower($username).'@atheneumsinttruiden.be';

                $seenUsernames[] = $username;
                $processed++;

                if ($limit > 0 && $processed > $limit) {
                    $this->warn("Limit bereikt ({$limit}). Stop.");
                    break 2;
                }

                // Klas auto-aanmaken? (als smartschool_code niet bestaat in DB)
                // (normaal heb je ze al, maar fail-safe)
                $klasId = $klas->id;

                if (!$klasId) {
                    // fallback (normaal nooit)
                    $klasId = $this->resolveOrCreateKlas($schoolId, $classCode, $codeToName, $dryRun);
                }

                if ($dryRun) {
                    $this->line("  [DRY] {$username} → {$voornaam} {$achternaam} ({$geboortedatum}) klasid={$klasId} email={$email}");
                    continue;
                }

                // Upsert leerling op (schoolid, gebruikersnaam)
                $existing = Leerling::query()
                    ->where('schoolid', $schoolId)
                    ->where('gebruikersnaam', $username)
                    ->first();

                if (!$existing) {
                    $l = new Leerling();
                    $l->schoolid = $schoolId;
                    $l->gebruikersnaam = $username;
                    $l->active = 1;
                    $l->klasid = $klasId;
                    $l->voornaam = $voornaam !== '' ? $voornaam : null;
                    $l->naam = $achternaam !== '' ? $achternaam : null;
                    if ($geboortedatum) $l->geboortedatum = $geboortedatum;
                    $l->{'e-mailadres'} = $email;
                    $l->save();

                    $created++;
                } else {
                    $dirty = false;

                    if ((int)$existing->active !== 1) { $existing->active = 1; $dirty = true; }
                    if ((int)$existing->klasid !== (int)$klasId) { $existing->klasid = $klasId; $dirty = true; }
                    if ($voornaam !== '' && (string)$existing->voornaam !== $voornaam) { $existing->voornaam = $voornaam; $dirty = true; }
                    if ($achternaam !== '' && (string)$existing->naam !== $achternaam) { $existing->naam = $achternaam; $dirty = true; }
                    if ($geboortedatum && optional($existing->geboortedatum)->toDateString() !== $geboortedatum) { $existing->geboortedatum = $geboortedatum; $dirty = true; }
                    if ((string)($existing->{'e-mailadres'} ?? '') !== $email) { $existing->{'e-mailadres'} = $email; $dirty = true; }

                    if ($dirty) {
                        $existing->save();
                        $updated++;
                    }
                }
            }
        }

        $seenUsernames = array_values(array_unique($seenUsernames));

        // Leerlingen die niet meer in Smartschool zitten → active = 0
        if (!$dryRun) {
            $q = Leerling::query()
                ->where('schoolid', $schoolId);

            if (count($seenUsernames) > 0) {
                $q->whereNotIn('gebruikersnaam', $seenUsernames);
            }

            $deactivated = $q->update(['active' => 0]);
            $this->info("Deactivated: {$deactivated}");
        }

        $this->info("Klaar. Processed={$processed}, created={$created}, updated={$updated}".($dryRun ? " [DRY-RUN]" : ""));
        Log::info("Smartschool sync klaar. Processed={$processed}, created={$created}, updated={$updated}".($dryRun ? " [DRY-RUN]" : ""));

        return self::SUCCESS;
    }

    private function resolveOrCreateKlas(int $schoolId, string $classCode, array $codeToName, bool $dryRun): int
    {
        $existing = Klas::query()
            ->where('schoolid', $schoolId)
            ->where('smartschool_code', $classCode)
            ->first();

        if ($existing) return (int)$existing->id;

        $name = $codeToName[$classCode] ?? $classCode;

        if ($dryRun) {
            $this->warn("  [DRY] Klas ontbreekt → zou aanmaken: {$name} ({$classCode})");
            return 0;
        }

        $klas = new Klas();
        $klas->schoolid = $schoolId;
        $klas->klasnaam = $name;
        $klas->smartschool_code = $classCode;
        $klas->save();

        $this->warn("  Klas aangemaakt: {$name} ({$classCode}) id={$klas->id}");
        return (int)$klas->id;
    }
}
