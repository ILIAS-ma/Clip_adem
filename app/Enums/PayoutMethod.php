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
            self::PayPal => 'Versement automatique, sous 24 h après validation.',
            self::BankTransfer => 'Virement SEPA, 1 à 3 jours ouvrés après validation.',
        };
    }

    /**
     * Les virements PayPal partent tout seuls ; les virements bancaires sont
     * exécutés par un humain depuis la banque, puis pointés dans le back-office.
     */
    public function isAutomatic(): bool
    {
        return $this === self::PayPal;
    }
}
