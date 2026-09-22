<?php

namespace App\Support;

use App\Models\User;
use Modules\Platform\Models\School;

/**
 * Tout ce qui concerne l'état d'accès d'un compte à son établissement :
 * chargement de l'école, message de suspension, charge utile renvoyée au
 * frontend à la connexion et sur /api/me.
 */
class SchoolAccess
{
    /** L'école du compte, relue à chaque requête : une suspension doit prendre effet aussitôt. */
    public static function resolve(User $user): ?School
    {
        if ($user->school_id === null) {
            return null;
        }

        return School::find($user->school_id);
    }

    public static function suspensionPayload(?School $school): array
    {
        $kind = $school?->suspensionKind();

        return [
            'code' => 'school_suspended',
            'message' => $kind === 'payment'
                ? "L'accès de votre établissement est suspendu : l'abonnement n'a pas été réglé à l'échéance."
                : "L'accès de votre établissement est suspendu.",
            'school_name' => $school?->name,
            'kind' => $kind,
            'reason' => $school?->suspension_reason,
            'support_whatsapp' => config('school.support_whatsapp'),
        ];
    }

    /** Utilisateur tel que le frontend le reçoit (login et /api/me). */
    public static function userPayload(User $user): array
    {
        $school = self::resolve($user);

        return [
            'id' => $user->id,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'role' => $user->role?->code,
            'permissions' => $user->permissions(),
            'school' => $school?->summary(),
            'must_change_password' => (bool) $user->must_change_password,
        ];
    }
}
