<?php

namespace App\Enums;

/**
 * Par où l'argent sort de la plateforme.
 *
 * Le mode est figé sur le retrait au moment de la demande, pas lu depuis le
 * profil au moment du versement : un clippeur qui change de RIB entre les deux
 * ne doit pas déplacer un virement déjà en cours.
 */
enum PayoutMethod: string
{
    case PayPal = 'paypal';
    case BankTransfer = 'bank_transfer';

    public function label(): string
    {
        return match ($this) {
            self::PayPal => 'PayPal',
            self::BankTransfer => 'Virement bancaire',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::PayPal => $this->isAutomatic()
                ? 'Versement automatique, sous 24 h après validation.'
                : 'Envoyé à la main par un administrateur, sous 24 à 48 h après validation.',
            self::BankTransfer => 'Virement SEPA, 1 à 3 jours ouvrés après validation.',
        };
    }

    /**
     * En principe, les virements PayPal partent tout seuls par lot via
     * l'API Payouts ; les virements bancaires sont exécutés par un humain
     * depuis la banque, puis pointés dans le back-office.
     *
     * En pratique, tant que l'app PayPal n'a pas ses identifiants Payouts
     * vérifiés en production, PayPal suit le même chemin manuel qu'un
     * virement bancaire — voir clipping.payouts.paypal_automatic.
     */
    public function isAutomatic(): bool
    {
        return $this === self::PayPal && config('clipping.payouts.paypal_automatic');
    }
}
