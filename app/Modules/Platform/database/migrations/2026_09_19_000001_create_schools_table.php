<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->dateTime('suspended_at')->nullable();
            $table->string('suspension_reason', 255)->nullable();
            $table->date('subscription_due_at')->nullable();
            // Suspension automatique une fois l'échéance (+ délai de grâce) dépassée.
            $table->boolean('auto_suspend')->default(false);
            $table->unsignedSmallInteger('grace_days')->default(0);
            $table->timestamps();
        });

        Schema::create('school_status_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id')->index();
            $table->string('action', 40);
            $table->string('reason', 255)->nullable();
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // L'établissement historique devient l'école n°1 : toutes les données
        // existantes portent déjà school_id = 1. Sans id explicite, la
        // séquence PostgreSQL reste cohérente pour les écoles suivantes.
        $existingName = DB::table('school_settings')
            ->where('school_id', 1)
            ->where('setting_key', 'school_name')
            ->value('setting_value');

        DB::table('schools')->insert([
            'name' => $existingName ?: 'Mon établissement',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('school_status_events');
        Schema::dropIfExists('schools');
    }
};
