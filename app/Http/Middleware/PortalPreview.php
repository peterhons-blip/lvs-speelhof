<?php

namespace App\Http\Middleware;

use App\Services\LibraryAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PortalPreview
{
    public function handle(Request $request, Closure $next)
    {
        $preview = $request->session()->get('portal_preview');
        if (!$preview) return $next($request);
        if ($request->routeIs('portal-preview.stop')) return $next($request);
        $access = app(LibraryAccess::class);
        $actor = $request->session()->get('ss_user');
        $valid = $actor && $access->role($request)==='ict_coordinator'
            && $access->username($request) === $preview['actor'] && time() < $preview['expires_at'];
        if (isset($preview['target_role'])) {
            $role=$preview['target_role'];
            $external=app(\App\Services\ExternalPortalAccess::class);
            $username=$valid ? DB::table('leerlingen')->where('id',$preview['target_id'])->where('schoolid',1)->value('gebruikersnaam') : null;
            $student=$username ? $external->student($username,$role,(int)$preview['target_id']) : null;
            if (!$student || !$external->has($role,'dashboard.view',(int)$student->id)) {
                $request->session()->forget('portal_preview');
                return redirect()->route('home');
            }
            if ($request->routeIs('logout')) { $request->session()->forget('portal_preview'); return $next($request); }
            if (!in_array($request->method(),['GET','HEAD'],true)) return redirect()->route('external.dashboard');
            if (!$request->routeIs('external.dashboard','home','bibliotheek.*')) abort(403);
            $request->session()->put('ss_user',['id'=>$student->id,'voornaam'=>$role==='leerling'?$student->voornaam:'Co-account','naam'=>$role==='leerling'?$student->naam:'(testmodus)','gebruikersnaam'=>$student->gebruikersnaam,'principal_type'=>$role,'leerling_id'=>$student->id,'platform'=>$actor['platform']??null,'groupname'=>$role]);
            try {
                $response=$next($request);
                $response->headers->set('Cache-Control','private, no-store');
                return $response;
            } finally { $request->session()->put('ss_user',$actor); }
        }
        $target = $valid ? DB::table('users')->join('groups','groups.id','=','users.groupId')
            ->where('users.id',$preview['target_id'])->where('users.allowed_login',1)
            ->where(fn($q)=>$q->whereNull('users.smartschool_active')->orWhere('users.smartschool_active',1))
            ->select('users.id','users.voornaam','users.name','users.smartschool_gebruikersnaam','users.groupId','groups.groupname')->first() : null;
        if (!$target || !$target->smartschool_gebruikersnaam || !in_array($target->groupname,LibraryAccess::ROLES,true) || $target->groupname===LibraryAccess::ROLES['ict_coordinator']) {
            Log::notice('Portal staff preview expired or invalid',['actor'=>$preview['actor'],'target'=>$preview['target_id']]);
            $request->session()->forget('portal_preview');
            return redirect()->route('home');
        }
        if ($request->routeIs('logout')) {
            $request->session()->forget('portal_preview');
            Log::notice('Portal staff preview ended by logout',['actor'=>$preview['actor'],'target'=>$preview['target_id']]);
            return $next($request);
        }
        if (!in_array($request->method(),['GET','HEAD'],true) || $request->routeIs('login','smartschool.callback') || $request->is('fake-login')) {
            return redirect()->route('home')->with('preview_warning','Testmodus is alleen-lezen. Er werd niets opgeslagen.');
        }
        $request->session()->put('ss_user',[
            'id'=>$target->id,'voornaam'=>$target->voornaam,'naam'=>$target->name,
            'gebruikersnaam'=>$target->smartschool_gebruikersnaam,'platform'=>$actor['platform']??null,
            'groupId'=>$target->groupId,'groupname'=>$target->groupname,
        ]);
        try {
            $response = $next($request);
            $response->headers->set('Cache-Control','no-store, private');
            return $response;
        } finally {
            $request->session()->put('ss_user',$actor);
        }
    }
}
