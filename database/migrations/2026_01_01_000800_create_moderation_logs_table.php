<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal des décisions de modération, en append-only.
 *
 * Invalider un clip revient à reprendre de l'argent à quelqu'un : sans trace
 * horodatée et motivée, un litige devient la parole de l'admin contre celle du
 * clippeur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moderation_logs', function (Blueprint $table) {
            $table->id();

            // L'auteur de la décision. Nullable pour les actions automatiques.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('action', 48);

            // Cible : un clip, un clippeur, un payout.
            $table->morphs('subject');

            $table->text('reason')->nullable();
            $table->json('meta')->nullable();

            // Pas d'updated_at : une décision consignée ne se réécrit pas.
            $table->timestamp('created_at')->useCurrent();

            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_logs');
    }
};
