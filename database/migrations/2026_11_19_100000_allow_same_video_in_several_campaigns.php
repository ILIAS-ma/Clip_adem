<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Une même vidéo peut compter pour plusieurs campagnes.
 *
 * Deux streamers qui streament ensemble ont chacun leur campagne : le clip d'un moment commun
 * concerne les deux. L'unicité ne porte donc plus sur la vidéo seule mais sur la vidéo ET la campagne :
 * une vidéo ne peut toujours pas être soumise deux fois à la même campagne. C'est la vérification finale
 * de l'équipe qui écarte, campagne par campagne, les clips qui n'ont rien à y faire.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clips', function (Blueprint $table) {
            // L'ajout d'abord : l'ancien index sert aussi de support à la clé étrangère éventuelle.
            $table->unique(['platform', 'external_post_id', 'campaign_id'], 'clips_post_once_per_campaign');
            $table->index(['platform', 'external_post_id'], 'clips_platform_post_index');
            $table->dropUnique(['platform', 'external_post_id']);
        });
    }

    public function down(): void
    {
        Schema::table('clips', function (Blueprint $table) {
            $table->unique(['platform', 'external_post_id']);
            $table->dropIndex('clips_platform_post_index');
            $table->dropUnique('clips_post_once_per_campaign');
        });
    }
};
