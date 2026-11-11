<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un compte dispensé de numéro de téléphone.
 *
 * Le numéro vérifié est ce qui empêche de multiplier les comptes. L'équipe peut pourtant avoir de
 * bonnes raisons d'en ouvrir un sans : second compte d'un administrateur, clippeur de confiance qui
 * n'a pas de ligne à fournir. La dispense est donnée par le super-admin, datée, et journalisée ; la
 * colonne dit depuis quand, jamais un simple booléen sans trace.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('phone_exempt_at')->nullable()->after('phone_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('phone_exempt_at');
        });
    }
};
