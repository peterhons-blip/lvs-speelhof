<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LibraryAccess
{
    public const ROLES = [
        'ict_coordinator' => 'ICT-coördinator',
        'directie' => 'Directie',
        'boekhouding' => 'Boekhouding',
        'secretariaat' => 'Secretariaat',
        'leerlingbegeleiding' => 'Leerlingbegeleiding',
        'tac' => 'TAC',
    ];

    public function username(Request $request): string
    {
        // Only exact Smartschool usernames are trusted, never the local session's display name.
        return mb_strtolower(trim((string) data_get($request->session()->get('ss_user'), 'gebruikersnaam', '')));
    }

    public function role(Request $request): ?string
    {
        $username = $this->username($request);
        if ($username === '' || ! Schema::hasTable('portal_staff')) return null;
        return DB::table('portal_staff')
            ->where('smartschool_username', $username)->where('is_active', 1)->value('role');
    }

    public function has(Request $request, string $permission): bool
    {
        $role = $this->role($request);
        if (! $role) return false;
        // Only registered permissions may be granted.
        $known = collect(config('portal_permissions', []))
            ->flatMap(fn ($module) => array_keys($module['permissions'] ?? []))->contains($permission);
        if (! $known) return false;

        // ICT coordinator is the portal owner and cannot lose administrative access
        // through an accidental permissions edit.
        if ($role === 'ict_coordinator') return true;
        if (! Schema::hasTable('portal_role_permissions')) return false;
        return DB::table('portal_role_permissions')->where('role', $role)
            ->where('permission', $permission)->exists();
    }

    public function requirePermission(Request $request, string $permission): void
    {
        abort_unless($this->has($request, $permission), 403,
            'Je hebt geen toegang tot deze functie. Vraag je ICT-coördinator om de rechten aan te passen.');
    }

    public function isManager(Request $request): bool
    {
        return $this->role($request) === 'ict_coordinator';
    }

    public function isOperator(Request $request): bool
    {
        return $this->has($request, 'bibliotheek.loans.manage');
    }

    public function requireManager(Request $request): void
    {
        $this->requirePermission($request, 'beheer.users.manage');
    }

    public function requireOperator(Request $request): void
    {
        $this->requirePermission($request, 'bibliotheek.loans.manage');
    }
}
