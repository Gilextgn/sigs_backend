<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Classes dédoublées (CE1 A / CE1 B, CP1 / CP2…) : un groupe est rattaché à
 * une classe principale dont il reprend scolarité, tranches et frais. Les
 * tarifs n'existent qu'une fois, sur la classe principale : pas de copie à
 * tenir synchronisée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->foreignId('parent_class_id')->nullable()->after('cycle_id')->constrained('classes')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_class_id');
        });
    }
};
