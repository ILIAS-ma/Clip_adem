<?php

namespace Tests\Feature\Notifications;

use App\Contracts\CampaignBudgetService;
use App\Enums\CampaignStatus;
use App\Enums\ClipStatus;
use App\Enums\PayoutMethod;
use App\Enums\PayoutStatus;
use App\Enums\Platform;
use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\Payout;
use App\Models\User;
use App\Notifications\ClipRejected;
use App\Notifications\PayoutFailed;
use App\Notifications\PayoutPaid;
use App\Services\Moderation\ClipModerationService;
use App\Services\Payouts\PayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Ce que le clippeur apprend sans avoir à aller regarder.
 *
 * Avant, rien : un clip refusé, un virement parti, un versement rejeté — il
 * fallait ouvrir le site pour le découvrir. Pour quelqu'un qui travaille et
 * attend d'être payé, c'est le manque le plus visible au quotidien.
 */
class ClipperIsKeptInformedTest extends TestCase
{
    use RefreshDatabase;

    protected function clipper(): User
    {
        return User::factory()->create([
            'role' => UserRole::Clipper,
            'pseudo' => 'maya.clips',
            'paypal_email' => 'maya@paypal.test',
        ]);
    }

    protected function paidClip(User $clipper, int $views = 200_000): Clip
    {
        $campaign = Campaign::factory()
            ->withRate(Platform::TikTok, ratePer1kCents: 100)
            ->funded()
            ->create([
                'status' => CampaignStatus::Active,
                'budget_total_cents' => 100_000,
            ]);

        $clip = Clip::factory()->create([
            'campaign_id' => $campaign->getKey(),
            'user_id' => $clipper->getKey(),
            'platform' => Platform::TikTok,
            'status' => ClipStatus::Approved,
            'views_total' => $views,
        ]);

        app(CampaignBudgetService::class)->creditViews($clip, $views, "clip:{$clip->id}:snapshot:1");

        return $clip->fresh();
    }

    #[Test]
    public function a_rejected_clip_tells_its_author_why(): void
    {
        Notification::fake();

        $clipper = $this->clipper();
        $clip = $this->paidClip($clipper);

        app(ClipModerationService::class)->reject($clip, 'Hashtags obligatoires absents.');

        Notification::assertSentTo($clipper, ClipRejected::class, function (ClipRejected $notification) {
            // Un refus sans motif ne corrige aucun comportement : la personne
            // republiera exactement de la même façon.
            return str_contains($notification->reason, 'Hashtags')
                && $notification->wasPaid === false;
        });
    }

    #[Test]
    public function an_invalidation_warns_that_the_balance_just_dropped(): void
    {
        Notification::fake();

        $clipper = $this->clipper();
        $clip = $this->paidClip($clipper);

        app(ClipModerationService::class)->invalidate($clip, 'Vues achetées.');

        // Découvrir la baisse en regardant son solde, c'est croire à une erreur
        // de la plateforme.
        Notification::assertSentTo(
            $clipper,
            ClipRejected::class,
            fn (ClipRejected $notification) => $notification->wasPaid === true,
        );
    }

    #[Test]
    public function an_executed_transfer_is_announced(): void
    {
        Notification::fake();

        $clipper = $this->clipper();
        $clipper->forceFill([
            'payout_method' => PayoutMethod::BankTransfer,
            'iban' => 'FR7630006000011234567890189',
            'iban_last4' => '0189',
        ])->save();

        $payout = Payout::factory()->create([
            'user_id' => $clipper->getKey(),
            'status' => PayoutStatus::Approved,
            'method' => PayoutMethod::BankTransfer,
            'destination' => '•••• •••• 0189',
            'paypal_email' => null,
        ]);

        app(PayoutService::class)->markPaid($payout, null, 'VIR-77');

        Notification::assertSentTo($clipper, PayoutPaid::class);
    }

    #[Test]
    public function a_rejected_transfer_says_the_money_is_not_lost(): void
    {
        Notification::fake();

        $clipper = $this->clipper();
        $payout = Payout::factory()->create([
            'user_id' => $clipper->getKey(),
            'status' => PayoutStatus::Processing,
            'method' => PayoutMethod::PayPal,
        ]);

        app(PayoutService::class)->applyItemStatus($payout, [
            'transaction_status' => 'FAILED',
            'errors' => ['message' => 'Compte destinataire fermé'],
        ]);

        Notification::assertSentTo($clipper, PayoutFailed::class);
    }

    #[Test]
    public function an_intermediate_state_sends_nothing(): void
    {
        // Une notification par changement d'état finit en filtre anti-spam, ce
        // qui coûterait ensuite les deux qui comptent vraiment.
        Notification::fake();

        $clipper = $this->clipper();
        $payout = Payout::factory()->create([
            'user_id' => $clipper->getKey(),
            'status' => PayoutStatus::Approved,
            'method' => PayoutMethod::PayPal,
        ]);

        app(PayoutService::class)->applyItemStatus($payout, ['transaction_status' => 'PENDING']);

        Notification::assertNothingSent();
    }

    #[Test]
    public function an_approved_clip_does_not_spam_anyone(): void
    {
        // Rien à corriger, rien à décider : la bonne nouvelle se lit sur le
        // tableau de bord.
        Notification::fake();

        $clipper = $this->clipper();
        $clip = $this->paidClip($clipper);

        app(ClipModerationService::class)->approve($clip);

        Notification::assertNothingSent();
    }

    #[Test]
    public function a_failed_notification_never_undoes_the_decision(): void
    {
        // Une invalidation rend le budget à la campagne. Elle ne doit pas être
        // annulée parce qu'un e-mail n'est pas parti.
        $clipper = $this->clipper();
        $clip = $this->paidClip($clipper);

        Notification::shouldReceive('send')->andThrow(new \RuntimeException('SMTP injoignable'));

        app(ClipModerationService::class)->invalidate($clip, 'Vues achetées.');

        $this->assertSame(ClipStatus::Invalidated, $clip->fresh()->status);
        $this->assertSame(0, $clip->fresh()->earned_cents);
    }
}
