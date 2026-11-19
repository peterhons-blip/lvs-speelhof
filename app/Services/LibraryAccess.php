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
        // No fallback to numeric IDs for authorization.
        return mb_strtolower(trim((string) data_get($request->session()->get('ss_user'), 'gebruikersnaam', '')));
    }

    public function role(Request $request): ?string
    {
        $username = $this->username($request);
        if ($username === '' || ! Schema::hasTable('library_staff')) return null;

        return DB::table('library_staff')
            ->where('smartschool_username', $username)
            ->where('is_active', 1)
            ->value('role');
    }

    public function isManager(Request $request): bool
    {
        return $this->role($request) === 'ict_coordinator';
    }

    public function isOperator(Request $request): bool
    {
        // Initial cautious policy: ICT manages, secretariat and TAC can operate loans.
        return in_array($this->role($request), ['ict_coordinator', 'secretariaat', 'tac'], true);
    }

    public function requireManager(Request $request): void
    {
        abort_unless($this->isManager($request), 403, 'Alleen ICT-coördinatoren hebben beheertoegang.');
    }

    public function requireOperator(Request $request): void
    {
        abort_unless($this->isOperator($request), 403, 'Je hebt geen rechten om uitleningen te wijzigen.');
    }
}
