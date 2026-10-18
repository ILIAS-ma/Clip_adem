<?php

namespace App\Support\Campaigns;

use App\Support\Money;

/**
 * La récompense d'une place de classement : de l'argent, ou un objet.
 *
 * Une place sans récompense n'est pas représentée : un classement « pour la
 * gloire » n'a simplement aucune place récompensée.
 */
final readonly class Reward
{
    public const MONEY = 'money';

    public const ITEM = 'item';

    public function __construct(
        public string $type,
        public int $amountCents = 0,
        public ?string $label = null,
    ) {}

    /**
     * Lit une place telle que stockée en base. Les premières versions ne
     * stockaient qu'un montant en centimes : il est relu comme de l'argent.
     */
    public static function from(mixed $raw): ?self
    {
        if (is_numeric($raw)) {
            return (int) $raw > 0 ? new self(self::MONEY, (int) $raw) : null;
        }

        if (! is_array($raw)) {
            return null;
        }

        $type = ($raw['type'] ?? self::MONEY) === self::ITEM ? self::ITEM : self::MONEY;

        return match ($type) {
            self::ITEM => filled($raw['label'] ?? null) ? new self(self::ITEM, label: trim($raw['label'])) : null,
            default => (int) ($raw['amount_cents'] ?? $raw['amount'] ?? 0) > 0
                ? new self(self::MONEY, (int) ($raw['amount_cents'] ?? $raw['amount']))
                : null,
        };
    }

    public function isMoney(): bool
    {
        return $this->type === self::MONEY;
    }

    public function isItem(): bool
    {
        return $this->type === self::ITEM;
    }

    /** « 100,00 € » ou « PS5 ». */
    public function display(): string
    {
        return $this->isMoney() ? Money::euros($this->amountCents) : (string) $this->label;
    }

    /** @return array{type: string, amount_cents?: int, label?: string} */
    public function toArray(): array
    {
        return $this->isMoney()
            ? ['type' => self::MONEY, 'amount_cents' => $this->amountCents]
            : ['type' => self::ITEM, 'label' => (string) $this->label];
    }
}
