<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Podium figé à la clôture d'un classement : qui a gagné quoi, avec quelles
 * vues. Conservé tel quel — un clip invalidé plus tard reprend son argent par
 * le grand livre, mais l'annonce faite aux gagnants reste lisible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->json('contest_winners')->nullable()->after('prizes_paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn('contest_winners');
        });
    }
};
