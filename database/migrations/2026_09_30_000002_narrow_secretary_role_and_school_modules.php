<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - Rôle secrétaire : ne voit plus par défaut les onglets qui ne sont pas de
 *   son ressort (enseignants, emploi du temps, présences, paie, tranches,
 *   autres frais). Le directeur peut les rendre à un compte précis depuis
 *   l'écran Utilisateurs.
 * - schools.hidden_modules : onglets du menu masqués pour toute une école,
 *   réglés par le propriétaire de la plateforme (console) sans toucher au code.
 */
return new class extends Migration
{
    private const REMOVED_FROM_SECRETARY = ['teachers.view', 'tranches.view', 'fees.view'];

    public function up(): void
    {
        $roleId = DB::table('roles')->where('code', 'secretary')->value('id');
        if ($roleId) {
            $ids = DB::table('permissions')->whereIn('code', self::REMOVED_FROM_SECRETARY)->pluck('id');
            DB::table('role_permissions')->where('role_id', $roleId)->whereIn('permission_id', $ids)->delete();
        }

        Schema::table('schools', function (Blueprint $table) {
            $table->json('hidden_modules')->nullable()->after('site_label');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn('hidden_modules');
        });
    }
};
