<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un paiement fait par l'équipe à la fin d'une campagne garde la campagne qu'il règle.
 *
 * C'est ce qui permet d'afficher « payé » ou « reste à payer » campagne par campagne, et d'empêcher de payer
 * deux fois la même campagne au même clippeur. Les retraits demandés par les clippeurs n'en ont pas (null).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->foreignId('campaign_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->index(['campaign_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('campaign_id');
        });
    }
};
