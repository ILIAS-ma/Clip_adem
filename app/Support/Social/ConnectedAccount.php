<?php

namespace App\Support\Social;

use App\Enums\Platform;
use Carbon\CarbonInterface;

/**
 * Ce qu'un fournisseur renvoie après un consentement OAuth réussi.
 */
final readonly class ConnectedAccount
{
    public function __construct(
        public Platform $platform,
        public string $externalAccountId,
        public ?string $handle,
        public string $accessToken,
        public ?string $refreshToken = null,
        public ?CarbonInterface $expiresAt = null,
        /** @var array<int, string> Permissions réellement accordées. */
        public array $scopes = [],
        public ?int $followersCount = null,
    ) {}
}
