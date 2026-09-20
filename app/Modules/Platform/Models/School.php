<?php

namespace Modules\Platform\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Un établissement client. La plateforme n'en connaît que le statut
 * d'abonnement : jamais ses élèves, ses paiements ni ses classes.
 */
class School extends Model
{
    protected $fillable = [
        'name', 'suspended_at', 'suspension_reason',
        'subscription_due_at', 'auto_suspend', 'grace_days',
    ];

    protected function casts(): array
    {
        return [
            'suspended_at' => 'datetime',
            'subscription_due_at' => 'date',
            'auto_suspend' => 'boolean',
            'grace_days' => 'integer',
        ];
    }

    public function events()
    {
        return $this->hasMany(SchoolStatusEvent::class)->latest('id');
    }

    /** L'échéance est passée (l'école peut encore travailler pendant la période de grâce). */
    public function isOverdue(): bool
    {
        return $this->subscription_due_at !== null && now()->startOfDay()->gt($this->subscription_due_at);
    }

    /** Dernier jour de fonctionnement avant suspension automatique, s'il y en a une. */
    public function blockedFrom(): ?\Illuminate\Support\Carbon
    {
        if (! $this->auto_suspend || $this->subscription_due_at === null) {
            return null;
        }

        return $this->subscription_due_at->copy()->addDays($this->grace_days + 1);
    }

    public function isSuspendedForPayment(): bool
    {
        $blockedFrom = $this->blockedFrom();

        return $blockedFrom !== null && now()->startOfDay()->gte($blockedFrom);
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null || $this->isSuspendedForPayment();
    }

    /** active | overdue | suspended */
    public function status(): string
    {
        if ($this->isSuspended()) {
            return 'suspended';
        }

        return $this->isOverdue() ? 'overdue' : 'active';
    }

    /** manual | payment | null : pourquoi l'école est bloquée. */
    public function suspensionKind(): ?string
    {
        return match (true) {
            $this->suspended_at !== null => 'manual',
            $this->isSuspendedForPayment() => 'payment',
            default => null,
        };
    }

    /** Ce que voit l'école elle-même : son état, sans les notes internes. */
    public function summary(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status(),
            'subscription_due_at' => $this->subscription_due_at?->toDateString(),
            'blocked_from' => $this->blockedFrom()?->toDateString(),
        ];
    }
}
