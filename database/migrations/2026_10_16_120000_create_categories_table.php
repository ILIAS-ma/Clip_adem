<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catégories de campagnes, créées par l'administration.
 *
 * Elles regroupent les campagnes par univers — gaming, musique, podcast… — et
 * servent de base aux classements par catégorie : un clippeur spécialisé dans
 * une niche doit pouvoir s'y voir en tête, même loin du classement général.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            // Ordre d'affichage des onglets du classement, choisi par l'admin.
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::table('campaigns', function (Blueprint $table) {
            // Nullable : les campagnes existantes n'ont pas de catégorie, et
            // supprimer une catégorie ne doit jamais emporter ses campagnes.
            $table->foreignId('category_id')
                ->nullable()
                ->after('creator_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });

        Schema::dropIfExists('categories');
    }
};
