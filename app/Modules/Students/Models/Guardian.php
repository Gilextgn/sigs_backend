<?php

namespace Modules\Students\Models;

use Illuminate\Database\Eloquent\Model;

class Guardian extends Model
{
    use \App\Support\BelongsToSchool;
    use \App\Support\Audited;

    /** Nom affiché dans le journal d'audit. */
    protected string $auditName = 'tuteur';

    /** Champs chiffrés : jamais recopiés en clair dans le journal. */
    protected array $auditHidden = ['full_name', 'phone', 'address', 'email', 'whatsapp'];

    public $timestamps = false;

    protected $fillable = ['school_id', 'full_name', 'relationship_label', 'phone', 'address', 'email', 'whatsapp'];

    protected function casts(): array
    {
        return [
            // Champs sensibles chiffrés au repos, comme dans Security::encrypt() /
            // les colonnes VARBINARY d'origine (schema.sql). Laravel gère la clé
            // via APP_KEY (AES-256-CBC) au lieu du AES-256-GCM fait main.
            'full_name' => 'encrypted',
            'phone' => 'encrypted',
            'address' => 'encrypted',
            'email' => 'encrypted',
            'whatsapp' => 'encrypted',
        ];
    }

    public function students()
    {
        return $this->hasMany(Student::class);
    }
}
