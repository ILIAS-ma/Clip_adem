<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Grand livre du budget, en append-only.
 *
 * C'est la source de vérité comptable : campaigns.spent_cents n'est qu'un cache
 * recalculable depuis cette table. Une ligne n'est jamais modifiée ni supprimée
 * — une erreur se corrige par une ligne inverse.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_budget_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();

            // Nuls pour un ajustement manuel d'administrateur.
            $table->foreignId('clip_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('type', 32);

            // Signé : > 0 consomme le budget, < 0 le restitue.
            $table->bigInteger('amount_cents');

            // Vues effectivement rémunérées par cette ligne.
            $table->bigInteger('views_delta')->default(0);

            // Taux figé au moment de l'opération : un changement de CPM ne doit
            // pas réécrire l'histoire.
            $table->unsignedInteger('rate_per_1k_cents')->nullable();

            // Budget restant juste après l'opération. Évite un SUM() pour afficher
            // l'historique de consommation.
            $table->bigInteger('balance_after_cents');

            /**
             * Garde-fou d'idempotence, ex. « clip:412:snapshot:9981 ».
             * L'index unique est le vrai rempart : même en course parfaite, la
             * seconde insertion lève une violation d'intégrité qui annule la
             * transaction concurrente.
             */
            $table->string('idempotency_key')->unique();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('meta')->nullable();

            // Pas d'updated_at : une ligne de grand livre ne se modifie pas.
            $table->timestamp('created_at')->useCurrent();

            $table->index(['campaign_id', 'created_at']);
            $table->index(['clip_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_budget_transactions');
    }
};
