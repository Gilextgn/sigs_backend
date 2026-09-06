<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id')->default(1)->index();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->string('reference_code', 40)->unique();
            $table->foreignId('student_id')->constrained('students');
            $table->foreignId('cashier_user_id')->constrained('users');
            $table->date('payment_date');
            $table->decimal('total_paid_amount', 12, 2);
            $table->timestamp('created_at')->useCurrent();
            $table->softDeletes();

            $table->index(['student_id', 'payment_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
