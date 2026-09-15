<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * closed_at est orthogonal à is_active : is_active désigne l'année cible
 * des nouvelles inscriptions/paiements, closed_at désigne une année dont
 * la clôture a été prononcée (débiteurs listés, réinscription bloquée
 * sauf passage outre). Reste NULL-able pour permettre la réouverture.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_years', function (Blueprint $table) {
            $table->timestamp('closed_at')->nullable()->after('is_active');
            $table->foreignId('closed_by_user_id')->nullable()->after('closed_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('academic_years', function (Blueprint $table) {
            $table->dropConstrainedForeignId('closed_by_user_id');
            $table->dropColumn('closed_at');
        });
    }
};
