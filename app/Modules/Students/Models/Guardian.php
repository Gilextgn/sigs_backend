<?php

namespace Modules\Students\Models;

use Illuminate\Database\Eloquent\Model;

class Guardian extends Model
{
    public $timestamps = false;

    protected $fillable = ['full_name', 'relationship_label', 'phone', 'address'];

    protected function casts(): array
    {
        return [
            // Champs sensibles chiffrés au repos, comme dans Security::encrypt() /
            // les colonnes VARBINARY d'origine (schema.sql). Laravel gère la clé
            // via APP_KEY (AES-256-CBC) au lieu du AES-256-GCM fait main.
            'full_name' => 'encrypted',
            'phone' => 'encrypted',
            'address' => 'encrypted',
        ];
    }

    public function students()
    {
        return $this->hasMany(Student::class);
    }
}
