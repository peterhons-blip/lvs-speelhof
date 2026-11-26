<?php

namespace App\Http\Controllers;

use App\Services\LibraryAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PortalPreviewController extends Controller
{
    public function start(Request $request, LibraryAccess $access, int $staff)
    {
        abort_unless(!$request->session()->has('portal_preview') && $access->role($request) === 'ict_coordinator', 403);
        $target = DB::table('users')->join('groups','groups.id','=','users.groupId')
            ->where('users.id',$staff)->where('users.allowed_login',1)
            ->where(fn($q)=>$q->whereNull('users.smartschool_active')->orWhere('users.smartschool_active',1))
            ->select('users.id','users.voornaam','users.name','users.smartschool_gebruikersnaam','users.groupId','groups.groupname')->first();
        abort_unless($target && $target->smartschool_gebruikersnaam && in_array($target->groupname, LibraryAccess::ROLES,true)
            && $target->groupname !== LibraryAccess::ROLES['ict_coordinator'], 403);
        $actor = $access->username($request);
        Log::notice('Portal staff preview started',['actor'=>$actor,'target'=>$staff]);
        $request->session()->regenerate();
        $request->session()->put('portal_preview',['target_id'=>$staff,'actor'=>$actor,'expires_at'=>time()+1800]);
        return redirect()->route('home');
    }

    public function startExternal(Request $request, LibraryAccess $access, string $role, int $student)
    {
        abort_unless(!$request->session()->has('portal_preview') && $access->role($request)==='ict_coordinator',403);
        abort_unless(in_array($role,['leerling','coaccount'],true),404);
        $username=DB::table('leerlingen')->where('id',$student)->where('schoolid',1)->value('gebruikersnaam');
        $external=app(\App\Services\ExternalPortalAccess::class);
        $target=$external->student((string)$username,$role,$student);
        abort_unless($target && $external->has($role,'dashboard.view',$student),403,'Dit portaal is niet beschikbaar voor deze leerling.');
        $actor=$access->username($request);
        Log::notice('External portal preview started',['actor'=>$actor,'target'=>$student,'role'=>$role]);
        $request->session()->regenerate();
        $request->session()->put('portal_preview',['target_id'=>$student,'target_role'=>$role,'actor'=>$actor,'expires_at'=>time()+1800]);
        return redirect()->route('external.dashboard');
    }

    public function stop(Request $request)
    {
        $preview = $request->session()->pull('portal_preview');
        if ($preview) Log::notice('Portal staff preview stopped',['actor'=>$preview['actor'],'target'=>$preview['target_id']]);
        $request->session()->regenerate();
        return redirect()->route('home');
    }
}
