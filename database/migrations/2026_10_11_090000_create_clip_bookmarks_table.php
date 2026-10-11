<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La sélection de clips d'un clippeur.
 *
 * Par clippeur, et c'est tout le point. L'outil local d'origine gardait un
 * seul fichier d'historique pour la machine : mis en ligne tel quel, chacun
 * aurait vu la sélection des autres. Dans un système où le budget part au
 * premier arrivé, lire la liste de repérage d'un concurrent est un avantage
 * qu'aucun règlement ne rattrape.
 *
 * Rien n'est repris de Twitch au-delà de ce qui sert à réafficher la fiche
 * sans rappeler l'API : titre, vues, durée, vignette. Les vues sont figées à
 * la mise de côté — ce n'est pas un compteur, c'est une note.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clip_bookmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // La plateforme d'origine du clip, pas celle de publication :
            // Kick suivra, et la colonne doit déjà savoir les distinguer.
            $table->string('source', 16)->default('twitch');
            $table->string('external_id', 128);

            $table->string('url', 512);
            $table->string('title');
            $table->string('broadcaster', 128);
            $table->unsignedBigInteger('views')->default(0);
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->string('thumbnail_url', 512)->nullable();

            /*
             * Campagne visée, si le diffuseur en a une chez nous. Mise à null
             * plutôt que supprimée si la campagne disparaît : la sélection du
             * clippeur lui appartient, elle ne doit pas s'évaporer parce qu'un
             * créateur a archivé sa campagne.
             */
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();

            // Posé quand le clippeur déclare l'avoir monté et publié : c'est
            // ce qui empêche de remonter deux fois le même moment.
            $table->timestamp('taken_at')->nullable();

            $table->timestamps();

            // Un même clip ne se met de côté qu'une fois par personne, et deux
            // clippeurs peuvent viser le même : l'unicité porte sur le couple.
            $table->unique(['user_id', 'source', 'external_id']);
            $table->index(['user_id', 'taken_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clip_bookmarks');
    }
};
