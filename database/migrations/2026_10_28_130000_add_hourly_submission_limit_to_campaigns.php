<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nombre maximum de vidéos qu'un clippeur peut soumettre sur une campagne par heure glissante.
 *
 * Vide = pas de limite. Complète la limite par envoi : sans cadence, rien n'empêche
 * d'enchaîner plusieurs envois à la suite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_clips_per_hour')->nullable()->after('max_clips_per_submission');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn('max_clips_per_hour');
        });
    }
};
