<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La messagerie entre l'équipe et chaque utilisateur.
 *
 * Elle remplace l'e-mail pour tout ce qui peut attendre une connexion :
 * une question sur un versement, un clip refusé, un compte à reconnecter.
 *
 * Une seule conversation par utilisateur, avec l'équipe entière plutôt
 * qu'avec un modérateur précis : le clippeur ne doit pas avoir à savoir qui
 * est de garde, et un modérateur absent ne doit pas laisser un message sans
 * réponse.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // Tri de la boîte de réception sans parcourir les messages.
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();

            // Nullable : un modérateur supprimé ne doit pas emporter
            // l'historique de ce qu'il a répondu.
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();

            // Écrit par l'équipe ou par l'utilisateur. Figé à l'envoi : un
            // modérateur rétrogradé plus tard ne doit pas faire basculer ses
            // anciens messages de l'autre côté du fil.
            $table->boolean('from_staff');

            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'from_staff', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
    }
};
