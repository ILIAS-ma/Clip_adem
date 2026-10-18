<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Critère d'un classement : les vues (par défaut, comme avant) ou le nombre
 * de clips validés — de quoi récompenser la régularité plutôt qu'un coup de
 * chance viral.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->string('contest_metric', 20)->default('views')->after('mode');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn('contest_metric');
        });
    }
};
