<?php

namespace App\Http\Controllers\Clipper;

use App\Http\Controllers\Controller;
use App\Models\TrainingLesson;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Espace formation : apprendre à se servir du site avant d'y passer des
 * heures, plutôt que de le découvrir à coups de clips refusés.
 */
class TrainingController extends Controller
{
    public function index(Request $request): View
    {
        $lessons = TrainingLesson::query()->published()->ordered()->get();
        $completed = $this->completedIds($request);

        return view('clipper.training.index', [
            'lessons' => $lessons,
            'completed' => $completed,
            // La première leçon pas encore terminée : c'est elle qu'on
            // propose de reprendre, pas la première de la liste.
            'next' => $lessons->first(fn (TrainingLesson $lesson) => ! in_array($lesson->id, $completed, true)),
        ]);
    }

    public function show(Request $request, TrainingLesson $lesson): View
    {
        abort_unless($lesson->is_published, 404);

        $lessons = TrainingLesson::query()->published()->ordered()->get(['id', 'slug', 'title', 'position']);
        $index = $lessons->search(fn (TrainingLesson $item) => $item->is($lesson));

        return view('clipper.training.show', [
            'lesson' => $lesson,
            'lessons' => $lessons,
            'completed' => $this->completedIds($request),
            'previous' => $index > 0 ? $lessons[$index - 1] : null,
            'following' => $lessons[$index + 1] ?? null,
            'number' => $index + 1,
        ]);
    }

    public function complete(Request $request, TrainingLesson $lesson): RedirectResponse
    {
        abort_unless($lesson->is_published, 404);

        // syncWithoutDetaching : terminer deux fois la même leçon ne doit ni
        // échouer sur la contrainte d'unicité ni repousser la date d'origine.
        $request->user()->completedLessons()->syncWithoutDetaching([
            $lesson->getKey() => ['completed_at' => now()],
        ]);

        $following = TrainingLesson::query()->published()->ordered()->get()
            ->first(fn (TrainingLesson $item) => $item->position > $lesson->position
                || ($item->position === $lesson->position && $item->id > $lesson->id));

        return $following
            ? redirect()->route('training.show', $following)->with('status', 'Leçon terminée. Place à la suivante.')
            : redirect()->route('training.index')->with('status', 'Formation terminée. Vous savez tout ce qu’il faut pour vous lancer.');
    }

    /** @return list<int> */
    protected function completedIds(Request $request): array
    {
        return $request->user()->completedLessons()->pluck('training_lessons.id')->map(fn ($id) => (int) $id)->all();
    }
}
