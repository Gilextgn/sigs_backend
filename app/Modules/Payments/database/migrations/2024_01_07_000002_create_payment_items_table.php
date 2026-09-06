<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->enum('item_type', ['TRANCHE', 'AUTRE_FRAIS']);
            $table->foreignId('tuition_installment_id')->nullable()->constrained('tuition_installments');
            $table->foreignId('fee_type_id')->nullable()->constrained('fee_types');
            $table->decimal('expected_amount', 12, 2);
            $table->decimal('paid_amount', 12, 2);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_items');
    }
};
