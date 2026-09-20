<?php

namespace App\Support;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

/**
 * Règles de validation limitées à l'école courante.
 *
 * « exists:classes,id » ne passe pas par le filtrage automatique des modèles :
 * sans ce garde-fou, une école pourrait rattacher un élève à la classe (ou au
 * tuteur, à l'enseignant…) d'une autre en devinant un identifiant.
 */
class SchoolRule
{
    public static function exists(string $table, string $column = 'id'): Exists
    {
        return Rule::exists($table, $column)->where('school_id', CurrentSchool::id());
    }

    public static function unique(string $table, string $column): Unique
    {
        return Rule::unique($table, $column)->where('school_id', CurrentSchool::id());
    }
}
