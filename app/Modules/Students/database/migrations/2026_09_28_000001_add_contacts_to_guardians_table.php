<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Canaux d'envoi du reçu au parent. Chiffrés comme le téléphone (cast
 * 'encrypted'), d'où le type text.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guardians', function (Blueprint $table) {
            $table->text('email')->nullable();
            $table->text('whatsapp')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('guardians', function (Blueprint $table) {
            $table->dropColumn(['email', 'whatsapp']);
        });
    }
};
