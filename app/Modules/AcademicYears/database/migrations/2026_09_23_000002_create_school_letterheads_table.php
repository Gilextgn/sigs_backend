<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * En-tête des documents stocké en base, et non sur le disque : l'hébergement
 * recrée le conteneur à chaque redéploiement, une image déposée dans
 * public/uploads disparaissait donc sans prévenir, et les reçus repartaient
 * sans en-tête. Les anciennes images sur disque restent servies tant
 * qu'elles existent (voir SchoolSettingController).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_letterheads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id')->unique();
            $table->string('mime_type', 40);
            // Base64 : quelques Mo au plus, une seule ligne par école.
            $table->longText('data');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_letterheads');
    }
};
