<?php

namespace App\Services\Payouts;

use App\Exceptions\PayPalException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Client PayPal Payouts v1.
 *
 * Volontairement minimal : trois appels suffisent pour verser de l'argent et
 * savoir si c'est parti. Tout ce qui touche à la décision de payer est dans
 * PayoutService, pas ici.
 */
class PayPalClient
{
    protected const TOKEN_CACHE_KEY = 'paypal.access_token';

    public function baseUrl(): string
    {
        return config('services.paypal.mode') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    public function isConfigured(): bool
    {
        return filled(config('services.paypal.client_id'))
            && filled(config('services.paypal.client_secret'));
    }

    /**
     * Jeton d'accès, mis en cache un peu moins longtemps que sa durée de vie
     * réelle pour ne jamais présenter un jeton expiré à la seconde près.
     */
    public function accessToken(): string
    {
        return Cache::remember(self::TOKEN_CACHE_KEY, now()->addMinutes(25), function () {
            $response = Http::asForm()
                ->withBasicAuth(
                    config('services.paypal.client_id'),
                    config('services.paypal.client_secret'),
                )
                ->post($this->baseUrl().'/v1/oauth2/token', ['grant_type' => 'client_credentials']);

            if ($response->failed()) {
                throw PayPalException::authenticationFailed($response->status(), $response->body());
            }

            return $response->json('access_token');
        });
    }

    /**
     * Crée un lot de versements.
     *
     * @param  string  $senderBatchId  Identifiant idempotent : PayPal refuse un
     *                                 second lot portant le même, ce qui protège
     *                                 d'un double virement en cas de rejeu.
     * @param  array<int, array{receiver: string, amount_cents: int, currency: string, sender_item_id: string, note?: string}>  $items
     * @return array<string, mixed>
     */
    public function createBatch(string $senderBatchId, array $items): array
    {
        $payload = [
            'sender_batch_header' => [
                'sender_batch_id' => $senderBatchId,
                'email_subject' => config('services.paypal.email_subject'),
            ],
            'items' => array_map(fn (array $item) => [
                'recipient_type' => 'EMAIL',
                'receiver' => $item['receiver'],
                'amount' => [
                    // PayPal attend une chaîne décimale : la conversion depuis
                    // les centimes se fait ici, au dernier moment.
                    'value' => number_format($item['amount_cents'] / 100, 2, '.', ''),
                    'currency' => $item['currency'],
                ],
                'sender_item_id' => $item['sender_item_id'],
                'note' => $item['note'] ?? 'Rémunération de vos clips',
            ], $items),
        ];

        $response = $this->request()->post($this->baseUrl().'/v1/payments/payouts', $payload);

        if ($response->failed()) {
            Log::error('PayPal Payouts : création de lot refusée', [
                'sender_batch_id' => $senderBatchId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw PayPalException::batchRejected($senderBatchId, $response->status(), $response->body());
        }

        return $response->json();
    }

    /** @return array<string, mixed> */
    public function getBatch(string $batchId): array
    {
        $response = $this->request()->get($this->baseUrl()."/v1/payments/payouts/{$batchId}");

        if ($response->failed()) {
            throw PayPalException::batchLookupFailed($batchId, $response->status(), $response->body());
        }

        return $response->json();
    }

    protected function request(): PendingRequest
    {
        return Http::withToken($this->accessToken())
            ->acceptJson()
            ->asJson()
            ->timeout(30)
            ->retry(2, 500, throw: false);
    }
}
