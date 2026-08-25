<?php

namespace App\Exceptions;

use RuntimeException;

class PayPalException extends RuntimeException
{
    public static function authenticationFailed(int $status, string $body): self
    {
        return new self("Authentification PayPal refusée (HTTP {$status}) : {$body}");
    }

    public static function batchRejected(string $senderBatchId, int $status, string $body): self
    {
        return new self("Lot PayPal {$senderBatchId} refusé (HTTP {$status}) : {$body}");
    }

    public static function batchLookupFailed(string $batchId, int $status, string $body): self
    {
        return new self("Lot PayPal {$batchId} illisible (HTTP {$status}) : {$body}");
    }

    public static function notConfigured(): self
    {
        return new self('PayPal n\'est pas configuré : renseignez PAYPAL_CLIENT_ID et PAYPAL_CLIENT_SECRET.');
    }
}
