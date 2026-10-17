<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Primes garanties.
 *
 * Une prime est un montant fixe crédité sur un clip validé, en plus de ses
 * vues : prime de lancement automatique pour les premiers clippeurs d'une
 * campagne, ou geste manuel de l'équipe. Elle est payée par le budget de la
 * campagne, passe par le moteur comme tout crédit et figure au grand livre.
 *
 * `clips.bonus_cents` isole la part de `earned_cents` qui vient des primes :
 * sans elle, une prime consommerait le plafond par clip et priverait le
 * clippeur des vues qu'il ferait ensuite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clips', function (Blueprint $table) {
            $table->unsignedInteger('bonus_cents')->default(0)->after('earned_cents');
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->unsignedInteger('launch_bonus_cents')->nullable()->after('max_payout_per_clip_cents');
            $table->unsignedSmallInteger('launch_bonus_slots')->nullable()->after('launch_bonus_cents');
        });
    }

    public function down(): void
    {
        Schema::table('clips', function (Blueprint $table) {
            $table->dropColumn('bonus_cents');
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn(['launch_bonus_cents', 'launch_bonus_slots']);
        });
    }
};
