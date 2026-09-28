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
    public const ACTIONS = ['payment.created', 'payment.deleted', 'payment.receipt_sent', 'cash.closed', 'cash.reopened'];

    public static function fromAudit(AuditLog $log): void
    {
        if (! in_array($log->action_code, self::ACTIONS, true) || ! $log->school_id) {
            return;
        }

        $actor = $log->actor_user_id ? User::with('role')->find($log->actor_user_id) : null;
        if (! $actor || $actor->role?->code === 'admin') {
            return;
        }

        $admins = User::whereHas('role', fn ($q) => $q->where('code', 'admin'))
            ->where('school_id', $log->school_id)
            ->where('status', 'active')
            ->where('id', '!=', $actor->id)
            ->pluck('id');

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
            default => [$log->action_code.' par '.$who, null],
        };
    }

    private static function studentName(?int $studentId): string
    {
        $student = $studentId ? \Modules\Students\Models\Student::find($studentId) : null;

        return $student ? trim($student->last_name.' '.$student->first_name) : '';
    }
}
