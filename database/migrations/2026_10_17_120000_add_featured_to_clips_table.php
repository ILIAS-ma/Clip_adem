<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clips mis en avant sur la page d'accueil.
 *
 * Le choix reste humain : un clip validé n'est pas pour autant une vitrine,
 * et rien de ce qui s'affiche devant un visiteur ne doit y arriver tout seul.
 *
 * La miniature est copiée chez nous au moment de la mise en avant : celles
 * des plateformes sont des liens signés qui expirent, et la vitrine se
 * remplirait de cases vides au bout de quelques jours.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clips', function (Blueprint $table) {
            $table->timestamp('featured_at')->nullable()->after('thumbnail_url')->index();
            $table->string('featured_thumbnail_path')->nullable()->after('featured_at');
        });
    }

    public function down(): void
    {
        Schema::table('clips', function (Blueprint $table) {
            $table->dropIndex(['featured_at']);
            $table->dropColumn(['featured_at', 'featured_thumbnail_path']);
        });
    }
};
