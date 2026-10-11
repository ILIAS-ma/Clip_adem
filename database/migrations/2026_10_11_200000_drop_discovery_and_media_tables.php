<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Retire les tables de la découverte et des fichiers de travail.
 *
 * Elles servaient une réimplémentation du studio de clips en PHP, abandonnée :
 * l'outil tourne désormais tel quel, comme service à côté du site, avec son
 * propre stockage. Garder ces tables aurait laissé croire qu'elles portent
 * quelque chose.
 *
 * `dropIfExists` et non `drop` : les migrations qui les créaient sont
 * supprimées, donc une installation neuve n'a jamais ces tables et ce fichier
 * doit passer quand même.
 *
 * Les fichiers déjà déposés sur le disque sont effacés à part : une migration
 * ne touche pas au système de fichiers, et la perdre de vue laisserait des
 * gigaoctets que plus rien ne référence.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Les montages pointent sur les clips repérés : l'ordre compte, sinon
        // la contrainte étrangère refuse la suppression.
        Schema::dropIfExists('media_assets');
        Schema::dropIfExists('clip_bookmarks');
    }

    public function down(): void
    {
        /*
         * Volontairement vide.
         *
         * Reconstituer deux tables dont plus aucun code ne se sert donnerait
         * des coquilles inertes, et laisserait croire que le retour en
         * arrière rétablit la fonctionnalité. Si cette réimplémentation
         * revient un jour, elle apportera ses propres migrations — et sans
         * doute un autre découpage.
         */
    }
};
