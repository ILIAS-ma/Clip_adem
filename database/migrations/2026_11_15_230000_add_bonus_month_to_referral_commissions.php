<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prime fixe de parrainage : un montant par filleul actif, versé le 25 du mois.
 *
 * Elle s'écrit dans la même table que les commissions, donc elle arrive sur le solde du parrain comme elles.
 * Le mois qui l'a déclenchée est gardé sur la ligne, avec un index unique par filleul : une prime ne peut pas
 * être versée deux fois pour le même filleul et le même mois, même si on relance le versement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referral_commissions', function (Blueprint $table) {
            $table->string('bonus_month', 7)->nullable()->after('rate_bp');
            $table->unique(['referred_id', 'bonus_month'], 'referral_bonus_once');
        });
    }

    public function down(): void
    {
        Schema::table('referral_commissions', function (Blueprint $table) {
            $table->dropUnique('referral_bonus_once');
            $table->dropColumn('bonus_month');
        });
    }
};
