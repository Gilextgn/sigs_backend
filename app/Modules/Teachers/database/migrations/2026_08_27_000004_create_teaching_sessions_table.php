<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teaching_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id')->default(1)->index();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('teacher_assignment_id')->constrained('teacher_assignments')->cascadeOnDelete();
            $table->date('session_date');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->unsignedInteger('planned_minutes');
            $table->unsignedInteger('realized_minutes')->default(0);
            $table->enum('status', ['planned', 'completed', 'cancelled'])->default('planned');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['teacher_assignment_id', 'session_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teaching_sessions');
    }
};
