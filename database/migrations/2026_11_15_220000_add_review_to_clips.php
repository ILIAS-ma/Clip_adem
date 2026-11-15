<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vérification finale d'une campagne par l'équipe.
 *
 * Tout se valide tout seul pendant la campagne ; à la fin, l'équipe ouvre chaque clippeur du classement,
 * regarde ses clips en galerie et confirme qu'ils parlent bien du bon streamer. Ces deux colonnes
 * gardent qui a vérifié le clip, et quand : elles ne changent rien au statut, ni aux gains.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clips', function (Blueprint $table) {
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('clips', function (Blueprint $table) {
            $table->dropColumn(['reviewed_at', 'reviewed_by']);
        });
    }
};
