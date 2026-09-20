<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Réserve la console de la plateforme au propriétaire : un compte SANS
 * établissement portant la permission platform.manage. Un compte d'école ne
 * passe jamais, même si un droit plateforme lui avait été attribué par erreur.
 *
 * Il retire aussi l'établissement de la requête : la plateforme n'opère
 * sur aucune école en particulier.
 */
class EnsurePlatformOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_if(! $user, 401, 'Authentification requise.');

        abort_unless(
            $user->school_id === null && $user->hasPermission('platform.manage'),
            403,
            'Accès réservé au propriétaire de la plateforme.'
        );

        app()->forgetInstance('currentSchoolId');

        return $next($request);
    }
}
