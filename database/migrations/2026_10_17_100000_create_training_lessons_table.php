<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Espace formation : des leçons courtes, une vidéo et un texte chacune, et la
 * trace de ce que chaque clippeur a terminé.
 *
 * La progression est une table à part plutôt qu'un compteur sur `users` :
 * ajouter une leçon ne doit pas faire croire à quelqu'un qui avait tout
 * terminé qu'il l'a déjà vue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_lessons', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('summary', 500)->nullable();
            // Markdown, rédigé dans le back-office.
            $table->text('body')->nullable();
            // Fichier déposé sur le disque public, ou à défaut une vidéo
            // hébergée ailleurs (YouTube non répertoriée, par exemple).
            $table->string('video_path')->nullable();
            $table->string('video_url')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        Schema::create('training_lesson_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_lesson_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('completed_at');

            $table->unique(['training_lesson_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_lesson_user');
        Schema::dropIfExists('training_lessons');
    }
};
