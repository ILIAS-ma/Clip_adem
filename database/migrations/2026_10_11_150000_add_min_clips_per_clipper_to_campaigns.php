<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nombre minimum de clips validés avant d'être rémunéré.
 *
 * Une campagne peut exiger un volume : cinq clips plutôt qu'un seul coup de
 * chance. En dessous, les vues sont comptées mais rien n'est crédité.
 *
 * Rien n'est perdu pour autant, et c'est ce qui rend la règle tenable : le
 * crédit se calcule sur le total de vues moins les vues déjà payées, pas sur
 * le relevé du moment. Le jour où le cinquième clip est validé, le relevé
 * suivant rattrape tout l'arriéré des quatre premiers d'un coup.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            // Nullable et non « zéro » : l'absence d'exigence doit se
            // distinguer d'une exigence de zéro clip, qui ne veut rien dire.
            $table->unsignedSmallInteger('min_clips_per_clipper')
                ->nullable()
                ->after('min_views_per_clip');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn('min_clips_per_clipper');
        });
    }
};
