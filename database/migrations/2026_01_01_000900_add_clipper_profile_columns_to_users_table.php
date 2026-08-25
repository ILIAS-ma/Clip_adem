<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Complétion de profil clippeur.
 *
 * `name` reste l'identité civile utilisée pour les versements ; `pseudo` est le
 * nom public affiché sur la plateforme. Les confondre obligerait un clippeur à
 * exposer son vrai nom pour être payé.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('pseudo', 48)->nullable()->unique()->after('name');

            // ISO 3166-1 alpha-2. PayPal restreint les versements selon le pays
            // du bénéficiaire, et la fiscalité en dépend.
            $table->char('country', 2)->nullable()->after('pseudo');

            $table->timestamp('profile_completed_at')->nullable()->after('paypal_email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['pseudo']);
            $table->dropColumn(['pseudo', 'country', 'profile_completed_at']);
        });
    }
};
