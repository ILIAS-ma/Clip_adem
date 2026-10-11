<?php

namespace Tests\Feature\Clipper;

use App\Enums\CampaignStatus;
use App\Enums\ClipStatus;
use App\Enums\Platform;
use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\Creator;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Le rail du tableau de bord.
 *
 * Trois listes classées, reprises de l'interface FetchClip, qui répondent à
 * des questions que les cartes ne posent pas : où aller maintenant, qu'est-ce
 * qui marche chez moi, suis-je en état de participer.
 *
 * Ce qui doit tenir, ce sont les ordres de classement — un classement faux est
 * pire qu'une absence de classement, parce qu'on s'y fie.
 */
class DashboardRailTest extends TestCase
{
    use RefreshDatabase;

    protected function clipper(): User
    {
        return User::factory()->create([
            'role' => UserRole::Clipper,
            'email_verified_at' => now(),
            'pseudo' => 'maya.clips',
            'country' => 'FR',
            'paypal_email' => 'maya@paypal.test',
            'profile_completed_at' => now(),
        ]);
    }

    protected function campagne(string $titre, int $cachet, bool $ouverte = true): Campaign
    {
        return Campaign::factory()
            ->withRate(Platform::TikTok, ratePer1kCents: $cachet)
            ->funded()
            ->create([
                'title' => $titre,
                'creator_id' => Creator::factory()->create()->getKey(),
                'status' => CampaignStatus::Active,
                'starts_at' => $ouverte ? now()->subDay() : now()->addWeek(),
            ]);
    }

    #[Test]
    public function open_campaigns_are_ranked_on_the_best_rate(): void
    {
        /*
         * Sur le cachet, pas sur la date : le clippeur choisit où aller, et
         * c'est le tarif qui décide. Un classement par date d'ouverture
         * mettrait en tête la campagne la plus récente, qui n'est pas la plus
         * intéressante.
         */
        $this->campagne('Petite', cachet: 50);
        $this->campagne('Grosse', cachet: 300);
        $this->campagne('Moyenne', cachet: 150);

        $campagnes = $this->actingAs($this->clipper())
            ->get(route('dashboard'))
            ->assertSuccessful()
            ->viewData('liveCampaigns');

        $this->assertSame(
            ['Grosse', 'Moyenne', 'Petite'],
            $campagnes->pluck('title')->all(),
        );
    }

    #[Test]
    public function a_campaign_not_yet_open_stays_out_of_the_rail(): void
    {
        // Le rail dit « ouvertes maintenant ». Y mettre une campagne qui
        // n'accepte encore rien ferait cliquer pour rien.
        $this->campagne('Ouverte', cachet: 100);
        $this->campagne('Pas encore', cachet: 500, ouverte: false);

        $campagnes = $this->actingAs($this->clipper())
            ->get(route('dashboard'))
            ->assertSuccessful()
            ->viewData('liveCampaigns');

        $this->assertSame(['Ouverte'], $campagnes->pluck('title')->all());
    }

    #[Test]
    public function my_best_clips_are_ranked_on_paid_views(): void
    {
        /*
         * Sur les vues rémunérées, jamais les vues brutes. Un clip à
         * 500 000 vues dont le budget s'est épuisé à 10 000 n'a rapporté que
         * ces 10 000 : le mettre en tête enseignerait de refaire la mauvaise
         * chose.
         */
        $user = $this->clipper();
        $campagne = $this->campagne('Campagne', cachet: 100);

        $faible = Clip::factory()->create([
            'user_id' => $user->getKey(),
            'campaign_id' => $campagne->getKey(),
            'status' => ClipStatus::Approved,
            'views_total' => 500_000,
            'paid_views' => 10_000,
            'earned_cents' => 1_000,
        ]);

        $fort = Clip::factory()->create([
            'user_id' => $user->getKey(),
            'campaign_id' => $campagne->getKey(),
            'status' => ClipStatus::Approved,
            'views_total' => 80_000,
            'paid_views' => 80_000,
            'earned_cents' => 8_000,
        ]);

        $clips = $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertSuccessful()
            ->viewData('topClips');

        $this->assertSame(
            [$fort->getKey(), $faible->getKey()],
            $clips->pluck('id')->all(),
        );
    }

    #[Test]
    public function the_status_row_says_what_is_missing(): void
    {
        // L'état du compte avant toute lecture : on sait tout de suite si
        // l'on peut participer, au lieu de le découvrir en essayant.
        $user = $this->clipper();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertSuccessful()
            ->assertSee('0 compte lié');

        SocialAccount::factory()->create([
            'user_id' => $user->getKey(),
            'platform' => Platform::TikTok,
            'handle' => 'maya.clips',
            'needs_reconnect' => true,
        ]);

        $this->actingAs($user->fresh())
            ->get(route('dashboard'))
            ->assertSuccessful()
            ->assertSee('1 compte lié')
            ->assertSee('1 à reconnecter');
    }

    #[Test]
    public function the_accounts_block_shows_the_handles(): void
    {
        $user = $this->clipper();

        SocialAccount::factory()->create([
            'user_id' => $user->getKey(),
            'platform' => Platform::TikTok,
            'handle' => 'maya.clips',
            'needs_reconnect' => false,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertSuccessful()
            ->assertSee('@maya.clips');
    }

    #[Test]
    public function an_empty_rail_says_so_instead_of_showing_nothing(): void
    {
        // Une liste vide sans un mot se lit comme une panne de chargement.
        $this->actingAs($this->clipper())
            ->get(route('dashboard'))
            ->assertSuccessful()
            ->assertSee('Aucune campagne ouverte.')
            ->assertSee('Rien encore publié.')
            ->assertSee('Aucun compte lié.');
    }

    #[Test]
    public function the_rail_leads_to_discovery(): void
    {
        $this->actingAs($this->clipper())
            ->get(route('dashboard'))
            ->assertSuccessful()
            ->assertSee(route('discovery.index', absolute: false), escape: false);
    }
}
