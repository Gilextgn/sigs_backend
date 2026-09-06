<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id')->default(1);
            $table->string('setting_key', 80);
            $table->text('setting_value')->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'setting_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_settings');
    }
};
