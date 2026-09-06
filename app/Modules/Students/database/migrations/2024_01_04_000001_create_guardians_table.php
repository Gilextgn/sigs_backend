<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardians', function (Blueprint $table) {
            $table->id();
            $table->text('full_name');        // chiffré (cast 'encrypted')
            $table->string('relationship_label', 50);
            $table->text('phone');            // chiffré
            $table->text('address')->nullable(); // chiffré
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardians');
    }
};
