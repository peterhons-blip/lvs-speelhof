<?php
namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\CarbonImmutable;

class ExternalPortalAccess
{
    public function student(string $username, string $role, ?int $id = null): ?object
    {
        if (!in_array($role,['leerling','coaccount'],true) || trim($username)==='') return null;
        $rows=DB::table('leerlingen')->where('schoolid',1)->where('active',1)
            ->where('gebruikersnaam',$username)->limit(2)->get();
        if ($rows->count()!==1) return null;
        $student=$rows->first();
        if ($id!==null && (int)$student->id!==$id) return null;
        // Fail closed for co-accounts when age is unknown or the student is an adult.
        if ($role==='coaccount') {
            if (!$student->geboortedatum) return null;
            try {
                $birth=CarbonImmutable::parse($student->geboortedatum,'Europe/Brussels')->startOfDay();
                if ($birth->isFuture() || $birth->addYearsNoOverflow(18)->lte(CarbonImmutable::today('Europe/Brussels'))) return null;
            } catch (\Throwable $e) { return null; }
        }
        return $student;
    }

    public function current(Request $request): ?object
    {
        $session=$request->session()->get('ss_user',[]);
        $id=(int)($session['leerling_id']??0);
        return $id>0 ? $this->student((string)($session['gebruikersnaam']??''),(string)($session['principal_type']??''),$id) : null;
    }

    public function has(string $role,string $permission,?int $studentId=null): bool
    {
        if (!in_array($role,['leerling','coaccount'],true)) return false;
        $key=$role.'.'.$permission;
        $implemented=['dashboard.view','profile.view','library.catalog.view','library.availability.view','library.view','library.history.view','lvs.view','incidents.view','library.reserve','library.renew.request','library.help','library.help.books.create','library.help.isbn.lookup','library.help.labels.print','library.help.loans.create','library.help.loans.return'];
        if (!in_array($permission,$implemented,true)) return false;
        if (str_starts_with($permission,'library.help') && ($role!=='leerling' || !$studentId)) return false;
        if ($role==='leerling' && $studentId) {
            $override=DB::table('portal_student_permissions')->where('leerling_id',$studentId)->where('permission',$key)->value('allowed');
            if ($override!==null) return (bool)$override;
        }
        if (str_starts_with($permission,'library.help')) return false; // Individual grants only.
        return DB::table('portal_role_permissions')->where('role',$role)->where('permission',$key)->exists();
    }
}
