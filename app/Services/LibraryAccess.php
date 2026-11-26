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

    public const EXTERNAL_ROLES = ['leerling'=>'Leerling','coaccount'=>'Ouder / voogd (co-account)'];

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
        'lvs.secretariat_visits.manage',
        'beheer.permissions.manage',
        'beheer.staff.manage', 'beheer.students.manage',
        'beheer.smartschool_test.send', 'beheer.student_sync.manage',
        'beheer.staff_sync.manage', 'beheer.scheduled_tasks.view',
        'beheer.sent_items.view',
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
        if (in_array($request->session()->get('ss_user.principal_type'),['leerling','coaccount'],true)) return null;
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

    public function staffId(Request $request): ?int
    {
        if ($this->role($request)===null) return null;
        $ids=DB::table('users')->where('schoolid',1)->where('smartschool_gebruikersnaam',$this->username($request))->where('allowed_login',1)->where(fn($q)=>$q->whereNull('smartschool_active')->orWhere('smartschool_active',1))->limit(2)->pluck('id');
        return $ids->count()===1?(int)$ids->first():null;
    }

    public function personalLibraryCan(Request $request,string $permission): bool
    {
        $map=['library.catalog.view'=>'catalog.view','library.availability.view'=>'availability.view','library.view'=>'loans.view','library.history.view'=>'history.view','library.reserve'=>'reserve','library.renew.request'=>'renew.request'];
        return isset($map[$permission]) && $this->has($request,'persoonlijke_bibliotheek.view') && $this->has($request,'persoonlijke_bibliotheek.'.$map[$permission]);
    }

    public function has(Request $request, string $permission): bool
    {
        $principal=$request->session()->get('ss_user.principal_type');
        if ($principal==='leerling') {
            if (!array_key_exists($permission,config('portal_permissions.bibliotheek.permissions',[]))) return false;
            $student=app(ExternalPortalAccess::class)->current($request);
            if (!$student) return false;
            return (bool)DB::table('portal_student_permissions')->where('leerling_id',$student->id)->where('permission',$permission)->value('allowed');
        }
        $role = $this->role($request);
        if ($role === null) return false;

        $known = collect(config('portal_permissions', []))
            ->flatMap(fn ($module) => array_keys($module['permissions'] ?? []))
            ->contains($permission);
        if (! $known) return false;

        // ICT-coördinator behoudt altijd volledige beheertoegang.
        if ($role === 'ict_coordinator') return true;
        if (str_starts_with($permission,'leerling.') || str_starts_with($permission,'coaccount.')) return false;
        if (Schema::hasTable('portal_user_permissions')) {
            $id=DB::table('users')->where('smartschool_gebruikersnaam',$this->username($request))->where('allowed_login',1)->value('id');
            $override=DB::table('portal_user_permissions')->where('user_id',$id)->where('permission',$permission)->value('allowed');
            if ($override !== null) return (bool)$override;
        }
        // Alle overige rechten worden per schoolrol toegekend.
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
