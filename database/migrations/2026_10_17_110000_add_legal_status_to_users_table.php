<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Statut légal du clippeur.
 *
 * Sans statut déclaré, les retraits sont plafonnés sur l'année civile (voir
 * `clipping.legal`). Un auto-entrepreneur ou une société déclare son SIRET ;
 * le plafond ne se lève qu'une fois ce SIRET vérifié par un membre de
 * l'équipe, au registre — un numéro tapé au clavier ne prouve rien.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('legal_status', 20)->nullable()->after('iban_last4');
            $table->string('siret', 14)->nullable()->after('legal_status');
            $table->timestamp('siret_submitted_at')->nullable()->after('siret');
            $table->timestamp('siret_verified_at')->nullable()->after('siret_submitted_at');
            $table->foreignId('siret_verified_by')->nullable()->after('siret_verified_at')
                ->constrained('users')->nullOnDelete();
            $table->string('siret_rejection_reason')->nullable()->after('siret_verified_by');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('siret_verified_by');
            $table->dropColumn(['legal_status', 'siret', 'siret_submitted_at', 'siret_verified_at', 'siret_rejection_reason']);
        });
    }
};
