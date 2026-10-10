<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Format international, stocké normalisé : « 0612… » et
            // « +33612… » désignent le même abonné, et sans normalisation
            // deux comptes peuvent réserver le même numéro.
            $table->string('phone', 20)->nullable()->unique()->after('email');
            $table->timestamp('phone_verified_at')->nullable()->after('phone');

            // Le code n'est pas stocké en clair : une fuite de la base
            // donnerait sinon un accès immédiat à tout compte en cours de
            // vérification.
            $table->string('phone_code_hash')->nullable();
            $table->timestamp('phone_code_sent_at')->nullable();
            $table->unsignedTinyInteger('phone_code_attempts')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->dropColumn([
                'phone', 'phone_verified_at', 'phone_code_hash',
                'phone_code_sent_at', 'phone_code_attempts',
            ]);
        });
    }
};
