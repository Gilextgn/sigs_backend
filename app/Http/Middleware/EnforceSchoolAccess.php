<?php

namespace App\Http\Middleware;

use App\Support\SchoolAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Appliqué à toutes les routes des établissements :
 *  1. fixe l'établissement de la requête (c'est ce qui isole les données) ;
 *  2. coupe l'accès à une école suspendue, y compris sur une session déjà ouverte ;
 *  3. refuse au compte plateforme (sans école) toute route d'établissement.
 *
 * Les routes d'authentification restent ouvertes : un utilisateur d'une école
 * suspendue doit pouvoir se déconnecter, et se connecter pour voir pourquoi.
 */
class EnforceSchoolAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('sanctum');

        // Invité : l'authentification (auth:sanctum) répondra 401 si la route l'exige.
        // Aucun établissement n'est fixé : rien à lire sans compte.
        if (! $user) {
            app()->forgetInstance('currentSchoolId');

            return $next($request);
        }

        $school = SchoolAccess::resolve($user);

        // Le filtrage doit être actif même quand on refuse la requête ensuite,
        // pour qu'aucun code ultérieur ne lise sans filtre.
        app()->instance('currentSchoolId', $user->school_id);

        if ($request->is('api/auth/*')) {
            return $next($request);
        }

        // /api/me sert à savoir où envoyer chacun : le compte plateforme y a droit.
        if ($user->school_id === null) {
            if ($request->is('api/me')) {
                return $next($request);
            }

            return response()->json([
                'code' => 'platform_account',
                'message' => "Ce compte gère la plateforme : il n'a pas accès aux données d'un établissement.",
            ], 403);
        }

        if ($user->school_id !== null && (! $school || $school->isSuspended())) {
            return response()->json(SchoolAccess::suspensionPayload($school), 403);
        }

        // Mot de passe temporaire (connu de la plateforme) : rien d'autre tant
        // que l'utilisateur n'a pas choisi le sien. /api/me reste ouvert pour
        // que le frontend sache afficher l'écran de changement.
        if ($user->must_change_password && ! $request->is('api/me')) {
            return response()->json([
                'code' => 'password_change_required',
                'message' => 'Choisissez un nouveau mot de passe pour continuer.',
            ], 403);
        }

        return $next($request);
    }
}
