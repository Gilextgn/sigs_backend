<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notifications de traçabilité : tout encaissement, annulation, renvoi de
 * reçu ou clôture de caisse fait par quelqu'un d'autre qu'un administrateur
 * est signalé à chaque administrateur de l'école (une ligne par destinataire,
 * pour que chacun ait son propre « lu / non lu »).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id')->index();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action_code', 60);
            $table->string('title', 160);
            $table->text('body')->nullable();
            $table->string('entity_name', 60)->nullable();
            $table->string('entity_id', 60)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_notifications');
    }
};
