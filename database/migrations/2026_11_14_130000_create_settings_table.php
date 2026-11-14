<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les réglages que l'équipe change depuis l'administration, sans toucher au serveur.
 *
 * Ce qui est ici prend le pas sur la configuration du site (le fichier et le .env) ; une ligne absente
 * veut dire « valeur par défaut ». Seules les clés prévues dans SiteSettings sont acceptées : ce n'est pas
 * un endroit où l'on range n'importe quoi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            // JSON : le type (nombre, oui/non, texte) survit à l'aller-retour.
            $table->text('value')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
