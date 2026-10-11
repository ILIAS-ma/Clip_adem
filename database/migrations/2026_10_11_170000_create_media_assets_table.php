<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les fichiers d'un clippeur : sources téléchargées et montages produits.
 *
 * Une ligne par fichier, et toujours rattachée à une personne. L'outil
 * d'origine se contentait du système de fichiers ; en ligne, il faut savoir à
 * qui appartient chaque octet — pour l'autorisation, pour le quota, et pour
 * purger ce qui n'a plus de raison d'exister.
 *
 * Le statut est porté par la ligne plutôt que déduit de la présence du
 * fichier : un montage en cours de rendu n'existe pas encore sur le disque,
 * et un rendu échoué doit pouvoir dire pourquoi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // source = le mp4 récupéré chez le diffuseur.
            // montage = le vertical produit par ffmpeg à partir d'une source.
            $table->string('kind', 16);

            /*
             * La source dont dérive un montage. Mise à null plutôt que
             * supprimée en cascade : effacer la source ne doit pas emporter
             * le montage, qui est le travail du clippeur.
             */
            $table->foreignId('source_id')->nullable()
                ->constrained('media_assets')->nullOnDelete();

            // Le clip repéré d'où vient la source, s'il vient de là.
            $table->foreignId('clip_bookmark_id')->nullable()
                ->constrained('clip_bookmarks')->nullOnDelete();

            $table->string('label');
            $table->string('path', 512)->nullable();
            $table->unsignedBigInteger('bytes')->default(0);
            $table->unsignedInteger('duration_seconds')->nullable();

            // en_attente | en_cours | pret | echec
            $table->string('status', 16)->default('en_attente');

            // Dit au clippeur ce qui a échoué, en français et sans trace.
            $table->string('error')->nullable();

            /*
             * Les réglages du rendu, tels qu'ils ont été demandés. Conservés
             * pour pouvoir rejouer un montage sans tout ressaisir — et pour
             * comprendre, six mois plus tard, pourquoi celui-ci est en 1:1.
             */
            $table->json('options')->nullable();

            /*
             * Date de purge. Le disque est partagé avec MySQL et les
             * journaux : un espace qui ne se vide jamais finit par arrêter le
             * site entier, et pas seulement l'outil de montage.
             */
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'kind', 'status']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_assets');
    }
};
