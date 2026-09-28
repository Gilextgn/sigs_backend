<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Niveau d'une matière : primaire, collège, ou les deux (Français,
 * Mathématiques…). Les listes de matières d'un enseignant ne montrent que
 * celles de son niveau. Les matières existantes valent pour les deux.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->string('level', 20)->default('both')->after('label');
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropColumn('level');
        });
    }
};
