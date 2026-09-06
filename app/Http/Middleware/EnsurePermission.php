<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reprend la règle métier existante : chaque utilisateur possède un set de
 * permissions (via son rôle + des permissions individuelles en surcharge,
 * cf. table user_permissions). Un contrôleur déclare la permission requise :
 *
 *   Route::get('/students', [StudentController::class, 'index'])
 *       ->middleware('permission:students.view');
 */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        abort_if(! $user, 401, 'Authentification requise.');

        abort_unless(
            $user->hasPermission($permission),
            403,
            "Accès refusé : permission '{$permission}' requise."
        );

        return $next($request);
    }
}
