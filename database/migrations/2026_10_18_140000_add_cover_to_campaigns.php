<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Image de couverture d'une campagne, affichée dans le catalogue et en haut
 * de sa fiche. Distincte des pièces du brief : celle-ci donne envie de
 * cliquer, celles-là servent à tourner.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->string('cover_path')->nullable()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn('cover_path');
        });
    }
};
