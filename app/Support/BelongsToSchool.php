<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Isole les données par établissement.
 *
 * Pendant une requête, toute lecture est filtrée sur l'établissement de
 * l'utilisateur connecté et toute création est rattachée à cet
 * établissement. Un identifiant appartenant à une autre école est donc
 * introuvable (404), y compris via une route « /classes/{id} ».
 *
 * Un compte sans établissement (le propriétaire de la plateforme) obtient
 * « school_id IS NULL » : aucune ligne, jamais.
 */
trait BelongsToSchool
{
    protected static function bootBelongsToSchool(): void
    {
        static::addGlobalScope('school', function (Builder $query) {
            if (CurrentSchool::isBound()) {
                $query->where($query->getModel()->getTable().'.school_id', CurrentSchool::id());
            }
        });

        static::creating(function ($model) {
            if (CurrentSchool::isBound() && empty($model->school_id)) {
                $model->school_id = CurrentSchool::id();
            }
        });
    }
}
