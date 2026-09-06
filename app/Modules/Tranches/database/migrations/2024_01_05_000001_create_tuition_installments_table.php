<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tuition_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->string('label', 120);
            $table->decimal('amount', 12, 2);
            $table->date('due_date')->nullable();
            $table->timestamps();

            $table->unique(['class_id', 'label'], 'uq_installment_per_class');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tuition_installments');
    }
};
