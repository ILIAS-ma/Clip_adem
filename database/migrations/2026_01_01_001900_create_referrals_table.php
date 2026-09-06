<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parrainage.
 *
 * Deux colonnes sur `users` — le code qu'on partage, et qui nous a parrainé —
 * plus un grand livre des commissions, sur le même modèle que le budget : en
 * ajout seul, jamais recalculé depuis un compteur.
 *
 * LA règle du dispositif : la commission de parrainage ne sort JAMAIS du
 * budget d'une campagne. Elle est payée par la plateforme sur sa marge. Sinon
 * le créateur financerait la croissance de la plateforme sans le savoir, et le
 * budget qu'il a provisionné ne servirait plus entièrement à ses vues.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Le code partagé. Unique, jamais réattribué même après
            // suppression : un lien partagé sur TikTok survit au compte.
            $table->string('referral_code', 16)->nullable()->unique()->after('pseudo');

            // Qui a parrainé. `nullOnDelete` plutôt que cascade : perdre le
            // parrain ne doit pas effacer le filleul.
            $table->foreignId('referred_by')->nullable()->after('referral_code')
                ->constrained('users')->nullOnDelete();

            $table->timestamp('referred_at')->nullable()->after('referred_by');
        });

        Schema::create('referral_commissions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('referrer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('referred_id')->constrained('users')->cascadeOnDelete();

            // La ligne du grand livre budget qui a déclenché la commission.
            // Elle permet de refaire le calcul, et de tout annuler si le clip
            // est invalidé.
            $table->foreignId('budget_transaction_id')->nullable()
                ->constrained('campaign_budget_transactions')->nullOnDelete();

            // Signé : négatif quand on reprend une commission versée sur des
            // vues finalement invalidées.
            $table->bigInteger('amount_cents');

            // Base et taux figés : changer le barème ne doit pas réécrire
            // l'histoire des commissions déjà acquises.
            $table->bigInteger('base_cents');
            $table->unsignedInteger('rate_bp'); // points de base : 500 = 5 %

            $table->timestamps();

            $table->index(['referrer_id', 'created_at']);
            $table->index('referred_id');

            // Une transaction budget ne donne qu'une commission : c'est le
            // garde-fou d'idempotence, comme pour le budget lui-même.
            $table->unique(['referrer_id', 'budget_transaction_id'], 'referral_once_per_transaction');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_commissions');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignKey('referred_by');
            $table->dropColumn(['referral_code', 'referred_at']);
        });
    }
};
