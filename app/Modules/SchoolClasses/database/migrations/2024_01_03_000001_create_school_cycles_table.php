<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_cycles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique(); // maternelle, primaire, college, lycee
            $table->string('label', 100);
            $table->integer('sort_order')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_cycles');
    }
};
