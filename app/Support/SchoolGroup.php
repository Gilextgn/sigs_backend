<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;
use Modules\Platform\Models\School;

/**
 * Groupe scolaire (plusieurs sites sous un même directeur).
 *
 * Les secrétaires, caissiers, comptables restent sur leur site. Les
 * administrateurs d'un site du groupe (le directeur) peuvent travailler sur
 * n'importe quel site du groupe : l'écran choisit le site via l'en-tête
 * X-Site-Id, et chaque requête ne voit alors que les données de ce site.
 */
class SchoolGroup
{
    /** Identifiants des sites du groupe de cette école (elle seule si elle n'est pas groupée). */
    public static function siteIds(int $schoolId): array
    {
        $code = School::whereKey($schoolId)->value('group_code');

        return $code ? School::where('group_code', $code)->orderBy('id')->pluck('id')->all() : [$schoolId];
    }

    /** Sites qu'un compte peut ouvrir : tout le groupe pour un administrateur, son site sinon. */
    public static function accessibleSites(User $user): Collection
    {
        if ($user->school_id === null) {
            return collect();
        }

        $ids = $user->loadMissing('role')->role?->code === 'admin' ? self::siteIds($user->school_id) : [$user->school_id];

        return School::whereIn('id', $ids)->orderBy('id')->get();
    }

    /** Libellé d'un site : son libellé de site, sinon son nom. */
    public static function label(School $school): string
    {
        return $school->site_label ?: $school->name;
    }
}
