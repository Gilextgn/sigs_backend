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

        // « a|b » : l'un OU l'autre de ces droits suffit.
        $accepted = explode('|', $permission);
        if (! collect($accepted)->contains(fn ($code) => $user->hasPermission($code))) {
            // Message lisible (libellé du droit) plutôt qu'un code technique.
            $permission = $accepted[0];
            $label = \Modules\Users\Models\Permission::where('code', $permission)->value('label') ?? $permission;

            return response()->json([
                'code' => 'permission_denied',
                'permission' => $permission,
                'message' => "Votre compte n'a pas le droit de : ".mb_strtolower($label).". Demandez-le à l'administrateur si besoin.",
            ], 403);
        }

        return $next($request);
    }
}
