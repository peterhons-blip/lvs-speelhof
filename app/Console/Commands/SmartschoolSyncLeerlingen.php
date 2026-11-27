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
                            {--only-class= : Alleen deze smartschool_code syncen}
                            {--confirm-empty-classes : Compatibiliteitsoptie; niet meer vereist}
                            {--skip-scancodes : QR-codes niet synchroniseren}
                            {--refresh-scancodes : Bestaande QR-codes opnieuw ophalen via getUserDetails}';

    protected $description = 'Synct leerlingen uit Smartschool naar DB: leerlingen, klassen, active vlag.';

    public function handle(): int
    {
        $schoolId = (int) ($this->option('schoolid') ?: 1);
        $lock = \Illuminate\Support\Facades\Cache::lock('smartschool:sync-leerlingen:school:'.$schoolId, 3600);
        if (! $lock->get()) {
            $this->error('Sync reeds actief voor deze school. Probeer later opnieuw.');
            return self::FAILURE;
        }

        try {
            return $this->runSync();
        } finally {
            $lock->release();
        }
    }

    private function runSync(): int
    {
        $schoolId = (int) ($this->option('schoolid') ?: 1);
        $dryRun = $this->option('dry-run') === true;
        $limit = (int) ($this->option('limit') ?: 0);
        $onlyCode = trim((string) ($this->option('only-class') ?: ''));
        $syncScancodes = ! $this->option('skip-scancodes')
            && \App\Support\LegacyDatabaseSchema::hasColumn('leerlingen', 'scannable_code');
        $refreshScancodes = (bool) $this->option('refresh-scancodes');
        $identifierFields = array_filter(['stamnummer'=>'stamboeknummer','InformatInternNr'=>'internnummer'],fn($source,$column)=>\App\Support\LegacyDatabaseSchema::hasColumn('leerlingen',$column),ARRAY_FILTER_USE_BOTH);
        $identifierCounts = ['stamnummer'=>0,'InformatInternNr'=>0];
        $identifierConflicts = 0;
        $trackMissing = \App\Support\LegacyDatabaseSchema::hasColumn('leerlingen','sync_missing_count');
        $reactivated = 0;
        $detailsCallsDisabled = false;
        $detailsFailures = 0;
        $codesUpdated = 0;
        $codesMissing = 0;
        $codeConflicts = 0;
        if (! $syncScancodes && ! $this->option('skip-scancodes')) {
            $this->warn('Scannable codes overgeslagen: voer eerst de leerlingen.scannable_code migratie uit.');
        }


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
        $failedClasses = 0;
        $emptyClasses = 0;

        foreach ($smartschoolClasses as $ssClass) {
            if (!is_array($ssClass)) {
                continue;
            }

            $isKlas = $ssClass['isKlas'] ?? $ssClass['isClass'] ?? null;
            $isOfficial = $ssClass['isOfficial'] ?? $ssClass['official'] ?? null;

            if (!$this->toBool($isKlas) || !$this->toBool($isOfficial)) {
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
                $failedClasses++;
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
                $failedClasses++;
                $this->warn("Fout bij ophalen accounts voor {$classCode}: " . $e->getMessage());
                continue;
            }

            $list = $this->extractAccountList($accounts);
            if ($accounts !== [] && !array_is_list($accounts) && !collect(['accounts','users','data','items','result'])->contains(fn($key)=>isset($accounts[$key]) && is_array($accounts[$key]))) {
                $failedClasses++;
                $this->warn("Onverwachte accountrespons voor {$classCode}; deactivatie geblokkeerd.");
                continue;
            }

            if (count($list) === 0) {
                $emptyClasses++;
                $this->warn("  Geen accounts gevonden voor {$className} ({$classCode})");
                continue;
            }

            foreach ($list as $row) {
                if (!is_array($row)) {
                    $failedClasses++;
                    continue;
                }

                if (!$this->isLeerling($row)) {
                    continue;
                }

                $username = trim((string) ($row['gebruikersnaam'] ?? $row['username'] ?? $row['userIdentifier'] ?? ''));

                if ($username === '') {
                    $failedClasses++;
                    continue;
                }

                $voornaam = trim((string) ($row['voornaam'] ?? $row['name'] ?? ''));
                $naam = trim((string) ($row['naam'] ?? $row['surname'] ?? ''));

                $geboortedatum = $this->normalizeDate(
                    $row['geboortedatum'] ?? $row['birthday'] ?? null
                );

                // Prefer the QR identifier already supplied by the bulk class response.
                // If absent, query getUserDetails during sync only, never during loans.
                $scannableCode = $syncScancodes ? $this->scannableCodeFrom($row) : null;
                if ($syncScancodes && ! $dryRun && $scannableCode === null && ! $detailsCallsDisabled) {
                    $cachedCode = Leerling::query()->where('schoolid', $schoolId)
                        ->where('gebruikersnaam', $username)->value('scannable_code');

                    if ($cachedCode === null || $refreshScancodes) {
                        try {
                            $details = $ss->getUserDetails($username);
                            $scannableCode = $this->scannableCodeFrom($details);
                            $detailsFailures = 0;
                        } catch (\Throwable $e) {
                            $detailsFailures++;
                            if ($detailsFailures === 1) {
                                $this->warn('Smartschool QR-opvraging mislukt. Bestaande leerlingensync gaat verder: '.$e->getMessage());
                            }
                            if ($detailsFailures >= 3) {
                                $detailsCallsDisabled = true;
                                $this->warn('QR-opvraging voor overige leerlingen overgeslagen na drie fouten.');
                            }
                        }
                    }
                }

                $identifiers = [];
                foreach ($identifierFields as $column=>$source) {
                    $raw = $row[$source] ?? ($source==='internnummer' ? ($row['internummer'] ?? null) : null);
                    if (!is_string($raw) && !is_int($raw)) continue;
                    $value = trim((string)$raw);
                    if ($value==='' || in_array(strtolower($value),['null','undefined','0'],true) || mb_strlen($value)>100) continue;
                    $identifierCounts[$column]++;
                    if (!$dryRun && Leerling::query()->where('schoolid',$schoolId)->where($column,$value)->where('gebruikersnaam','<>',$username)->exists()) {
                        $identifierConflicts++;
                        $this->warn("Identificatieconflict ({$column}); bestaande waarde behouden.");
                        continue;
                    }
                    $identifiers[$column]=$value;
                }

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

                // Never assign one QR code to multiple student records (also across schools).
                if ($scannableCode !== null) {
                    $conflict = Leerling::query()->where('scannable_code', $scannableCode)
                        ->where(function ($q) use ($schoolId, $username) {
                            $q->where('schoolid', '!=', $schoolId)
                                ->orWhere('gebruikersnaam', '!=', $username);
                        })->exists();
                    if ($conflict) {
                        $codeConflicts++;
                        $this->warn("QR-codeconflict bij {$username}; scanwaarde niet bijgewerkt.");
                        $scannableCode = null;
                    }
                }

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
                    if ($scannableCode !== null) {
                        $leerling->scannable_code = $scannableCode;
                        $codesUpdated++;
                    } else if ($syncScancodes) {
                        $codesMissing++;
                    }
                    foreach ($identifiers as $column=>$value) $leerling->{$column}=$value;
                    $leerling->save();

                    $created++;
                    continue;
                }

                $dirty = false;

                if ((int) $existing->active !== 1) {
                    $existing->active = 1;
                    $reactivated++;
                    $dirty = true;
                }

                if ($trackMissing && (int)$existing->sync_missing_count !== 0) {
                    $existing->sync_missing_count = 0;
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

                if ($syncScancodes) {
                    if ($scannableCode !== null && $existing->scannable_code !== $scannableCode) {
                        $existing->scannable_code = $scannableCode;
                        $codesUpdated++;
                        $dirty = true;
                    } else if ($scannableCode === null && empty($existing->scannable_code)) {
                        $codesMissing++;
                    }
                }
                foreach ($identifiers as $column=>$value) {
                    if ((string)$existing->{$column}!==$value) {
                        $existing->{$column}=$value;
                        $dirty=true;
                    }
                }
                if ($dirty) {
                    $existing->save();
                    $updated++;
                }
            }
        }

        $seenUsernames = array_values(array_unique($seenUsernames));

        $deactivated = 0;

        // Reconcile immediately, but only after a complete, plausible school snapshot.
        $completeScope = \App\Support\StudentSyncSafety::complete($failedClasses,$limit,$onlyCode,count($seenUsernames));
        $pendingMissing = 0;
        if ($completeScope) {
            $activeCount = Leerling::query()->where('schoolid',$schoolId)->where('active',1)->count();
            $missing = Leerling::query()->where('schoolid',$schoolId)->where('active',1)
                ->whereNotIn('gebruikersnaam',$seenUsernames)->with('klas')->orderBy('naam')->orderBy('voornaam')->get();
            $plausible = \App\Support\StudentSyncSafety::plausible($activeCount,$missing->count());
            $this->info('Unieke leerlingen ontvangen: '.count($seenUsernames));
            $this->info('Lokaal actief maar niet ontvangen: '.$missing->count());
            foreach ($missing as $student) {
                $this->line('  '.$student->gebruikersnaam.' | '.($student->klas?->klasnaam ?? 'Geen klas'));
            }
            if (!$plausible) {
                $pendingMissing=$missing->count();
                $this->warn('Onverwacht grote daling: geen leerlingen gedeactiveerd.');
            } elseif (!$dryRun) {
                \Illuminate\Support\Facades\DB::transaction(function() use ($missing,$schoolId,$seenUsernames,$trackMissing,&$deactivated) {
                    foreach ($missing as $student) {
                        $row=Leerling::query()->where('schoolid',$schoolId)->where('id',$student->id)->where('active',1)->whereNotIn('gebruikersnaam',$seenUsernames)->lockForUpdate()->first();
                        if (!$row) continue;
                        $row->active=0;
                        if ($trackMissing) $row->sync_missing_count=0;
                        $row->save();
                        $deactivated++;
                        $this->info('  Gedeactiveerd: '.$row->gebruikersnaam);
                    }
                });
            } else {
                $pendingMissing=$missing->count();
                $this->info('DRY-RUN: '.$pendingMissing.' leerlingen zouden gedeactiveerd worden.');
            }
        } else {
            $this->warn('Vergelijking en deactivering overgeslagen: geen volledige schoolopvraging.');
        }
        if ($emptyClasses > 0) {
            $this->info("INFO: {$emptyClasses} lege Smartschoolklassen aangetroffen; lege klassen blokkeren de deactivering niet.");
        }
        if ($failedClasses > 0) {
            $this->warn("WAARSCHUWING: {$failedClasses} onvolledige klasopvragingen. Geen deactivering.");
        }

        $msg = "Klaar. Processed={$processed}, created={$created}, updated={$updated}, classes_created={$createdClasses}, deactivated={$deactivated}, reactivated={$reactivated}, pending_missing={$pendingMissing}, scancodes_updated={$codesUpdated}, scancodes_missing={$codesMissing}, scancodes_conflicts={$codeConflicts}, stamnummers_received={$identifierCounts['stamnummer']}, informatnummers_received={$identifierCounts['InformatInternNr']}, identifier_conflicts={$identifierConflicts}, failed_classes={$failedClasses}, empty_classes={$emptyClasses}" . ($dryRun ? ' [DRY-RUN]' : '');

        $this->info($msg);
        Log::info('Smartschool sync leerlingen: ' . $msg);

        // An empty class can be legitimate in Smartschool. The imported 645+ pupil
        // records remain valid; report a warning instead of a failed task.
        // Genuine errors obtaining classes still fail.
        return $failedClasses > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Smartschool QR identifiers are UUIDs. Extract only their identifier field,
     * never persist other profile fields or log QR values.
     */
    private function scannableCodeFrom(mixed $payload): ?string
    {
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            if (json_last_error() !== JSON_ERROR_NONE) return null;
            $payload = $decoded;
        }
        if (is_object($payload)) $payload = (array) $payload;
        if (! is_array($payload)) return null;

        foreach (['scannableCode', 'scannablecode', 'scannable_code'] as $field) {
            if (isset($payload[$field]) && is_string($payload[$field])) {
                $value = strtoupper(trim($payload[$field]));
                if (preg_match('/^[A-F0-9]{8}(-[A-F0-9]{4}){3}-[A-F0-9]{12}$/D', $value)) {
                    return $value;
                }
            }
        }

        // Some SOAP responses wrap the profile in a top-level object or array.
        foreach (['data', 'user', 'account', 'result'] as $key) {
            if (isset($payload[$key])) {
                $nested = $this->scannableCodeFrom($payload[$key]);
                if ($nested !== null) return $nested;
            }
        }
        return null;
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

    private function toBool($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $value = strtolower(trim((string) $value));

        return in_array($value, ['1', 'true', 'yes', 'ja'], true);
    }
}