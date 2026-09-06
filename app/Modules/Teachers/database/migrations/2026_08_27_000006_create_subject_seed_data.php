<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            ['code' => 'francais', 'label' => 'Français'],
            ['code' => 'mathematiques', 'label' => 'Mathématiques'],
            ['code' => 'anglais', 'label' => 'Anglais'],
            ['code' => 'sciences', 'label' => 'Sciences'],
        ] as $subject) {
            DB::table('subjects')->updateOrInsert(['school_id' => 1, 'code' => $subject['code']], $subject + ['is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        DB::table('subjects')->where('school_id', 1)->whereIn('code', ['francais', 'mathematiques', 'anglais', 'sciences'])->delete();
    }
};
