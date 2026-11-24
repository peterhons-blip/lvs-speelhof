<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LibraryAccess
{
    // Slugs are only permission keys; groups.id remains the existing source of school roles.
    // Do not rename the four legacy groups or change their IDs.
    public const ROLES = [
        'leerkracht' => 'leerkracht',
        'administratie' => 'administratie',
        'directie' => 'directeur',
        'leerlingbegeleiding' => 'leerlingbegeleider',
        'ict_coordinator' => 'ICT-coördinator',
        'boekhouding' => 'boekhouding',
        'secretariaat' => 'secretariaat',
        'tac' => 'TAC',
    ];

    public const LABELS = [
        'ict_coordinator' => 'ICT-coördinator',
        'directie' => 'Directie',
        'secretariaat' => 'Secretariaat',
        'leerlingbegeleiding' => 'Leerlingbegeleiding',
        'boekhouding' => 'Boekhouding',
        'tac' => 'TAC',
        'leerkracht' => 'Leerkracht',
        'administratie' => 'Administratie',
    ];

    public const MANAGEMENT_PERMISSIONS = [
        'beheer.permissions.manage',
        'beheer.staff.manage', 'beheer.students.manage',
        'beheer.smartschool_test.send', 'beheer.student_sync.manage',
        'beheer.staff_sync.manage', 'beheer.scheduled_tasks.view',
        'beheer.sent_items.view',
        'lvs.dashboard.view', 'lvs.view', 'lvs.meldingen.view', 'lvs.meldingen.create', 'lvs.reports.view',
        'beheer.birthdays.manage',
    ];

    public function requireIctManager(Request $request): void
    {
        $this->requirePermission($request, 'beheer.permissions.manage');
    }

    public function username(Request $request): string
    {
        // Smartschool OAuth's canonical username; never authorize by supplied groupname.
        return mb_strtolower(trim((string) data_get($request->session()->get('ss_user'), 'gebruikersnaam', '')));
    }

    public function role(Request $request): ?string
    {
        $username = $this->username($request);
        if ($username === '' || ! Schema::hasTable('users') || ! Schema::hasTable('groups')) return null;

        $groupName = DB::table('users')
            ->join('groups', 'groups.id', '=', 'users.groupId')
            ->where('users.smartschool_gebruikersnaam', $username)
            ->where('users.allowed_login', 1)
            ->where(function ($q) { $q->whereNull('users.smartschool_active')->orWhere('users.smartschool_active', 1); })
            ->value('groups.groupname');

        if ($groupName === null) return null;

        foreach (self::ROLES as $slug => $name) {
            if (mb_strtolower($groupName) === mb_strtolower($name)) return $slug;
        }
        return null;
    }

    public function has(Request $request, string $permission): bool
    {
        $role = $this->role($request);
        if ($role === null) return false;

        $known = collect(config('portal_permissions', []))
            ->flatMap(fn ($module) => array_keys($module['permissions'] ?? []))
            ->contains($permission);
        if (! $known) return false;

        // ICT-coördinator behoudt altijd volledige beheertoegang.
        if ($role === 'ict_coordinator') return true;
        // Alle rechten, ook beheermodules, worden per schoolrol toegekend.
        if (! Schema::hasTable('portal_role_permissions')) return false;

        return DB::table('portal_role_permissions')
            ->where('role', $role)->where('permission', $permission)->exists();
    }

    public function requirePermission(Request $request, string $permission): void
    {
        abort_unless($this->has($request, $permission), 403,
            'Je hebt geen toegang tot deze functie. Vraag de ICT-coördinator om rechten.');
    }

    public function isManager(Request $request): bool
    {
        if ($this->role($request) === 'ict_coordinator') return true;
        foreach (self::MANAGEMENT_PERMISSIONS as $permission) {
            if ($this->has($request, $permission)) return true;
        }
        return false;
    }

    public function isOperator(Request $request): bool
    {
        return $this->has($request, 'bibliotheek.loans.manage');
    }

    public function requireManager(Request $request): void
    {
        $this->requirePermission($request, 'beheer.staff.manage');
    }

    public function requireOperator(Request $request): void
    {
        $this->requirePermission($request, 'bibliotheek.loans.manage');
    }
}
