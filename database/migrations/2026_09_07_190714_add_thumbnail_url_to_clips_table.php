<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Miniature de la publication, relevée comme la légende ou la durée : figée
 * au premier contrôle de conformité, pas rafraîchie ensuite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clips', function (Blueprint $table) {
            $table->string('thumbnail_url', 2048)->nullable()->after('caption');
        });
    }

    public function down(): void
    {
        Schema::table('clips', function (Blueprint $table) {
            $table->dropColumn('thumbnail_url');
        });
    }
};
