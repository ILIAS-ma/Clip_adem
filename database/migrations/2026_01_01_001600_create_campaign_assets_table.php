<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pièces jointes d'une campagne : sons, vidéos, images, documents.
 *
 * Le brief textuel ne suffit pas — un clippeur a besoin d'entendre le son
 * imposé et de voir des exemples avant de tourner. Une table plutôt que des
 * colonnes : le nombre de pièces varie, et chacune porte son propre type.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();

            $table->string('kind', 16);          // audio | video | image | document
            $table->string('label');
            $table->text('description')->nullable();

            // Fichier déposé, ou lien externe pour ce qui est déjà hébergé
            // ailleurs (Drive, WeTransfer, un son TikTok). L'un ou l'autre.
            $table->string('path')->nullable();
            $table->string('external_url', 2048)->nullable();

            $table->string('mime_type', 128)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();

            // Une pièce peut être signalée comme obligatoire : le son imposé
            // n'est pas au même niveau qu'un exemple d'inspiration.
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('position')->default(0);

            $table->timestamps();

            $table->index(['campaign_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_assets');
    }
};
