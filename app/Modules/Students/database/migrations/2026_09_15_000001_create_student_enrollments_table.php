<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historique des inscriptions par année, distinct de students.class_id
 * (qui reste la "classe actuelle" dénormalisée dont dépend tout le code
 * existant). Un élève a au plus une ligne par année : la réinscription
 * pour une nouvelle année crée une nouvelle ligne plutôt que d'écraser
 * la précédente, pour pouvoir calculer un reste-dû par année révolue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->foreignId('class_id')->constrained('classes')->restrictOnDelete();
            $table->foreignId('enrolled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['student_id', 'academic_year_id'], 'uq_student_enrollment_per_year');
            $table->index(['academic_year_id', 'class_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_enrollments');
    }
};
