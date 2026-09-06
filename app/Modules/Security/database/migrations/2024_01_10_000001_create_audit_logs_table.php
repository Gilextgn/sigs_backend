<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id')->default(1)->index();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action_code', 120);
            $table->string('entity_name', 120);
            $table->string('entity_id', 120)->nullable();
            $table->string('entity_label', 180)->nullable();
            $table->json('details_json')->nullable();
            $table->json('changes_json')->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['entity_name', 'entity_id']);
            $table->index('action_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
