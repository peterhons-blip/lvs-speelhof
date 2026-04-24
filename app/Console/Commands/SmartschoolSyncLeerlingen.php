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
                            {--limit=0 : Maximum # leerlingen verwerken (0 = geen limiet)}
                            {--only-class= : Alleen deze smartschool_code syncen}';

    protected $description = 'Synct leerlingen uit Smartschool naar DB: leerlingen, klassen, active vlag.';

    public function handle(): int
    {
        $schoolId = (int) ($this->option('schoolid') ?: 1);
        $dryRun = $this->option('dry-run') === true;
        $limit = (int) ($this->option('limit') ?: 0);
        $onlyCode = trim((string) ($this->option('only-class') ?: ''));

        $this->info("Smartschool sync leerlingen gestart (schoolid={$schoolId})" . ($dryRun ? ' [DRY-RUN]' : ''));

        $school = School::find($schoolId);

        if (!$school) {
            $this->error("School {$schoolId} niet gevonden.");
            return self::FAILURE;
        }

        $wsdl = trim((string) $school->smartschool_wsdl);
        $accesscode = trim((string) $school->smartschool_accesscode);

        if ($wsdl === '') {
            $this->error("School {$schoolId}: smartschool_wsdl ontbreekt in tabel scholen.");
            Log::error("Smartschool sync gestopt: smartschool_wsdl ontbreekt voor schoolid={$schoolId}");
            return self::FAILURE;
        }

        if ($accesscode === '') {
            $this->error("School {$schoolId}: smartschool_accesscode ontbreekt in tabel scholen.");
            Log::error("Smartschool sync gestopt: smartschool_accesscode ontbreekt voor schoolid={$schoolId}");
            return self::FAILURE;
        }

        $ss = new SmartschoolSoap($wsdl, $accesscode);

        try {
            $smartschoolClasses = $ss->getClassListJson();
        } catch (\Throwable $e) {
            $this->error('Kon Smartschool klassen niet ophalen: ' . $e->getMessage());
            return self::FAILURE;
        }

        if (empty($smartschoolClasses)) {
            $this->error('Geen klassen ontvangen van Smartschool. Sync gestopt.');
            return self::FAILURE;
        }

        $seenUsernames = [];
        $processed = 0;
        $created = 0;
        $updated = 0;
        $createdClasses = 0;

        foreach ($smartschoolClasses as $ssClass) {
            if (!is_array($ssClass)) {
                continue;
            }

            $classCode = $this->firstValue($ssClass, [
                'code',
                'classCode',
                'groupCode',
                'smartschool_code',
                'id',
            ]);

            $className = $this->firstValue($ssClass, [
                'name',
                'klasnaam',
                'className',
                'desc',
                'description',
                'omschrijving',
            ]);

            if (!$classCode) {
                continue;
            }

            if ($onlyCode !== '' && $classCode !== $onlyCode) {
                continue;
            }

            if (!$className) {
                $className = $classCode;
            }

            $klas = $this->findOrCreateKlas($schoolId, $classCode, $className, $dryRun, $createdClasses);

            $this->line("→ Sync klas {$className} ({$classCode})");

            try {
                $accounts = $ss->getAllAccountsExtended($classCode, false);
            } catch (\Throwable $e) {
                $this->warn("Fout bij ophalen accounts voor {$classCode}: " . $e->getMessage());
                continue;
            }

            $list = $this->extractAccountList($accounts);

            if (count($list) === 0) {
                $this->warn("  Geen accounts gevonden voor {$className} ({$classCode})");
                continue;
            }

            foreach ($list as $row) {
                if (!is_array($row)) {
                    continue;
                }

                if (!$this->isLeerling($row)) {
                    continue;
                }

                $username = trim((string) ($row['gebruikersnaam'] ?? $row['username'] ?? $row['userIdentifier'] ?? ''));

                if ($username === '') {
                    continue;
                }

                $voornaam = trim((string) ($row['voornaam'] ?? $row['name'] ?? ''));
                $naam = trim((string) ($row['naam'] ?? $row['surname'] ?? ''));

                $geboortedatum = $this->normalizeDate(
                    $row['geboortedatum'] ?? $row['birthday'] ?? null
                );

                $email = strtolower($username) . '@atheneumsinttruiden.be';

                $seenUsernames[] = $username;
                $processed++;

                if ($limit > 0 && $processed > $limit) {
                    $this->warn("Limit bereikt ({$limit}). Stop.");
                    break 2;
                }

                $klasId = $klas ? (int) $klas->id : 0;

                if ($dryRun) {
                    $this->line("  [DRY] {$username} → {$voornaam} {$naam} | klas={$className} | geboortedatum={$geboortedatum} | email={$email}");
                    continue;
                }

                $existing = Leerling::query()
                    ->where('schoolid', $schoolId)
                    ->where('gebruikersnaam', $username)
                    ->first();

                if (!$existing) {
                    $leerling = new Leerling();
                    $leerling->schoolid = $schoolId;
                    $leerling->klasid = $klasId;
                    $leerling->gebruikersnaam = $username;
                    $leerling->voornaam = $voornaam ?: null;
                    $leerling->naam = $naam ?: null;
                    $leerling->geboortedatum = $geboortedatum;
                    $leerling->{'e-mailadres'} = $email;
                    $leerling->active = 1;
                    $leerling->save();

                    $created++;
                    continue;
                }

                $dirty = false;

                if ((int) $existing->active !== 1) {
                    $existing->active = 1;
                    $dirty = true;
                }

                if ((int) $existing->klasid !== $klasId) {
                    $existing->klasid = $klasId;
                    $dirty = true;
                }

                if ($voornaam !== '' && (string) $existing->voornaam !== $voornaam) {
                    $existing->voornaam = $voornaam;
                    $dirty = true;
                }

                if ($naam !== '' && (string) $existing->naam !== $naam) {
                    $existing->naam = $naam;
                    $dirty = true;
                }

                if ($geboortedatum && optional($existing->geboortedatum)->toDateString() !== $geboortedatum) {
                    $existing->geboortedatum = $geboortedatum;
                    $dirty = true;
                }

                if ((string) ($existing->{'e-mailadres'} ?? '') !== $email) {
                    $existing->{'e-mailadres'} = $email;
                    $dirty = true;
                }

                if ($dirty) {
                    $existing->save();
                    $updated++;
                }
            }
        }

        $seenUsernames = array_values(array_unique($seenUsernames));

        $deactivated = 0;

        if (!$dryRun && count($seenUsernames) > 0) {

            $query = Leerling::query()
                ->where('schoolid', $schoolId);

            if ($onlyCode !== '') {
                // 🔥 enkel binnen deze klas deactiveren
                $klas = Klas::query()
                    ->where('schoolid', $schoolId)
                    ->where('smartschool_code', $onlyCode)
                    ->first();

                if ($klas) {
                    $query->where('klasid', $klas->id);
                } else {
                    $this->warn("Deactivatie overgeslagen: klas {$onlyCode} niet gevonden.");
                    $query = null;
                }
            } elseif ($limit > 0) {
                // bij limit nog steeds gevaarlijk → niet doen
                $this->warn('Deactivatie overgeslagen wegens --limit.');
                $query = null;
            }

            if ($query) {
                $deactivated = $query
                    ->whereNotIn('gebruikersnaam', $seenUsernames)
                    ->update(['active' => 0]);

                $scope = $onlyCode !== '' ? "klas {$onlyCode}" : "volledige school";
                $this->info("Niet meer gevonden in Smartschool ({$scope}) → active=0: {$deactivated}");
            }
        }

        $msg = "Klaar. Processed={$processed}, created={$created}, updated={$updated}, classes_created={$createdClasses}, deactivated={$deactivated}" . ($dryRun ? ' [DRY-RUN]' : '');

        $this->info($msg);
        Log::info('Smartschool sync leerlingen: ' . $msg);

        return self::SUCCESS;
    }

    private function findOrCreateKlas(int $schoolId, string $classCode, string $className, bool $dryRun, int &$createdClasses): ?Klas
    {
        $existing = Klas::query()
            ->where('schoolid', $schoolId)
            ->where('smartschool_code', $classCode)
            ->first();

        if ($existing) {
            if ($existing->klasnaam !== $className && !$dryRun) {
                $existing->klasnaam = $className;
                $existing->save();
            }

            return $existing;
        }

        if ($dryRun) {
            $this->warn("  [DRY] Klas ontbreekt → zou aanmaken: {$className} ({$classCode})");
            return null;
        }

        $klas = new Klas();
        $klas->schoolid = $schoolId;
        $klas->klasnaam = $className;
        $klas->smartschool_code = $classCode;
        $klas->save();

        $createdClasses++;

        $this->warn("  Klas aangemaakt: {$className} ({$classCode})");

        return $klas;
    }

    private function extractAccountList(array $accounts): array
    {
        foreach (['accounts', 'users', 'data', 'items', 'result'] as $key) {
            if (isset($accounts[$key]) && is_array($accounts[$key])) {
                return $accounts[$key];
            }
        }

        if (array_is_list($accounts)) {
            return $accounts;
        }

        return [];
    }

    private function isLeerling(array $row): bool
    {
        $role = strtolower(trim((string) ($row['basisrol'] ?? $row['role'] ?? $row['basisRole'] ?? '')));

        // In jouw Smartschool response is basisrol = "1" voor leerlingen
        if ($role === '' || $role === '1' || $role === 'leerling' || $role === 'student') {
            return true;
        }

        return str_contains($role, 'leerling') || str_contains($role, 'student');
    }

    private function normalizeDate($value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $value, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        return null;
    }

    private function firstValue(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($row[$key]) && trim((string) $row[$key]) !== '') {
                return trim((string) $row[$key]);
            }
        }

        return null;
    }
}