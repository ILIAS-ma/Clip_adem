<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L'argent qui ENTRE, en face de celui qui sort.
 *
 * Jusqu'ici `campaigns.budget_total_cents` était un nombre tapé au clavier par
 * un administrateur, sans le moindre lien avec un encaissement réel. La
 * plateforme pouvait donc devoir 5 000 € à des clippeurs sans avoir reçu un
 * centime : `budget:audit` vérifiait la cohérence interne, jamais la
 * solvabilité.
 *
 * Même forme que le grand livre des dépenses, et pour la même raison : une
 * table en ajout seul plutôt qu'un compteur. Un encaissement se corrige par
 * une ligne inverse — un remboursement au créateur en est une — et l'histoire
 * reste lisible six mois plus tard.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_fundings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();

            // Signé : positif pour un encaissement, négatif pour un
            // remboursement au créateur.
            $table->bigInteger('amount_cents');
            $table->char('currency', 3)->default('EUR');

            // 'transfer' | 'card' | 'paypal' | 'other' — comment l'argent est
            // arrivé, pour le rapprochement bancaire.
            $table->string('method', 32)->default('transfer');

            // Référence du virement ou numéro de facture : c'est ce qui permet
            // de retrouver la ligne sur un relevé.
            $table->string('reference')->nullable();
            $table->text('note')->nullable();

            $table->timestamp('received_at');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['campaign_id', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_fundings');
    }
};
