<?php

namespace Tests\Feature\Clips;

use App\Enums\CampaignStatus;
use App\Enums\ParticipationStatus;
use App\Enums\Platform;
use App\Enums\UserRole;
use App\Exceptions\ClipSubmissionRefused;
use App\Models\Campaign;
use App\Models\Participation;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Clips\ClipSubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Le contrôle « cette vidéo est-elle bien la vôtre ? ».
 *
 * L'URL d'une vidéo porte un **nom d'utilisateur** : `@coolerkek0`. Selon la
 * portée accordée, TikTok ne rend parfois que le **nom affiché** : « zebi
 * land ». Les comparer refusait à quelqu'un sa propre vidéo, en lui affirmant
 * qu'elle venait d'un autre compte — un message qui accuse, et qui a tort.
 *
 * Le filtre ne se tait que faute de valeur comparable. La vérification qui
 * compte reste entière : `ClipComplianceChecker::checkOwnership` compare
 * l'identifiant renvoyé par l'API au premier relevé de vues.
 */
class HandleMismatchTest extends TestCase
{
    use RefreshDatabase;

    protected User $clipper;

    protected Campaign $campaign;

    /** Prépare un clippeur dont le compte lié porte le pseudo voulu. */
    protected function linkedAs(string $handle): void
    {
        $this->clipper = User::factory()->create([
            'role' => UserRole::Clipper,
            'email_verified_at' => now(),
            'pseudo' => 'clip'.fake()->unique()->numberBetween(1, 99999),
            'country' => 'FR',
            'paypal_email' => fake()->unique()->safeEmail(),
            'profile_completed_at' => now(),
        ]);

        $this->campaign = Campaign::factory()
            ->withRate(Platform::TikTok, ratePer1kCents: 100)
            ->funded()
            ->create(['status' => CampaignStatus::Active, 'budget_total_cents' => 100_000]);

        $account = SocialAccount::factory()->create([
            'user_id' => $this->clipper->getKey(),
            'platform' => Platform::TikTok,
            'handle' => $handle,
        ]);

        Participation::factory()->create([
            'user_id' => $this->clipper->getKey(),
            'campaign_id' => $this->campaign->getKey(),
            'social_account_id' => $account->getKey(),
            'status' => ParticipationStatus::Approved,
        ]);
    }

    protected function submit(string $url): void
    {
        app(ClipSubmissionService::class)->submit(
            $this->clipper->fresh(),
            $this->campaign->fresh(),
            $url,
        );
    }

    #[Test]
    public function a_display_name_never_refuses_your_own_video(): void
    {
        // Le cas vécu : compte lié sous « zebi land », vidéo publiée par
        // @coolerkek0. C'est la même personne.
        $this->linkedAs('zebi land');

        $this->submit('https://www.tiktok.com/@coolerkek0/video/7300000000000000001');

        $this->assertDatabaseHas('clips', ['user_id' => $this->clipper->getKey()]);
    }

    #[Test]
    public function a_real_username_that_does_not_match_is_still_refused(): void
    {
        /*
         * L'assouplissement ne doit pas vider le contrôle de son sens : quand
         * la valeur stockée est bien un nom d'utilisateur, deux pseudos
         * différents restent deux comptes différents, et la vidéo est refusée.
         */
        $this->linkedAs('coolerkek0');

        $this->expectException(ClipSubmissionRefused::class);

        $this->submit('https://www.tiktok.com/@quelquun.dautre/video/7300000000000000002');
    }

    #[Test]
    public function the_matching_username_passes(): void
    {
        $this->linkedAs('coolerkek0');

        $this->submit('https://www.tiktok.com/@coolerkek0/video/7300000000000000003');

        $this->assertDatabaseHas('clips', ['user_id' => $this->clipper->getKey()]);
    }

    #[Test]
    public function the_comparison_ignores_case_and_the_at_sign(): void
    {
        // « @CoolerKek0 » et « coolerkek0 » désignent le même compte : refuser
        // sur une majuscule serait un faux positif de plus.
        $this->linkedAs('@CoolerKek0');

        $this->submit('https://www.tiktok.com/@coolerkek0/video/7300000000000000004');

        $this->assertDatabaseHas('clips', ['user_id' => $this->clipper->getKey()]);
    }
}
