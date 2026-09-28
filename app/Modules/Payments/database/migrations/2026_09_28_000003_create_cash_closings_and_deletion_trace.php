<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Point & clôture de caisse (remplace le cahier des secrétaires).
 *
 * - cash_closings : chaque caissier arrête sa caisse du jour. Le montant
 *   attendu est calculé par le serveur, le caissier déclare ce qu'il a
 *   compté : l'écart est figé. Une fois clôturée, la journée ne bouge plus
 *   (ni encaissement ni suppression) sauf réouverture motivée par l'admin ;
 *   la clôture rouverte reste en historique.
 * - payments.deleted_by_user_id / deletion_reason : une annulation dit qui
 *   et pourquoi, et apparaît dans le point.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        'cash.close' => 'Clôturer sa caisse du jour',
        'cash.report' => 'Voir le point de caisse de tous les caissiers',
        'cash.reopen' => 'Rouvrir une caisse clôturée',
    ];

    // Rôles existants qui reçoivent la nouvelle permission (en plus de l'admin).
    private const ROLE_GRANTS = [
        'cashier' => ['cash.close'],
        'secretary' => ['cash.close'],
        'accountant' => ['cash.report'],
    ];

    public function up(): void
    {
        Schema::create('cash_closings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id')->index();
            $table->foreignId('cashier_user_id')->constrained('users');
            $table->date('closing_date');
            $table->unsignedInteger('payment_count');
            $table->decimal('expected_amount', 12, 2);
            $table->decimal('counted_amount', 12, 2);
            $table->decimal('difference', 12, 2);
            $table->text('note')->nullable();
            $table->timestamp('closed_at')->useCurrent();
            $table->timestamp('reopened_at')->nullable();
            $table->foreignId('reopened_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reopen_reason', 255)->nullable();

            $table->index(['school_id', 'closing_date']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('deleted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('deletion_reason', 255)->nullable();
        });

        foreach (self::PERMISSIONS as $code => $label) {
            if (! DB::table('permissions')->where('code', $code)->exists()) {
                DB::table('permissions')->insert(['code' => $code, 'label' => $label, 'created_at' => now()]);
            }
        }

        $grants = ['admin' => array_keys(self::PERMISSIONS), ...self::ROLE_GRANTS];
        foreach ($grants as $roleCode => $codes) {
            $roleId = DB::table('roles')->where('code', $roleCode)->value('id');
            if (! $roleId) {
                continue;
            }
            foreach (DB::table('permissions')->whereIn('code', $codes)->pluck('id') as $permissionId) {
                DB::table('role_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionId]);
            }
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')->whereIn('code', array_keys(self::PERMISSIONS))->pluck('id');
        DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();

        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deleted_by_user_id');
            $table->dropColumn('deletion_reason');
        });
        Schema::dropIfExists('cash_closings');
    }
};
