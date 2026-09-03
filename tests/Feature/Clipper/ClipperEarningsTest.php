<?php

namespace Tests\Feature\Clipper;

use App\Contracts\CampaignBudgetService;
use App\Enums\CampaignStatus;
use App\Enums\ClipStatus;
use App\Enums\PayoutStatus;
use App\Enums\Platform;
use App\Enums\UserRole;
use App\Livewire\RequestPayout;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClipperEarningsTest extends TestCase
{
    use RefreshDatabase;

    protected function clipper(): User
    {
        return User::factory()->create([
            'role' => UserRole::Clipper,
            'email_verified_at' => now(),
            'pseudo' => 'clip'.fake()->unique()->numberBetween(1, 99999),
            'country' => 'FR',
            'paypal_email' => fake()->unique()->safeEmail(),
        ]);
    }

    /** Un clip crédité par le moteur, pour un montant exact. */
    protected function paidClip(User $clipper, int $cents, array $campaignAttributes = []): Clip
    {
        $campaign = Campaign::factory()
            ->withRate(Platform::TikTok, ratePer1kCents: 100)
            ->create(array_merge([
                'status' => CampaignStatus::Active,
                'budget_total_cents' => max($cents, 1) * 10,
            ], $campaignAttributes));

        $account = SocialAccount::factory()->create([
            'user_id' => $clipper->getKey(),
            'platform' => Platform::TikTok,
        ]);

        $clip = Clip::factory()->create([
            'campaign_id' => $campaign->getKey(),
            'user_id' => $clipper->getKey(),
            'social_account_id' => $account->getKey(),
            'platform' => Platform::TikTok,
            'status' => ClipStatus::Approved,
            'views_total' => $cents * 10,
        ]);

        // 1 € pour 1000 vues : $cents * 10 vues valent exactement $cents.
        app(CampaignBudgetService::class)->creditViews($clip, $cents * 10, "clip:{$clip->id}:snapshot:1");

        return $clip->fresh();
    }

    #[Test]
    public function the_earnings_page_separates_validated_from_estimated(): void
    {
        $clipper = $this->clipper();
        $clip = $this->paidClip($clipper, 5_000);

        // Des vues relevées mais pas encore créditées.
        $clip->forceFill(['views_total' => 100_000])->save();

        $this->actingAs($clipper)
            ->get(route('earnings.index'))
            ->assertSuccessful()
            ->assertSee('Gains validés')
            ->assertSee('Estimé en attente')
            ->assertSee('50,00 €');
    }

    #[Test]
    public function the_estimate_never_promises_more_than_the_remaining_budget(): void
    {
        // Un calcul maison « vues × taux » annoncerait 100 € sur une campagne
        // qui n'a plus que 20 € : quote() applique le reliquat et les plafonds.
        $clipper = $this->clipper();
        $clip = $this->paidClip($clipper, 8_000, ['budget_total_cents' => 10_000]);

        $clip->forceFill(['views_total' => 1_000_000])->save();

        $quote = app(CampaignBudgetService::class)->quote($clip->fresh(), 1_000_000);

        $this->assertSame(2_000, $quote->payableCents, 'L\'estimation doit être plafonnée au reliquat.');
    }

    #[Test]
    public function a_clipper_can_request_a_withdrawal_from_the_page(): void
    {
        $clipper = $this->clipper();
        $this->paidClip($clipper, 8_000);

        Livewire::actingAs($clipper)
            ->test(RequestPayout::class)
            ->set('amount', '50.00')
            ->call('request')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('payouts', [
            'user_id' => $clipper->getKey(),
            'amount_cents' => 5_000,
        ]);

        $this->assertSame(3_000, $clipper->fresh()->availableBalanceCents());
    }

    #[Test]
    public function requesting_more_than_the_balance_shows_the_reason(): void
    {
        $clipper = $this->clipper();
        $this->paidClip($clipper, 2_000);

        Livewire::actingAs($clipper)
            ->test(RequestPayout::class)
            ->set('amount', '500')
            ->call('request')
            ->assertHasErrors('amount');

        $this->assertSame(0, $clipper->payouts()->count());
    }

    #[Test]
    public function a_small_withdrawal_from_a_clean_clipper_is_auto_approved(): void
    {
        $clipper = $this->clipper();
        $this->paidClip($clipper, 4_000);

        Livewire::actingAs($clipper)
            ->test(RequestPayout::class)
            ->set('amount', '20')
            ->call('request')
            ->assertHasNoErrors();

        $this->assertSame(PayoutStatus::Approved, $clipper->payouts()->sole()->status);
    }

    #[Test]
    public function the_clips_pages_render(): void
    {
        $clipper = $this->clipper();
        $clip = $this->paidClip($clipper, 3_000);

        $this->actingAs($clipper)->get(route('clips.index'))->assertSuccessful();
        $this->actingAs($clipper)->get(route('clips.show', $clip))->assertSuccessful();
    }

    #[Test]
    public function a_clipper_cannot_open_someone_elses_clip(): void
    {
        $clip = $this->paidClip($this->clipper(), 1_000);

        $this->actingAs($this->clipper())
            ->get(route('clips.show', $clip))
            ->assertForbidden();
    }

    #[Test]
    public function the_accounts_page_renders_and_announces_the_simulation(): void
    {
        $this->actingAs($this->clipper())
            ->get(route('accounts.index'))
            ->assertSuccessful()
            ->assertSee('TikTok')
            // Laisser croire à une vraie liaison ferait perdre du temps au
            // premier comportement inattendu.
            ->assertSee('Mode démonstration');
    }
}
