<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ce que la plateforme sait de ses clients pour les facturer et les relancer :
 * coordonnées du contact, tarif, et paiements d'abonnement reçus. Ce sont les
 * données de la plateforme, jamais celles de l'école (élèves, paiements…).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->string('contact_name', 180)->nullable();
            // Numéro WhatsApp du contact, chiffres au format international (22901…).
            $table->string('contact_phone', 30)->nullable();
            $table->string('city', 120)->nullable();
            $table->text('notes')->nullable();
            // Montant habituel d'une mensualité : pré-remplit l'encaissement.
            $table->unsignedBigInteger('plan_amount')->nullable();
        });

        Schema::create('school_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id')->index();
            $table->unsignedBigInteger('amount');
            $table->date('paid_at');
            $table->unsignedSmallInteger('months');
            // Échéance avant / après ce paiement : l'historique reste lisible
            // même si l'échéance est retouchée à la main ensuite.
            $table->date('due_before')->nullable();
            $table->date('due_after');
            $table->string('method', 30)->nullable();
            $table->string('reference', 120)->nullable();
            $table->string('note', 255)->nullable();
            $table->unsignedBigInteger('recorded_by_user_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_payments');

        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn(['contact_name', 'contact_phone', 'city', 'notes', 'plan_amount']);
        });
    }
};
