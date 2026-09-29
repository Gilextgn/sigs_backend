<?php

namespace Modules\Security\Services;

use App\Models\User;
use Modules\Security\Models\AdminNotification;
use Modules\Security\Models\AuditLog;

/**
 * Traçabilité : toute action de caisse faite par un autre acteur qu'un
 * administrateur (secrétaire, caissier, comptable…) est notifiée à chaque
 * administrateur de l'école. Les actions d'un administrateur restent dans le
 * journal d'audit sans notification.
 */
class AdminNotifier
{
    /** Actions notifiées (codes du journal d'audit). */
    public const ACTIONS = ['payment.created', 'payment.deleted', 'payment.receipt_sent', 'cash.closed', 'cash.reopened', 'cash.handover'];

    public static function fromAudit(AuditLog $log): void
    {
        if (! in_array($log->action_code, self::ACTIONS, true) || ! $log->school_id) {
            return;
        }

        $actor = $log->actor_user_id ? User::with('role')->find($log->actor_user_id) : null;
        if (! $actor || $actor->role?->code === 'admin') {
            return;
        }

        $admins = self::recipients((int) $log->school_id, $actor->id);

        if ($admins->isEmpty()) {
            return;
        }

        [$title, $body] = self::describe($log, $actor);
        $now = now();

        AdminNotification::insert($admins->map(fn ($adminId) => [
            'school_id' => $log->school_id,
            'user_id' => $adminId,
            'actor_user_id' => $actor->id,
            'action_code' => $log->action_code,
            'title' => mb_substr($title, 0, 160),
            'body' => $body,
            'entity_name' => $log->entity_name,
            'entity_id' => $log->entity_id,
            'created_at' => $now,
        ])->all());
    }

    /**
     * Changements sensibles faits par un non-administrateur (tarifs, élèves,
     * comptes, paie) : l'admin en est averti comme pour la caisse.
     */
    public const SENSITIVE = [
        'tuitioninstallment.updated', 'tuitioninstallment.deleted', 'tuitioninstallment.created',
        'feetype.updated', 'feetype.deleted',
        'schoolclass.updated', 'schoolclass.deleted',
        'student.deleted',
        'user.created', 'user.updated', 'user.deleted',
        'payrollentry.created', 'payrollentry.updated',
    ];

    public static function fromModelAudit(string $code, string $name, \Illuminate\Database\Eloquent\Model $model, ?array $before, ?array $after): void
    {
        $actor = request()->user();
        if (! in_array($code, self::SENSITIVE, true) || ! $actor || $actor->loadMissing('role')->role?->code === 'admin') {
            return;
        }
        $schoolId = (int) ($model->school_id ?? \App\Support\CurrentSchool::id());
        $admins = self::recipients($schoolId, $actor->id);
        if ($admins->isEmpty()) {
            return;
        }

        $verb = str_ends_with($code, '.created') ? 'créé(e)' : (str_ends_with($code, '.deleted') ? 'supprimé(e)' : 'modifié(e)');
        $label = method_exists($model, 'auditLabel') ? $model->auditLabel() : ($model->label ?? $model->full_name ?? '#'.$model->getKey());
        // Détail « champ : avant → après » des champs visibles (les champs chiffrés restent masqués).
        $diff = collect($after ?? $before ?? [])->except(['id'])->map(fn ($value, $key) => $key.' : '.(isset($before[$key]) && $after !== null ? self::show($before[$key]).' → ' : '').self::show($value))->take(4)->implode(' · ');
        $now = now();

        AdminNotification::insert($admins->map(fn ($adminId) => [
            'school_id' => $schoolId,
            'user_id' => $adminId,
            'actor_user_id' => $actor->id,
            'action_code' => $code,
            'title' => mb_substr(ucfirst($name).' '.$verb.' par '.$actor->full_name, 0, 160),
            'body' => mb_substr(trim($label.($diff !== '' ? ' — '.$diff : '')), 0, 500),
            'entity_name' => $name,
            'entity_id' => (string) $model->getKey(),
            'created_at' => $now,
        ])->all());
    }

    /**
     * Administrateurs à prévenir : ceux de l'école concernée, et le directeur
     * d'un groupe scolaire qui supervise plusieurs sites.
     */
    private static function recipients(int $schoolId, int $actorId): \Illuminate\Support\Collection
    {
        $schoolIds = \App\Support\SchoolGroup::siteIds($schoolId);

        return User::withoutGlobalScopes()
            ->whereHas('role', fn ($q) => $q->where('code', 'admin'))
            ->whereIn('school_id', $schoolIds)
            ->where('status', 'active')
            ->where('id', '!=', $actorId)
            ->pluck('id');
    }

    private static function show($value): string
    {
        return is_scalar($value) || $value === null ? mb_substr((string) ($value ?? '—'), 0, 40) : '…';
    }

    private static function describe(AuditLog $log, User $actor): array
    {
        $d = $log->details_json ?? [];
        $who = $actor->full_name;
        $money = fn ($v) => number_format((float) $v, 0, ',', ' ').' XOF';
        $ref = $d['reference_code'] ?? '';

        return match ($log->action_code) {
            'payment.created' => [
                "Encaissement de {$money($d['total_paid_amount'] ?? 0)} par {$who}",
                trim($ref.' · '.self::studentName($d['student_id'] ?? null), ' ·'),
            ],
            'payment.deleted' => [
                "Paiement annulé par {$who}",
                trim($ref.(isset($d['total_paid_amount']) ? ' · '.$money($d['total_paid_amount']) : '').(isset($d['reason']) ? ' · motif : '.$d['reason'] : ''), ' ·'),
            ],
            'payment.receipt_sent' => ["Reçu renvoyé au parent par {$who}", $ref],
            'cash.closed' => [
                "Caisse du jour clôturée par {$who}",
                'Attendu '.$money($d['expected_amount'] ?? 0).' · compté '.$money($d['counted_amount'] ?? 0)
                    .((float) ($d['difference'] ?? 0) != 0 ? ' · écart '.$money($d['difference']) : ' · caisse juste'),
            ],
            'cash.reopened' => ["Caisse rouverte par {$who}", ($d['closing_date'] ?? '').(isset($d['reason']) ? ' · motif : '.$d['reason'] : '')],
            'cash.handover' => ["Remise de caisse reçue par {$who}", 'Attendu '.$money($d['expected_amount'] ?? 0).' · reçu '.$money($d['received_amount'] ?? 0)],
            default => [$log->action_code.' par '.$who, null],
        };
    }

    private static function studentName(?int $studentId): string
    {
        $student = $studentId ? \Modules\Students\Models\Student::find($studentId) : null;

        return $student ? trim($student->last_name.' '.$student->first_name) : '';
    }
}
