<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conformité au brief, constatée à la soumission.
 *
 * Le résultat est figé sur le clip et non recalculé à l'affichage : la
 * modération doit voir ce qui était vrai au moment des faits, pas ce que la
 * légende est devenue après une modification.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clips', function (Blueprint $table) {
            $table->timestamp('submitted_at')->nullable()->after('posted_at');

            // Relevés à la soumission, pour vérifier hashtags et durée minimale.
            $table->text('caption')->nullable()->after('url');
            $table->unsignedInteger('duration_seconds')->nullable()->after('caption');

            // pending | passed | failed — un rapport, pas une décision :
            // la validation reste manuelle.
            $table->string('compliance_status', 32)->nullable()->after('rejection_reason');
            $table->json('compliance')->nullable()->after('compliance_status');

            $table->index('compliance_status');
        });
    }

    public function down(): void
    {
        Schema::table('clips', function (Blueprint $table) {
            $table->dropIndex(['compliance_status']);
            $table->dropColumn([
                'submitted_at', 'caption', 'duration_seconds', 'compliance_status', 'compliance',
            ]);
        });
    }
};
