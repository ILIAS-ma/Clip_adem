<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Publication disparue de la plateforme.
 *
 * Sans ces colonnes, supprimer sa vidéo après avoir été payé ne laissait
 * aucune trace : le relevé notait simplement « rien à lire » et passait au
 * suivant. C'est le vecteur de fraude le moins coûteux contre la plateforme —
 * publier, encaisser sur trois jours, effacer.
 *
 * Deux colonnes plutôt qu'un booléen : la date dit depuis quand, le compteur
 * dit combien de relevés consécutifs l'ont manquée. Un seul échec ne prouve
 * rien — une publication passée en privé une heure, un hoquet d'API — et
 * accuser quelqu'un sur un hoquet coûte plus cher que d'attendre un relevé.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clips', function (Blueprint $table) {
            $table->timestamp('missing_since')->nullable()->after('last_synced_at');
            $table->unsignedInteger('missing_checks')->default(0)->after('missing_since');

            // Index partiel impossible en MySQL : celui-ci suffit, la
            // modération filtre sur « disparu » qui reste une minorité.
            $table->index('missing_since');
        });
    }

    public function down(): void
    {
        Schema::table('clips', function (Blueprint $table) {
            $table->dropIndex(['missing_since']);
            $table->dropColumn(['missing_since', 'missing_checks']);
        });
    }
};
