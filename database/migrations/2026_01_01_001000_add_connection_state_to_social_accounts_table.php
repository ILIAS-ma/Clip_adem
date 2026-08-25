<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * État de la connexion OAuth.
 *
 * Un jeton qui expire est la panne la plus silencieuse du système : le clippeur
 * croit que ses vues montent alors que la synchronisation est morte depuis une
 * semaine. Ces colonnes rendent la panne visible et évitent de brûler du quota
 * d'API sur des comptes qui ne répondront plus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_accounts', function (Blueprint $table) {
            // Permissions réellement accordées : un clippeur peut décocher une
            // case au moment du consentement, et l'API échouera plus tard.
            $table->json('scopes')->nullable()->after('token_expires_at');

            $table->timestamp('last_refreshed_at')->nullable()->after('scopes');

            // Pilote le bandeau de reconnexion et fait sauter le compte dans
            // le job de synchronisation.
            $table->boolean('needs_reconnect')->default(false)->after('last_refreshed_at');
            $table->text('last_error')->nullable()->after('needs_reconnect');

            $table->index(['is_active', 'needs_reconnect']);
        });
    }

    public function down(): void
    {
        Schema::table('social_accounts', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'needs_reconnect']);
            $table->dropColumn(['scopes', 'last_refreshed_at', 'needs_reconnect', 'last_error']);
        });
    }
};
