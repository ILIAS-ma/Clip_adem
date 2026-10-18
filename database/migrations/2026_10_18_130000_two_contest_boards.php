<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deux classements par campagne, côte à côte : les vues et le nombre de clips
 * validés, chacun avec ses propres récompenses.
 *
 * `contest_prizes` reste celles du classement des vues ; `contest_post_prizes`
 * porte celles du classement des clips. Le choix d'un critère unique
 * (`contest_metric`) n'a plus d'objet : les deux tableaux courent toujours.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->json('contest_post_prizes')->nullable()->after('contest_prizes');
            $table->dropColumn('contest_metric');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->string('contest_metric', 20)->default('views')->after('mode');
            $table->dropColumn('contest_post_prizes');
        });
    }
};
