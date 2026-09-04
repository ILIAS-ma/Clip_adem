<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creators', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('bio')->nullable();
            $table->string('avatar_path')->nullable();

            $table->string('spotify_url')->nullable();
            $table->string('instagram_handle', 64)->nullable();
            $table->string('tiktok_handle', 64)->nullable();
            $table->string('youtube_handle', 64)->nullable();

            // Visible dans le back-office uniquement.
            $table->text('internal_notes')->nullable();

            // Un créateur inactif disparaît du sélecteur de création de campagne.
            $table->boolean('is_active')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            // Soft delete : l'historique des campagnes doit survivre à la suppression.
            $table->softDeletes();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creators');
    }
};
