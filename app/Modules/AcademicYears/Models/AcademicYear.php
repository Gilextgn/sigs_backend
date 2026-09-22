<?php

namespace Modules\AcademicYears\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicYear extends Model
{
    use \App\Support\BelongsToSchool;

    public $timestamps = false;

    protected $fillable = ['school_id', 'code', 'label', 'is_active', 'date_start', 'date_end', 'closed_at', 'closed_by_user_id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'date_start' => 'date',
            'date_end' => 'date',
            'closed_at' => 'datetime',
        ];
    }

    /** Nombre de jours avant la fin de l'année à partir duquel la clôture est permise. */
    public const CLOSING_WINDOW_DAYS = 60;

    protected $appends = ['closable_from'];

    /**
     * Fin de l'année : date_end si elle est renseignée, sinon le 31 juillet de
     * la seconde année du code (« 2026-2027 » → 2027-07-31). Null si le code
     * ne permet pas de la déduire.
     */
    public function effectiveEnd(): ?\Illuminate\Support\Carbon
    {
        if ($this->date_end) {
            return $this->date_end->copy();
        }

        return preg_match('/^\d{4}-(\d{4})$/', (string) $this->code, $m)
            ? \Illuminate\Support\Carbon::create((int) $m[1], 7, 31)
            : null;
    }

    /**
     * Garde-fou : on ne clôture pas une année en plein milieu. La clôture
     * n'ouvre qu'à l'approche de la fin (fin − CLOSING_WINDOW_DAYS).
     */
    public function closableFrom(): ?\Illuminate\Support\Carbon
    {
        return $this->effectiveEnd()?->subDays(self::CLOSING_WINDOW_DAYS)->startOfDay();
    }

    public function isClosableNow(): bool
    {
        $from = $this->closableFrom();

        return $from === null || now()->startOfDay()->gte($from);
    }

    public function getClosableFromAttribute(): ?string
    {
        return $this->closableFrom()?->toDateString();
    }
}
