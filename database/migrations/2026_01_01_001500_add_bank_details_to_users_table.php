<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Virement bancaire en plus de PayPal.
 *
 * L'IBAN est chiffré au niveau du modèle : c'est une donnée bancaire, un dump
 * de base ne doit pas la livrer en clair. Les quatre derniers caractères sont
 * stockés à part, en clair, pour pouvoir afficher « ••••1234 » et rapprocher un
 * virement sans jamais déchiffrer quoi que ce soit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('payout_method', 16)->default('paypal')->after('paypal_email');
            $table->text('iban')->nullable()->after('payout_method');
            $table->string('iban_last4', 4)->nullable()->after('iban');
            $table->string('bic', 16)->nullable()->after('iban_last4');
            $table->string('account_holder')->nullable()->after('bic');
        });

        Schema::table('payouts', function (Blueprint $table) {
            // Un virement bancaire n'a pas d'adresse PayPal : la colonne
            // historique devient facultative plutôt que de recevoir du vide.
            $table->string('paypal_email')->nullable()->change();

            $table->string('method', 16)->default('paypal')->after('currency');

            // Destination masquée, figée au moment de la demande : si le
            // clippeur change d'IBAN ensuite, l'historique doit continuer de
            // dire où l'argent est réellement parti.
            $table->string('destination')->nullable()->after('method');
        });

        DB::table('payouts')->whereNull('destination')->update([
            'destination' => DB::raw('paypal_email'),
        ]);
    }

    public function down(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->dropColumn(['method', 'destination']);
            $table->string('paypal_email')->nullable(false)->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['payout_method', 'iban', 'iban_last4', 'bic', 'account_holder']);
        });
    }
};
