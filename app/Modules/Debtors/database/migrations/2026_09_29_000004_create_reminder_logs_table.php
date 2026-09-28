<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Relances des familles avant / après l'échéance d'une tranche. Chaque envoi
 * (automatique ou fait à la main) est tracé : on sait qui a été relancé,
 * quand, par quel canal, et on n'envoie jamais deux fois le même jour.
 * Le réglage (texte du message, délais, canaux) vit dans school_settings
 * sous la clé « reminders ».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminder_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id')->index();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('channel', 20);   // email | whatsapp | whatsapp_manual | paper
            $table->string('recipient', 120)->nullable();
            $table->string('status', 20);    // sent | failed
            $table->text('error')->nullable();
            $table->decimal('amount', 12, 2);
            $table->text('message');
            $table->foreignId('sent_by_user_id')->nullable()->constrained('users')->nullOnDelete(); // null = envoi automatique
            $table->timestamp('created_at')->useCurrent();

            $table->index(['student_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_logs');
    }
};
