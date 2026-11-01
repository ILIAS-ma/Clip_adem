<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un clippeur qui réclame ses prix à la fin d'un classement.
 *
 * Réclamer ne verse rien : les prix sont attribués par l'équipe. La demande dit seulement à
 * l'équipe « j'attends mes prix », et le tableau de bord admin la compte.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prize_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->timestamp('claimed_at');
            $table->timestamps();

            // Une demande par clippeur et par campagne : réclamer deux fois ne change rien.
            $table->unique(['user_id', 'campaign_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prize_claims');
    }
};
