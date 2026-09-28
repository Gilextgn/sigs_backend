<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SchoolClasses\Support\PedagogicalOrder;

/**
 * Ordre pédagogique des classes (Maternelle → CI → CP → CE1 … → Tle) au lieu
 * de l'ordre alphabétique. Les classes existantes reçoivent un rangement
 * deviné d'après leur cycle et leur libellé ; le directeur l'ajuste ensuite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('label');
        });

        $classes = DB::table('classes')
            ->leftJoin('school_cycles', 'school_cycles.id', '=', 'classes.cycle_id')
            ->get(['classes.id', 'classes.school_id', 'classes.label', DB::raw('COALESCE(school_cycles.sort_order, 0) as cycle_order')]);

        foreach ($classes->groupBy('school_id') as $schoolClasses) {
            foreach (PedagogicalOrder::sort($schoolClasses) as $position => $id) {
                DB::table('classes')->where('id', $id)->update(['sort_order' => $position + 1]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
