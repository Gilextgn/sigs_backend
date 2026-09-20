<?php

namespace App\Support;

/**
 * Établissement de la requête en cours. Le middleware EnforceSchoolAccess le
 * fixe à chaque requête authentifiée ; hors requête (commandes, seeders,
 * tests qui créent des modèles directement) on retombe sur l'établissement 1,
 * l'école historique de l'application.
 */
class CurrentSchool
{
    public const DEFAULT_ID = 1;

    public static function id(): ?int
    {
        return app()->bound('currentSchoolId') ? app('currentSchoolId') : self::DEFAULT_ID;
    }

    /** Vrai quand une requête a fixé l'établissement (donc que le filtrage est actif). */
    public static function isBound(): bool
    {
        return app()->bound('currentSchoolId');
    }
}
