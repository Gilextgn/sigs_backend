<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Frais mensuels (cantine, TD…).
 *
 * - fee_types.billing_cycle : « once » (payé une fois, comme avant) ou
 *   « monthly » : le montant est alors celui d'UN mois, dû pour chacun des
 *   mois listés dans fee_types.months (septembre → juin par défaut).
 * - fee_subscriptions : un frais facultatif (is_mandatory = false) devient dû
 *   pour les élèves qui y sont inscrits (ex. seuls certains mangent à la
 *   cantine). Un frais obligatoire reste dû par toute la classe.
 * - payment_items.period_month : mois réglé par une ligne de frais mensuel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_types', function (Blueprint $table) {
            $table->string('billing_cycle', 10)->default('once')->after('amount');
            $table->json('months')->nullable()->after('billing_cycle');
        });

        Schema::table('payment_items', function (Blueprint $table) {
            $table->unsignedTinyInteger('period_month')->nullable()->after('fee_type_id');
        });

        Schema::create('fee_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id')->index();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('fee_type_id')->constrained('fee_types')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['student_id', 'fee_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_subscriptions');
        Schema::table('payment_items', function (Blueprint $table) {
            $table->dropColumn('period_month');
        });
        Schema::table('fee_types', function (Blueprint $table) {
            $table->dropColumn(['billing_cycle', 'months']);
        });
    }
};
