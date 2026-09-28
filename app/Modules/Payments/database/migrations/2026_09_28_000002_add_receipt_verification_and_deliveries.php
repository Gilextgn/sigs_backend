<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * - verification_token : imprimé en QR code sur le reçu. Le parent le scanne
 *   et voit ce que le serveur a enregistré (ou que le paiement a été annulé) :
 *   un reçu fait à la main ou un montant gonflé ne passe plus.
 * - receipt_deliveries : trace de chaque envoi du reçu au parent (mail,
 *   WhatsApp), consultable par le directeur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('verification_token', 40)->nullable()->unique();
        });

        // Les reçus déjà émis deviennent vérifiables eux aussi.
        DB::table('payments')->whereNull('verification_token')->orderBy('id')->chunkById(500, function ($payments) {
            foreach ($payments as $payment) {
                DB::table('payments')->where('id', $payment->id)->update(['verification_token' => Str::random(40)]);
            }
        });

        Schema::create('receipt_deliveries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id')->index();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->string('channel', 20);   // email | whatsapp
            $table->string('recipient', 120); // masqué : le directeur voit à qui, sans exposer le contact complet
            $table->string('status', 20);    // sent | failed
            $table->text('error')->nullable();
            $table->foreignId('triggered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_deliveries');
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['verification_token']);
            $table->dropColumn('verification_token');
        });
    }
};
