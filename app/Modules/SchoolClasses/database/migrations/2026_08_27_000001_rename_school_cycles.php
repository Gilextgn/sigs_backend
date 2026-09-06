<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('school_cycles')->where('code', 'college')->update(['code' => 'cycle_1', 'label' => 'Collège 1er cycle']);
        DB::table('school_cycles')->where('code', 'lycee')->update(['code' => 'cycle_2', 'label' => 'Collège 2ème cycle']);
    }

    public function down(): void
    {
        DB::table('school_cycles')->where('code', 'cycle_1')->update(['code' => 'college', 'label' => 'Collège']);
        DB::table('school_cycles')->where('code', 'cycle_2')->update(['code' => 'lycee', 'label' => 'Lycée']);
    }
};
