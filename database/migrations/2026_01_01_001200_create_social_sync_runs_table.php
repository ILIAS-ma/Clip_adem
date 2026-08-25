<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal des passages de synchronisation.
 *
 * Sans lui, un dépassement de quota se diagnostique à l'aveugle. Avec, la
 * question « pourquoi les vues n'ont pas bougé hier » se répond en une requête.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 32);

            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();

            $table->unsignedInteger('clips_synced')->default(0);
            $table->unsignedInteger('api_calls')->default(0);

            // Unités de quota consommées : la notion n'a de sens que chez
            // YouTube, mais la colonne sert de dénominateur commun.
            $table->unsignedInteger('quota_used')->default(0);

            $table->boolean('rate_limited')->default(false);
            $table->text('error')->nullable();

            $table->timestamps();

            $table->index(['platform', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_sync_runs');
    }
};
