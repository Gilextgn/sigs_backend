<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id')->default(1)->index();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->foreignId('class_id')->constrained('classes');
            $table->foreignId('guardian_id')->constrained('guardians');
            $table->unsignedSmallInteger('registration_year');
            $table->unsignedInteger('registration_sequence');
            $table->string('matricule', 30)->unique();
            $table->text('first_name');   // chiffré
            $table->text('last_name');    // chiffré
            $table->date('birth_date')->nullable();
            $table->enum('gender', ['F', 'M'])->nullable();
            $table->enum('status', ['active', 'transferred', 'graduated', 'archived'])->default('active');
            $table->timestamps();

            $table->unique(['registration_year', 'registration_sequence'], 'uq_students_registration_seq');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
