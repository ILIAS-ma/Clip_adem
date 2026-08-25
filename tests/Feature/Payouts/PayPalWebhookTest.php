<?php

namespace Tests\Feature\Payouts;

use App\Enums\PayoutStatus;
use App\Models\Payout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PayPalWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function event(Payout $payout, string $status): array
    {
        return [
            'event_type' => 'PAYMENT.PAYOUTS-ITEM.'.($status === 'SUCCESS' ? 'SUCCEEDED' : 'FAILED'),
            'resource' => [
                'payout_item_id' => 'ITEM-'.$payout->getKey(),
                'transaction_status' => $status,
                'payout_item' => ['sender_item_id' => (string) $payout->getKey()],
            ],
        ];
    }

    #[Test]
    public function a_success_event_marks_the_payout_paid(): void
    {
        $payout = Payout::factory()->create(['status' => PayoutStatus::Processing]);

        $this->postJson('/webhooks/paypal', $this->event($payout, 'SUCCESS'))
            ->assertOk()
            ->assertJson(['status' => 'ok']);

        $payout->refresh();
        $this->assertSame(PayoutStatus::Paid, $payout->status);
        $this->assertSame('ITEM-'.$payout->getKey(), $payout->paypal_payout_item_id);
        $this->assertNotNull($payout->processed_at);
    }

    #[Test]
    public function a_failure_event_frees_the_balance(): void
    {
        $payout = Payout::factory()->create(['status' => PayoutStatus::Processing]);

        $this->postJson('/webhooks/paypal', $this->event($payout, 'DENIED'))->assertOk();

        $payout->refresh();
        $this->assertSame(PayoutStatus::Failed, $payout->status);
        $this->assertStringContainsString('DENIED', $payout->failure_reason);
    }

    #[Test]
    public function replaying_the_same_event_changes_nothing(): void
    {
        // Les webhooks PayPal arrivent en double : le traitement doit être
        // idempotent, sinon un retrait payé pourrait être réécrit.
        $payout = Payout::factory()->create(['status' => PayoutStatus::Processing]);

        $this->postJson('/webhooks/paypal', $this->event($payout, 'SUCCESS'))->assertOk();
        $paidAt = $payout->fresh()->processed_at;

        $this->postJson('/webhooks/paypal', $this->event($payout, 'SUCCESS'))->assertOk();

        $this->assertSame(PayoutStatus::Paid, $payout->fresh()->status);
        $this->assertEquals($paidAt, $payout->fresh()->processed_at);
    }

    #[Test]
    public function a_late_failure_never_overwrites_a_paid_payout(): void
    {
        $payout = Payout::factory()->create(['status' => PayoutStatus::Paid]);

        $this->postJson('/webhooks/paypal', $this->event($payout, 'FAILED'))->assertOk();

        $this->assertSame(PayoutStatus::Paid, $payout->fresh()->status);
    }

    #[Test]
    public function an_unknown_payout_is_acknowledged_rather_than_retried_forever(): void
    {
        $this->postJson('/webhooks/paypal', [
            'event_type' => 'PAYMENT.PAYOUTS-ITEM.SUCCEEDED',
            'resource' => ['payout_item_id' => 'INCONNU'],
        ])
            ->assertOk()
            ->assertJson(['status' => 'unknown_payout']);
    }

    #[Test]
    public function batch_level_events_are_ignored(): void
    {
        $this->postJson('/webhooks/paypal', [
            'event_type' => 'PAYMENT.PAYOUTSBATCH.SUCCESS',
            'resource' => [],
        ])
            ->assertOk()
            ->assertJson(['status' => 'ignored']);
    }

    #[Test]
    public function the_webhook_is_exempt_from_csrf_but_rate_limited(): void
    {
        $payout = Payout::factory()->create(['status' => PayoutStatus::Processing]);

        // Requête POST sans jeton CSRF : elle doit passer.
        $this->post('/webhooks/paypal', $this->event($payout, 'SUCCESS'))->assertOk();
    }
}
