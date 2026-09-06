<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Prépare l'architecture multi-tenant (school_id) introduite dans
 * database/migrations/001_permissions_academic_years_multitenant.sql.
 * En mono-école, school_id = 1 pour tout le monde. Le jour où plusieurs
 * écoles sont gérées, cette classe est le seul endroit à faire évoluer.
 */
class ResolveActiveSchool
{
    public function handle(Request $request, Closure $next): Response
    {
        $schoolId = $request->user()?->school_id ?? 1;

        app()->instance('currentSchoolId', $schoolId);

        return $next($request);
    }
}
