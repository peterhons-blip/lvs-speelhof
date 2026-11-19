<?php

namespace App\Http\Middleware;

use App\Services\LibraryAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePortalPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        app(LibraryAccess::class)->requirePermission($request, $permission);
        return $next($request);
    }
}
