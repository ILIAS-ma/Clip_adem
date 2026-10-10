<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nature de la fiche, et les champs qu'un streamer est seul à avoir.
 *
 * Les fiches existantes sont toutes des artistes : le défaut vaut pour elles,
 * et il faut qu'il soit exact — pas « indéfini » — sinon leur espace
 * s'afficherait sans libellé le temps qu'un administrateur passe les reprendre
 * une par une.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creators', function (Blueprint $table) {
            $table->string('kind', 16)->default('artist')->after('slug');

            $table->string('twitch_handle', 64)->nullable()->after('youtube_handle');
            $table->string('kick_handle', 64)->nullable()->after('twitch_handle');
            $table->string('vod_url')->nullable()->after('kick_handle');

            // Le back-office filtre par nature : les deux populations ne se
            // pilotent pas ensemble.
            $table->index('kind');
        });
    }

    public function down(): void
    {
        Schema::table('creators', function (Blueprint $table) {
            $table->dropIndex(['kind']);
            $table->dropColumn(['kind', 'twitch_handle', 'kick_handle', 'vod_url']);
        });
    }
};
