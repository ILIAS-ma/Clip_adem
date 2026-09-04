<?php

namespace App\Exceptions;

use App\Enums\PayoutMethod;
use App\Enums\PayoutStatus;
use RuntimeException;

class PayoutRefused extends RuntimeException
{
    public static function bannedClipper(): self
    {
        return new self('Ce compte est banni : aucun retrait ne peut être demandé.');
    }

    public static function missingPaypalEmail(): self
    {
        return new self('Renseignez une adresse PayPal avant de demander un retrait.');
    }

    public static function missingPayoutDestination(PayoutMethod $method): self
    {
        return new self(match ($method) {
            PayoutMethod::PayPal => 'Renseignez votre adresse PayPal avant de demander un retrait.',
            PayoutMethod::BankTransfer => 'Renseignez votre IBAN avant de demander un retrait.',
        });
    }

    public static function notAManualPayout(PayoutMethod $method): self
    {
        return new self(
            'Seul un virement bancaire se pointe à la main : celui-ci part par '
            .$method->label().', laissez le lot faire son travail.'
        );
    }

    public static function notApproved(PayoutStatus $status): self
    {
        return new self(
            'Un virement se pointe une fois validé (statut actuel : '.$status->label().').'
        );
    }

    public static function belowMinimum(int $amount, int $minimum): self
    {
        return new self(sprintf(
            'Retrait minimum de %s € (demandé : %s €).',
            number_format($minimum / 100, 2, ',', ' '),
            number_format($amount / 100, 2, ',', ' '),
        ));
    }

    public static function insufficientBalance(int $amount, int $available): self
    {
        return new self(sprintf(
            'Solde insuffisant : %s € disponibles, %s € demandés.',
            number_format($available / 100, 2, ',', ' '),
            number_format($amount / 100, 2, ',', ' '),
        ));
    }

    public static function notPending(PayoutStatus $status): self
    {
        return new self('Seul un retrait en attente peut être validé (statut actuel : '.$status->label().').');
    }

    public static function alreadyInFlight(PayoutStatus $status): self
    {
        return new self('Retrait '.$status->label().' : il faut attendre le retour de PayPal.');
    }
}
