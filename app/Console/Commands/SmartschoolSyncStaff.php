<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Services\SmartschoolSoap;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SmartschoolSyncStaff extends Command
{
    protected $signature = 'smartschool:sync-personeel {--schoolid=1} {--apply : Wijzig de databank; standaard enkel preview}';
    protected $description = 'Personeelsaccounts uit Smartschool ophalen; standaard veilige preview zonder wijzigingen.';

    private const GROUPS = [
        'Leerkrachten Atheneum Sint-Truiden' => 'leerkracht',
        'Administratie Campus Speelhof' => 'administratie',
    ];

    public function handle(): int
    {
        $schoolId = (int) $this->option('schoolid');
        $apply = (bool) $this->option('apply');
        $lock = Cache::lock('smartschool:sync-personeel:'.$schoolId, 1200);
        if (! $lock->get()) {
            $this->error('Er loopt al een personeelssynchronisatie voor deze school.');
            return self::FAILURE;
        }

        try {
            return $this->synchronize($schoolId, $apply);
        } finally {
            $lock->release();
        }
    }

    private function synchronize(int $schoolId, bool $apply): int
    {
        if (! DB::getSchemaBuilder()->hasTable('smartschool_staff_memberships')) {
            $this->error('Voer eerst de migratie voor smartschool_staff_memberships uit.');
            return self::FAILURE;
        }

        $school = School::find($schoolId);
        if (! $school || ! trim((string) $school->smartschool_wsdl)
            || ! trim((string) $school->smartschool_accesscode)) {
            $this->error('De Smartschool-configuratie voor deze school ontbreekt.');
            return self::FAILURE;
        }

        $soap = new SmartschoolSoap($school->smartschool_wsdl, $school->smartschool_accesscode);
        $accounts = [];
        $totals = [];
        foreach (self::GROUPS as $group => $role) {
            try {
                $rows = $soap->getAllAccountsExtended($group, true);
            } catch (\Throwable $e) {
                $this->error('Smartschool-groep kon niet worden opgehaald: '.$group);
                return self::FAILURE;
            }
            if (! array_is_list($rows) || count($rows) < 5) {
                $this->error('Onverwacht lege/onvolledige Smartschool-groep: '.$group.'. Niets gewijzigd.');
                return self::FAILURE;
            }
            $totals[$role] = count($rows);
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    $this->error('Ongeldig accountrecord ontvangen. Niets gewijzigd.');
                    return self::FAILURE;
                }
                $basisrol = mb_strtolower(trim((string) ($row['basisrol'] ?? '')));
                if (in_array($basisrol, ['1', 'leerling', 'student'], true)) {
                    $this->error('Leerlingenaccount aangetroffen in personeelsgroep. Niets gewijzigd.');
                    return self::FAILURE;
                }
                $username = mb_strtolower(trim((string) ($row['gebruikersnaam'] ?? '')));
                if ($username === '') {
                    $this->error('Een personeelsaccount mist een gebruikersnaam. Niets gewijzigd.');
                    return self::FAILURE;
                }
                if (! isset($accounts[$username])) {
                    $accounts[$username] = [
                        'username' => $username,
                        'first' => trim((string) ($row['voornaam'] ?? '')),
                        'last' => trim((string) ($row['naam'] ?? '')),
                        'email' => trim((string) ($row['emailadres'] ?? '')),
                        'role' => $role,
                    ];
                }
                if ($role === 'administratie') {
                    $accounts[$username]['role'] = 'administratie';
                }
            }
        }

        // De test in Smartschool gaf ca. 135 unieke accounts terug. Een sterk afwijkende
        // respons kan betekenen dat de groepsboom of de webservice tijdelijk onvolledig is.
        if (count($accounts) < 100 || ($totals['leerkracht'] ?? 0) < 80
            || ($totals['administratie'] ?? 0) < 10) {
            $this->error('Onverwacht weinig personeelsaccounts. Niets gewijzigd.');
            return self::FAILURE;
        }

        $groupIds = DB::table('groups')->whereIn('groupname', ['leerkracht', 'administratie'])
            ->pluck('id', 'groupname')->all();
        if (! isset($groupIds['leerkracht'], $groupIds['administratie'])) {
            $this->error('Groep leerkracht of administratie ontbreekt. Voer eerst de rollenmigratie uit.');
            return self::FAILURE;
        }

        // Legacy LVS-accounts hebben soms geen schoolid. Zoek ook in de bestaande
        // volledige users-tabel zodat ze niet onterecht als 'nieuw' verschijnen.
        $allUsers = DB::table('users')->get();
        $existing = $allUsers->filter(fn ($user) => trim((string) $user->gebruikersnaam) !== '')
            ->keyBy(fn ($user) => mb_strtolower(trim((string) $user->gebruikersnaam)));
        $localSchool = $allUsers->filter(fn ($user) =>
            $user->schoolid === null || (int) $user->schoolid === $schoolId);
        $this->line('Bestaande users: '.$allUsers->count().' | schoolid='.$schoolId
            .': '.$allUsers->where('schoolid', $schoolId)->count()
            .' | zonder schoolid: '.$allUsers->whereNull('schoolid')->count().'.');
        $managed = DB::table('smartschool_staff_memberships')->where('schoolid', $schoolId)
            ->pluck('gebruikersnaam')->map(fn ($v) => mb_strtolower($v))->all();
        $changes = ['nieuw' => 0, 'bijwerken' => 0, 'uitschakelen' => 0];
        $missingUsernames = [];
        $matchedWithoutSchool = 0;
        $disable = [];
        // Ook bestaande lokale accounts met een standaardrol opnemen in de
        // controle; speciale rollen en serviceaccounts worden niet automatisch verwijderd.
        foreach ($localSchool as $user) {
            $username = mb_strtolower(trim((string) $user->gebruikersnaam));
            if ($username !== '' && in_array((int) $user->groupId, array_map('intval', array_values($groupIds)), true)) {
                $managed[] = $username;
            }
        }
        $managed = array_values(array_unique($managed));
        foreach ($managed as $username) {
            if (! isset($accounts[$username]) && isset($existing[$username])
                && (int) $existing[$username]->allowed_login === 1) {
                $disable[] = $username;
            }
        }
        // Bescherm de laatste actieve ICT-coordinator tegen automatische uitschakeling.
        if ($disable) {
            $ictId = DB::table('groups')->where('groupname', 'ICT-coördinator')->value('id');
            if ($ictId !== null) {
                $activeIct = DB::table('users')->where('schoolid', $schoolId)
                    ->where('groupId', $ictId)->where('allowed_login', 1)->count();
                $affectedIct = collect($disable)->filter(fn ($name) =>
                    isset($existing[$name]) && (int) $existing[$name]->groupId === (int) $ictId
                )->count();
                if ($activeIct - $affectedIct < 1) {
                    $this->error('De laatste actieve ICT-coordinator zou worden geblokkeerd. Niets gewijzigd.');
                    return self::FAILURE;
                }
            }
        }

        // Nooit een grote groep gebruikers door een onvolledige respons blokkeren.
        if (count($disable) > 10 || (count($managed) >= 100 && count($accounts) < count($managed) * 0.85)) {
            $this->error('Onverwacht veel verdwenen accounts. Niets gewijzigd; controleer Smartschool.');
            return self::FAILURE;
        }

        foreach ($accounts as $username => $row) {
            $existingUser = $existing[$username] ?? null;
            if (! $existingUser) {
                $changes['nieuw']++;
                $missingUsernames[] = $username;
            } else {
                if ($existingUser->schoolid === null) $matchedWithoutSchool++;
                if ($this->needsUpdate($existingUser, $row, $groupIds)) {
                    $changes['bijwerken']++;
                }
            }
        }
        $changes['uitschakelen'] = count($disable);
        $this->info(($apply ? 'UITVOEREN' : 'PREVIEW').': leerkrachtgroep '.$totals['leerkracht']
            .', administratiegroep '.$totals['administratie'].', uniek '.count($accounts).'.');
        $this->line('Nieuw: '.$changes['nieuw'].' | Bijwerken: '.$changes['bijwerken']
            .' | Uitschakelen: '.$changes['uitschakelen']);
        $this->line('Bestaande gebruikers zonder schoolid die werden herkend: '.$matchedWithoutSchool);
        if ($missingUsernames) $this->line('Nog niet lokaal gevonden: '.implode(', ', $missingUsernames));
        if ($disable) $this->warn('Uitschakelen: '.implode(', ', $disable));
        if (! $apply) {
            $this->comment('Dit was een preview. Gebruik --apply om de veranderingen uit te voeren.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($schoolId, $accounts, $existing, $groupIds, $disable) {
            foreach ($accounts as $username => $row) {
                $current = $existing[$username] ?? null;
                if ($current) {
                    $fields = $this->updatedFields($current, $row, $groupIds);
                    if ($fields) DB::table('users')->where('id', $current->id)->update($fields);
                } else {
                    $email = filter_var($row['email'], FILTER_VALIDATE_EMAIL)
                        ? $row['email'] : $username.'@staff.invalid';
                    // Een bestaande e-mail kan al bij een ander account horen: nooit accounts samenvoegen op e-mail.
                    if (DB::table('users')->where('email', $email)->exists()) {
                        $email = 'staff-'.$schoolId.'-'.hash('sha256', $username).'@staff.invalid';
                    }
                    DB::table('users')->insert([
                        'schoolid' => $schoolId,
                        'gebruikersnaam' => $username,
                        'smartschool_gebruikersnaam' => $username,
                        'voornaam' => $row['first'],
                        'name' => $row['last'] !== '' ? $row['last'] : $username,
                        'email' => $email,
                        'password' => Hash::make(Str::random(64)),
                        'groupId' => (int) $groupIds[$row['role']],
                        'allowed_login' => 1,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
                DB::table('smartschool_staff_memberships')->updateOrInsert(
                    ['schoolid' => $schoolId, 'gebruikersnaam' => $username],
                    ['last_seen_at' => now(), 'updated_at' => now()]
                );
            }
            if ($disable) {
                DB::table('users')->whereIn('id', collect($disable)
                    ->map(fn ($name) => (int) $existing[$name]->id)->all())
                    ->update(['allowed_login' => 0, 'updated_at' => now()]);
            }
        });
        $this->info('Personeelssynchronisatie voltooid.');
        return self::SUCCESS;
    }

    private function needsUpdate(object $user, array $row, array $groupIds): bool
    {
        return $this->updatedFields($user, $row, $groupIds) !== [];
    }

    private function updatedFields(object $user, array $row, array $groupIds): array
    {
        $fields = [];
        foreach (['voornaam' => 'first', 'name' => 'last'] as $column => $key) {
            if ($row[$key] !== '' && (string) $user->{$column} !== $row[$key]) {
                $fields[$column] = $row[$key];
            }
        }
        // Enkel twee door de sync beheerde standaardrollen mogen van groep veranderen.
        if (in_array((int) $user->groupId, array_map('intval', array_values($groupIds)), true)
            && (int) $user->groupId !== (int) $groupIds[$row['role']]) {
            $fields['groupId'] = (int) $groupIds[$row['role']];
        }
        if ($fields) $fields['updated_at'] = now();
        return $fields;
    }
}
