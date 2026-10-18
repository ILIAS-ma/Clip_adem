<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fenêtre de bienvenue des clippeurs, montrée une seule fois par compte.
 *
 * Retenu en base plutôt que dans le navigateur : la fenêtre ne doit pas
 * revenir sur le téléphone de quelqu'un qui l'a déjà fermée sur son ordinateur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('welcome_seen_at')->nullable()->after('profile_completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('welcome_seen_at');
        });
    }
};
