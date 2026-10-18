<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campagnes « classement ».
 *
 * Aucune vue n'est payée au fil de l'eau : les clippeurs sont classés sur les
 * vues de leurs clips validés, et des prix fixés à l'avance récompensent les
 * premières places à la fin. Le budget de la campagne est la somme des prix.
 *
 * Les plateformes ouvertes restent décrites par `campaign_rates`, avec un taux
 * à zéro : le moteur de budget ne crédite donc rien aux vues, sans qu'aucune
 * de ses règles ait à connaître ce mode.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->string('mode', 20)->default('cpm')->after('status');
            // Montants par place, en centimes : [10000, 5000, 2500] = 100 € au
            // premier, 50 € au deuxième, 25 € au troisième.
            $table->json('contest_prizes')->nullable()->after('mode');
            $table->timestamp('prizes_paid_at')->nullable()->after('contest_prizes');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn(['mode', 'contest_prizes', 'prizes_paid_at']);
        });
    }
};
