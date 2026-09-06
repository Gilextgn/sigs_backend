<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique(); // ex: 2026-2027
            $table->string('label', 60);
            $table->boolean('is_active')->default(false);
            $table->date('date_start')->nullable();
            $table->date('date_end')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_years');
    }
};
