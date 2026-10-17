<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saisons du classement.
 *
 * Le classement « depuis toujours » finit par se figer : les premiers inscrits
 * y restent en tête, et un nouveau venu n'a aucune chance d'y entrer. Une
 * saison remet tout le monde à zéro sur une période choisie par l'équipe,
 * sans rien effacer — elle se lit dans le grand livre, entre deux dates.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seasons', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('starts_on');
            $table->date('ends_on');
            // Récompense annoncée aux clippeurs, en texte libre : « 200 € au
            // premier, 100 € au deuxième ». Rien n'est versé automatiquement.
            $table->string('prize')->nullable();
            $table->timestamps();

            $table->index(['starts_on', 'ends_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seasons');
    }
};
