<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un taux de rémunération par plateforme et par campagne.
 *
 * Table plutôt que colonne JSON : le taux appliqué doit rester lisible et
 * agrégeable en SQL pour le reporting par plateforme.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 32);

            // Rémunération pour 1000 vues, en centimes (CPM).
            // Un taux « par vue » imposerait des fractions de centime.
            $table->unsignedInteger('rate_per_1k_cents');

            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['campaign_id', 'platform']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_rates');
    }
};
