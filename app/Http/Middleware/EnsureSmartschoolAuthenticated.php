<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureSmartschoolAuthenticated
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // Staat er een user in de sessie?
        if (!$request->session()->has('ss_user')) {
            // Bewaar waar de gebruiker eigenlijk heen wilde
            $request->session()->put('intended_url', $request->fullUrl());
            return redirect()->route('login');
            
        }

        // Deel ss_user overal met views (val terug op lege array)
        view()->share('ss_user', $request->session()->get('ss_user', []));

        return $next($request);
    }
}
