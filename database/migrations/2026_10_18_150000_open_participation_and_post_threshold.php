<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Deux règles du produit.
 *
 * 1. Plus de demande de participation : un clippeur rejoint et poste. Les
 *    demandes restées en attente sont acceptées, et plus aucune campagne ne
 *    les exige. La colonne `requires_approval` reste, inutilisée, pour ne pas
 *    casser un retour arrière.
 *
 * 2. Au classement des clips, un clip ne compte que s'il atteint un minimum de
 *    vues, fixé par l'administrateur (1 000 par défaut) : dix clips à quarante
 *    vues ne doivent pas battre un clippeur qui en fait trois qui marchent.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('campaigns')->update(['requires_approval' => false]);

        DB::table('campaign_participations')
            ->where('status', 'pending')
            ->update(['status' => 'approved', 'approved_at' => now()]);

        Schema::table('campaigns', function (Blueprint $table) {
            $table->unsignedInteger('contest_post_min_views')->default(1000)->after('contest_post_prizes');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn('contest_post_min_views');
        });
    }
};
