<?php
namespace App\Http\Middleware;

use App\Services\ExternalPortalAccess;
use Closure;
use Illuminate\Http\Request;

class ExternalPortalBoundary
{
    public function handle(Request $request,Closure $next)
    {
        $role=$request->session()->get('ss_user.principal_type');
        if (!in_array($role,['leerling','coaccount'],true)) {
            abort_if($request->routeIs('external.*'),403);
            return $next($request);
        }
        if ($request->routeIs('logout')) return $next($request);
        $access=app(ExternalPortalAccess::class);
        $student=$access->current($request);
        if (!$student || !$access->has($role,'dashboard.view',(int)$student->id)) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('home')->with('error','Je toegang tot dit portaal is niet (meer) toegestaan.');
        }
        if ($request->routeIs('home')) return redirect()->route('external.dashboard');
        abort_unless($request->routeIs('external.*') || ($role==='leerling' && $request->routeIs('bibliotheek.*') && app(\App\Services\LibraryAccess::class)->has($request,'bibliotheek.view')),403);
        $request->attributes->set('external_student',$student);
        $response=$next($request);
        $response->headers->set('Cache-Control','private, no-store');
        return $response;
    }
}
