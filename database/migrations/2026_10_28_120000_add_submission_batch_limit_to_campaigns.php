<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nombre maximum de vidéos qu'un clippeur peut envoyer d'un coup sur une campagne.
 *
 * Vide = pas de limite. Sert à empêcher qu'une centaine de vidéos soient déversées
 * d'un seul envoi, notamment dans les dernières heures d'un classement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_clips_per_submission')->nullable()->after('contest_post_min_views');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn('max_clips_per_submission');
        });
    }
};
