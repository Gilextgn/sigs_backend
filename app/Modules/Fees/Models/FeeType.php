<?php

namespace Modules\Fees\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\AcademicYears\Models\AcademicYear;
use Modules\SchoolClasses\Models\SchoolClass;

class FeeType extends Model
{
    use \App\Support\BelongsToSchool;
    use \App\Support\Audited;

    /** Nom affiché dans le journal d'audit. */
    protected string $auditName = 'frais';

    /** Mois d'une année scolaire, dans l'ordre de l'année (septembre → août). */
    public const SCHOOL_YEAR_MONTHS = [9, 10, 11, 12, 1, 2, 3, 4, 5, 6, 7, 8];

    public const DEFAULT_MONTHS = [9, 10, 11, 12, 1, 2, 3, 4, 5, 6];

    public const MONTH_LABELS = [
        1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril', 5 => 'mai', 6 => 'juin',
        7 => 'juillet', 8 => 'août', 9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre',
    ];

    protected $fillable = ['school_id', 'code', 'label', 'category', 'amount', 'billing_cycle', 'months', 'is_active', 'is_mandatory'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'months' => 'array',
            'is_active' => 'boolean',
            'is_mandatory' => 'boolean',
        ];
    }

    public function classes()
    {
        return $this->belongsToMany(SchoolClass::class, 'fee_type_classes', 'fee_type_id', 'class_id');
    }

    public function isMonthly(): bool
    {
        return $this->billing_cycle === 'monthly';
    }

    /** Mois facturés, rangés dans l'ordre de l'année scolaire. */
    public function billedMonths(): array
    {
        $months = array_map('intval', $this->months ?: self::DEFAULT_MONTHS);

        return array_values(array_filter(self::SCHOOL_YEAR_MONTHS, fn ($m) => in_array($m, $months, true)));
    }

    public static function monthLabel(int $month): string
    {
        return self::MONTH_LABELS[$month] ?? (string) $month;
    }

    /**
     * Un mois est dû dès son premier jour : septembre 2026 pour l'année
     * 2026-2027, janvier 2027, etc. Les mois à venir restent payables
     * d'avance, mais ne comptent pas encore comme dette.
     */
    public static function monthIsDue(int $month, ?AcademicYear $year, ?Carbon $today = null): bool
    {
        $startYear = $year?->date_start?->year
            ?? (preg_match('/^(\d{4})/', (string) $year?->code, $m) ? (int) $m[1] : null);

        if ($startYear === null) {
            return true;
        }

        $startMonth = $year?->date_start?->month ?? 9;
        $calendarYear = $month >= $startMonth ? $startYear : $startYear + 1;

        return Carbon::create($calendarYear, $month, 1)->startOfDay()->lte($today ?? now());
    }
}
