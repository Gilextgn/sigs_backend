<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Modules\Security\Models\AuditLog;

/**
 * Traçabilité automatique : toute création, modification ou suppression
 * d'un modèle sensible (tarifs, élèves, utilisateurs, paie…) faite pendant
 * une requête est inscrite au journal d'audit, avec l'auteur et le détail
 * « avant → après ». Aucun contrôleur ne peut l'oublier.
 *
 * Un modèle peut définir :
 *  - $auditName   : nom lisible (« tranche », « élève »…) ;
 *  - auditLabel() : libellé de la ligne (ex. « 2ème tranche — CE1 ») ;
 *  - $auditHidden : champs jamais recopiés en clair (mot de passe, champs
 *    chiffrés…) : on note seulement qu'ils ont changé.
 */
trait Audited
{
    protected static function bootAudited(): void
    {
        static::created(fn (Model $model) => $model->writeAudit('created', null, $model->auditableValues($model->getAttributes())));
        static::updated(function (Model $model) {
            $changed = array_diff_key($model->getChanges(), array_flip(['updated_at', 'created_at', 'remember_token', 'last_login_at']));
            if ($changed === []) {
                return;
            }
            $before = array_intersect_key($model->getOriginal(), $changed);
            $model->writeAudit('updated', $model->auditableValues($before), $model->auditableValues($changed));
        });
        static::deleted(fn (Model $model) => $model->writeAudit('deleted', $model->auditableValues($model->getOriginal()), null));
    }

    private function writeAudit(string $event, ?array $before, ?array $after): void
    {
        // Seeders, migrations, commandes : pas d'auteur, pas de ligne.
        if (! request()->user()) {
            return;
        }

        $name = property_exists($this, 'auditName') ? $this->auditName : class_basename($this);
        $code = str_replace(' ', '_', strtolower(class_basename($this))).'.'.$event;

        AuditLog::create([
            'school_id' => $this->school_id ?? CurrentSchool::id(),
            'actor_user_id' => request()->user()->id,
            'action_code' => $code,
            'entity_name' => $name,
            'entity_id' => (string) $this->getKey(),
            'entity_label' => mb_substr((string) (method_exists($this, 'auditLabel') ? $this->auditLabel() : ($this->label ?? $this->full_name ?? $this->getKey())), 0, 180),
            'changes_json' => ['before' => $before, 'after' => $after],
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);

        \Modules\Security\Services\AdminNotifier::fromModelAudit($code, $name, $this, $before, $after);
    }

    /** Valeurs lisibles : champs cachés masqués, horodatages et clés techniques retirés. */
    private function auditableValues(array $values): array
    {
        $hidden = array_merge(['password', 'remember_token'], property_exists($this, 'auditHidden') ? $this->auditHidden : []);
        $values = array_diff_key($values, array_flip(['created_at', 'updated_at', 'school_id']));

        foreach ($values as $key => $value) {
            if (in_array($key, $hidden, true)) {
                $values[$key] = '••• (modifié)';
            } elseif (is_string($value) && mb_strlen($value) > 300) {
                $values[$key] = mb_substr($value, 0, 300).'…';
            }
        }

        return $values;
    }
}
