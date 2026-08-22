<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Une seule table `users` pour les administrateurs et les clippeurs.
 *
 * Les capacités viennent de la colonne `role` : deux guards distincts
 * imposeraient des clés étrangères polymorphes sur clips, social_accounts
 * et payouts pour zéro gain fonctionnel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 32)->default(UserRole::Clipper->value)->after('password');

            // Modération (le volet budget est traité par CampaignBudgetService::reverseClip).
            $table->boolean('is_banned')->default(false)->after('role');
            $table->timestamp('banned_at')->nullable()->after('is_banned');
            $table->text('ban_reason')->nullable()->after('banned_at');

            // Destination des payouts PayPal, renseignée depuis l'espace clippeur.
            $table->string('paypal_email')->nullable()->after('ban_reason');

            $table->softDeletes();

            $table->index(['role', 'is_banned']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role', 'is_banned']);
            $table->dropSoftDeletes();
            $table->dropColumn(['role', 'is_banned', 'banned_at', 'ban_reason', 'paypal_email']);
        });
    }
};
