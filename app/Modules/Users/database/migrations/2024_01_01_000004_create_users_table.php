<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id')->default(1)->index();
            $table->foreignId('role_id')->constrained('roles');
            $table->string('full_name', 180);
            $table->string('email', 180)->unique();
            $table->string('password');
            $table->text('phone')->nullable(); // chiffré via cast 'encrypted'
            $table->enum('status', ['active', 'inactive', 'locked'])->default('active');
            $table->dateTime('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
