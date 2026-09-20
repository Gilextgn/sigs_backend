<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rend chaque table métier propre à un établissement.
 *
 * - school_id ajouté aux tables qui n'en avaient pas ;
 * - identifiants « uniques » (code de classe, matricule…) uniques PAR école,
 *   sinon deux écoles ne pourraient pas avoir chacune une « 6ème A » ni un
 *   matricule ELV-2026-000001 ;
 * - un compte sans école (propriétaire de la plateforme) devient possible.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['academic_years', 'fee_types', 'tuition_installments', 'payroll_entries', 'guardians'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unsignedBigInteger('school_id')->default(1)->index();
            });
        }

        // Les lignes existantes appartiennent toutes à l'école 1 (valeur par
        // défaut) ; on recale quand même les tables filles sur leur parent.
        DB::statement('UPDATE tuition_installments SET school_id = (SELECT school_id FROM classes WHERE classes.id = tuition_installments.class_id) WHERE class_id IS NOT NULL');
        // Un tuteur appartient à l'école de ses élèves (le tuteur est créé avec eux).
        DB::statement('UPDATE guardians SET school_id = COALESCE((SELECT MIN(school_id) FROM students WHERE students.guardian_id = guardians.id), 1)');
        DB::statement('UPDATE payroll_entries SET school_id = (SELECT school_id FROM teachers WHERE teachers.id = payroll_entries.teacher_id) WHERE teacher_id IS NOT NULL');

        Schema::table('academic_years', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->unique(['school_id', 'code'], 'uq_academic_years_school_code');
        });

        Schema::table('classes', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->unique(['school_id', 'code'], 'uq_classes_school_code');
        });

        Schema::table('fee_types', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->unique(['school_id', 'code'], 'uq_fee_types_school_code');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique(['matricule']);
            $table->dropUnique('uq_students_registration_seq');
            $table->unique(['school_id', 'matricule'], 'uq_students_school_matricule');
            $table->unique(['school_id', 'registration_year', 'registration_sequence'], 'uq_students_school_registration_seq');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('school_id')->nullable()->default(1)->change();
        });
    }

    public function down(): void
    {
        // Retour arrière volontairement absent : recréer des contraintes
        // d'unicité globales échouerait dès qu'une deuxième école existe.
    }
};
