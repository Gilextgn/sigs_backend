<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Remise de caisse : le caissier (la secrétaire) remet au directeur l'argent
 * encaissé depuis sa dernière remise. Le directeur saisit ce qu'il a reçu ;
 * l'écart éventuel est expliqué. Une remise couvre les paiements du caissier
 * jusqu'à to_payment_id : ils ne peuvent plus être annulés.
 *
 * Remplace la « clôture de caisse » faite par le caissier lui-même.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_handovers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id')->index();
            $table->foreignId('cashier_user_id')->constrained('users');
            $table->foreignId('received_by_user_id')->constrained('users');
            $table->unsignedBigInteger('to_payment_id');
            $table->unsignedInteger('payment_count');
            $table->decimal('expected_amount', 12, 2);
            $table->decimal('received_amount', 12, 2);
            $table->decimal('difference', 12, 2);
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['cashier_user_id', 'to_payment_id']);
        });

        if (! DB::table('permissions')->where('code', 'cash.receive')->exists()) {
            DB::table('permissions')->insert(['code' => 'cash.receive', 'label' => 'Recevoir la remise de caisse des caissiers', 'created_at' => now()]);
        }
        $adminRoleId = DB::table('roles')->where('code', 'admin')->value('id');
        $permissionId = DB::table('permissions')->where('code', 'cash.receive')->value('id');
        if ($adminRoleId && $permissionId) {
            DB::table('role_permissions')->insertOrIgnore(['role_id' => $adminRoleId, 'permission_id' => $permissionId]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_handovers');
        DB::table('permissions')->where('code', 'cash.receive')->delete();
    }
};
