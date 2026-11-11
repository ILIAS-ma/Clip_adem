<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un créateur qui suit ses statistiques sans voir l'argent mis pour les clippeurs.
 *
 * Certains créateurs n'ont pas à connaître le budget engagé ni les prix : on leur montre les vues,
 * les clips, les clippeurs et le classement, pas ce que la campagne rémunère. C'est un réglage par
 * fiche (faux par défaut) : les autres créateurs voient exactement ce qu'ils voyaient.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creators', function (Blueprint $table) {
            $table->boolean('hide_budget')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('creators', function (Blueprint $table) {
            $table->dropColumn('hide_budget');
        });
    }
};
