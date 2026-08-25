<?php

namespace App\Http\Controllers;

use App\Models\Payout;
use App\Services\Payouts\PayoutService;
use App\Services\Payouts\PayPalClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Retours asynchrones de PayPal sur les versements.
 *
 * Les webhooks arrivent en double, dans le désordre, ou pas du tout : le
 * traitement est donc idempotent, et `payouts:sync` sert de filet.
 */
class PayPalWebhookController extends Controller
{
    public function __invoke(Request $request, PayoutService $payouts, PayPalClient $client): JsonResponse
    {
        if (! $this->verifySignature($request, $client)) {
            Log::warning('Webhook PayPal rejeté : signature invalide.', [
                'event_type' => $request->input('event_type'),
            ]);

            return response()->json(['status' => 'invalid_signature'], 400);
        }

        $eventType = (string) $request->input('event_type');

        if (! str_starts_with($eventType, 'PAYMENT.PAYOUTS-ITEM.')) {
            // Les événements de lot ne portent aucune information qu'on n'ait
            // déjà par item : on les acquitte sans rien faire.
            return response()->json(['status' => 'ignored']);
        }

        $resource = $request->input('resource', []);
        $payout = $this->resolvePayout($resource);

        if (! $payout) {
            Log::warning('Webhook PayPal sans retrait correspondant.', [
                'event_type' => $eventType,
                'payout_item_id' => data_get($resource, 'payout_item_id'),
            ]);

            // On acquitte quand même : sinon PayPal réessaiera indéfiniment.
            return response()->json(['status' => 'unknown_payout']);
        }

        $payouts->applyItemStatus($payout, $resource);

        return response()->json(['status' => 'ok']);
    }

    /** @param  array<string, mixed>  $resource */
    protected function resolvePayout(array $resource): ?Payout
    {
        if ($itemId = data_get($resource, 'payout_item.sender_item_id')) {
            return Payout::find((int) $itemId);
        }

        if ($payoutItemId = data_get($resource, 'payout_item_id')) {
            return Payout::where('paypal_payout_item_id', $payoutItemId)->first();
        }

        return null;
    }

    /**
     * Vérification de signature côté PayPal.
     *
     * Sans `PAYPAL_WEBHOOK_ID`, la vérification est impossible : on refuse
     * plutôt que d'accepter n'importe quelle requête capable de faire passer un
     * virement pour réussi — sauf en environnement local, pour pouvoir tester.
     */
    protected function verifySignature(Request $request, PayPalClient $client): bool
    {
        $webhookId = config('services.paypal.webhook_id');

        if (blank($webhookId)) {
            return app()->environment('local', 'testing');
        }

        $response = Http::withToken($client->accessToken())
            ->acceptJson()
            ->asJson()
            ->post($client->baseUrl().'/v1/notifications/verify-webhook-signature', [
                'auth_algo' => $request->header('PAYPAL-AUTH-ALGO'),
                'cert_url' => $request->header('PAYPAL-CERT-URL'),
                'transmission_id' => $request->header('PAYPAL-TRANSMISSION-ID'),
                'transmission_sig' => $request->header('PAYPAL-TRANSMISSION-SIG'),
                'transmission_time' => $request->header('PAYPAL-TRANSMISSION-TIME'),
                'webhook_id' => $webhookId,
                'webhook_event' => $request->all(),
            ]);

        return $response->successful()
            && $response->json('verification_status') === 'SUCCESS';
    }
}
