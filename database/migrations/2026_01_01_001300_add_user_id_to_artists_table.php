<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rattache une fiche artiste à un compte de connexion.
 *
 * Nullable et unique : un artiste créé par l'admin n'a pas forcément de compte,
 * et un compte ne pilote jamais deux fiches — sinon la question « de quel
 * artiste voit-il les statistiques ? » n'aurait pas de réponse unique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('artists', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->unique()->after('id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('artists', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropUnique(['artists_user_id_unique']);
            $table->dropColumn('user_id');
        });
    }
};
