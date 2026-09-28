<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Niveau d'un enseignant : il intervient soit au primaire, soit au collège,
 * jamais les deux. Le niveau fixe le mode de paie : primaire = salaire
 * mensuel fixe, collège = payé à l'heure.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->string('level', 20)->default('secondary')->after('subject');
        });

        // Enseignants existants : déduit de leur mode de paie actuel.
        DB::table('teachers')->where('pay_mode', 'monthly')->update(['level' => 'primary']);
    }

    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->dropColumn('level');
        });
    }
};
