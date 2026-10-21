<?php

namespace Tests\Feature\Clipper;

use App\Enums\UserRole;
use App\Models\TrainingLesson;
use App\Models\User;
use App\Services\Clippers\AchievementService;
use Database\Seeders\TrainingLessonSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TrainingTest extends TestCase
{
    use RefreshDatabase;

    protected function clipper(): User
    {
        return User::factory()->create([
            'role' => UserRole::Clipper,
            'email_verified_at' => now(),
            'pseudo' => fake()->unique()->userName(),
            'country' => 'FR',
            'paypal_email' => fake()->unique()->safeEmail(),
            'profile_completed_at' => now(),
        ]);
    }

    #[Test]
    public function the_index_lists_published_lessons_in_order(): void
    {
        TrainingLesson::factory()->create(['title' => 'Deuxième', 'position' => 20]);
        TrainingLesson::factory()->create(['title' => 'Première', 'position' => 10]);
        TrainingLesson::factory()->draft()->create(['title' => 'Brouillon caché']);

        $this->actingAs($this->clipper())
            ->get(route('training.index'))
            ->assertSuccessful()
            ->assertSeeInOrder(['Première', 'Deuxième'])
            ->assertDontSee('Brouillon caché')
            ->assertSee('0 / 2');
    }

    #[Test]
    public function an_animated_page_with_voice_over_is_shown_in_an_isolated_frame(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('formation/explainer.html', '<html><body>animation</body></html>');

        $lesson = TrainingLesson::factory()->create(['video_path' => 'formation/explainer.html']);

        $this->assertTrue($lesson->isInteractivePage());

        $this->actingAs($this->clipper())
            ->get(route('training.show', $lesson))
            ->assertSuccessful()
            ->assertSee('<iframe', escape: false)
            ->assertSee('sandbox="allow-scripts"', escape: false)
            ->assertSee('formation/explainer.html', escape: false)
            ->assertDontSee('<video', escape: false);
    }

    #[Test]
    public function a_regular_video_file_still_uses_the_video_player(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('formation/clip.mp4', 'x');

        $lesson = TrainingLesson::factory()->create(['video_path' => 'formation/clip.mp4']);

        $this->assertFalse($lesson->isInteractivePage());

        $this->actingAs($this->clipper())
            ->get(route('training.show', $lesson))
            ->assertSee('<video', escape: false)
            ->assertDontSee('sandbox=', escape: false);
    }

    #[Test]
    public function a_draft_lesson_cannot_be_opened(): void
    {
        $draft = TrainingLesson::factory()->draft()->create();

        $this->actingAs($this->clipper())
            ->get(route('training.show', $draft))
            ->assertNotFound();
    }

    #[Test]
    public function completing_a_lesson_leads_to_the_next_one(): void
    {
        $first = TrainingLesson::factory()->create(['position' => 10]);
        $second = TrainingLesson::factory()->create(['position' => 20]);
        $clipper = $this->clipper();

        $this->actingAs($clipper)
            ->post(route('training.complete', $first))
            ->assertRedirect(route('training.show', $second));

        // Deux fois de suite : ni erreur d'unicité, ni doublon.
        $this->actingAs($clipper)->post(route('training.complete', $first));

        $this->assertSame(1, $clipper->completedLessons()->count());
        $this->assertTrue($first->isCompletedBy($clipper));
    }

    #[Test]
    public function finishing_every_lesson_unlocks_the_achievement_until_a_new_one_appears(): void
    {
        $lessons = TrainingLesson::factory()->count(2)->sequence(['position' => 1], ['position' => 2])->create();
        $clipper = $this->clipper();

        $trained = fn () => app(AchievementService::class)->for($clipper)->firstWhere('key', 'trained')->unlocked;

        $this->assertFalse($trained());

        foreach ($lessons as $lesson) {
            $this->actingAs($clipper)->post(route('training.complete', $lesson));
        }

        $this->assertTrue($trained());

        TrainingLesson::factory()->create(['position' => 3]);

        $this->assertFalse($trained());
    }

    #[Test]
    public function a_recorded_video_is_picked_up_by_its_slug(): void
    {
        Storage::fake('public');

        $lesson = TrainingLesson::factory()->create(['slug' => 'lier-ses-comptes']);
        $this->assertNull($lesson->videoFileUrl());

        UploadedFile::fake()->create('x.webm', 10, 'video/webm')
            ->storeAs(TrainingLesson::RECORDED_DIRECTORY, 'lier-ses-comptes.webm', 'public');

        $this->assertStringEndsWith('formation/lier-ses-comptes.webm', $lesson->videoFileUrl());

        $this->actingAs($this->clipper())
            ->get(route('training.show', $lesson))
            ->assertSuccessful()
            ->assertSee('<video', escape: false);
    }

    #[Test]
    public function a_youtube_page_link_becomes_an_embed(): void
    {
        $lesson = TrainingLesson::factory()->make(['video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=3']);

        $this->assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $lesson->embedUrl());
    }

    #[Test]
    public function raw_html_in_a_lesson_body_is_stripped(): void
    {
        $lesson = TrainingLesson::factory()->make(['body' => "**Gras**\n\n<script>alert(1)</script>"]);

        $html = (string) $lesson->bodyHtml();

        $this->assertStringContainsString('<strong>Gras</strong>', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    #[Test]
    public function the_default_course_seeds_idempotently(): void
    {
        $this->seed(TrainingLessonSeeder::class);
        $count = TrainingLesson::count();

        TrainingLesson::first()->update(['is_published' => false]);
        $this->seed(TrainingLessonSeeder::class);

        $this->assertSame($count, TrainingLesson::count());
        $this->assertSame(1, TrainingLesson::where('is_published', false)->count());
    }

    #[Test]
    public function the_navigation_links_to_the_training(): void
    {
        $this->actingAs($this->clipper())
            ->get(route('dashboard'))
            ->assertSee(route('training.index', absolute: false), escape: false);
    }
}
