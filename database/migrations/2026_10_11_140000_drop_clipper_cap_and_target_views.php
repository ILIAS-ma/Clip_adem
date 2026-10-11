<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retire le plafond par clippeur et l'objectif de vues.
 *
 * Le plafond par clippeur contredisait la règle du produit : le budget part au
 * premier arrivé. Le brider par personne revenait à promettre un budget qu'on
 * empêchait ensuite de capter — et c'est le bon clippeur, celui qui fait les
 * vues, qu'on arrêtait le premier. Le plafond par clip reste : lui borne un
 * accident, pas une réussite.
 *
 * L'objectif de vues n'était qu'un nombre affiché. Il ne gouvernait rien,
 * aucune décision ne s'y adossait, et un créateur le lisait comme un
 * engagement de la plateforme.
 *
 * Aucune campagne ne portait de plafond, une seule portait un objectif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn(['max_payout_per_clipper_cents', 'target_views']);
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->unsignedBigInteger('target_views')->nullable()->after('budget_total_cents');
            $table->unsignedBigInteger('max_payout_per_clipper_cents')->nullable()->after('max_payout_per_clip_cents');
        });
    }
};
