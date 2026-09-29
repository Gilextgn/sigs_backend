<?php

namespace Modules\Fees\Models;

use Illuminate\Database\Eloquent\Model;

/** Élève inscrit à un frais facultatif (cantine, TD…) : ce frais lui devient dû. */
class FeeSubscription extends Model
{
    use \App\Support\BelongsToSchool;
    use \App\Support\Audited;

    /** Nom affiché dans le journal d'audit. */
    protected string $auditName = 'inscription à un frais';

    public function auditLabel(): string
    {
        return ($this->feeType?->label ?? 'frais').' — élève #'.$this->student_id;
    }

    public $timestamps = false;

    protected $fillable = ['school_id', 'student_id', 'fee_type_id', 'created_at'];

    public function feeType()
    {
        return $this->belongsTo(FeeType::class);
    }
}
