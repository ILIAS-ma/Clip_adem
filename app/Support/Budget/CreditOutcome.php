<?php

namespace App\Support\Budget;

/**
 * Issue d'un appel à creditViews(). Aucune de ces valeurs n'est une erreur :
 * le module clippeur doit savoir distinguer « rien à payer » de « plus de
 * budget » pour afficher le bon message au clippeur.
 */
enum CreditOutcome: string
{
    /** Le delta de vues a été payé intégralement. */
    case Credited = 'credited';

    /** Payé partiellement : reliquat de budget ou plafond anti-abus atteint. */
    case Capped = 'capped';

    /** Cette clé d'idempotence a déjà été traitée. Aucun débit supplémentaire. */
    case AlreadyProcessed = 'already_processed';

    /** Aucune vue nouvelle depuis le dernier crédit. */
    case NothingToCredit = 'nothing_to_credit';

    /** Campagne en pause, épuisée, terminée ou hors de sa fenêtre de diffusion. */
    case CampaignClosed = 'campaign_closed';

    /** Le clip n'est pas (ou plus) validé par la modération. */
    case ClipNotPayable = 'clip_not_payable';

    /** Le clip n'atteint pas le seuil minimum de vues de la campagne. */
    case BelowThreshold = 'below_threshold';

    /** La plateforme du clip n'a pas de taux actif sur cette campagne. */
    case NoRate = 'no_rate';

    /** Budget épuisé, ou plafond du clippeur déjà atteint. */
    case NoBudgetLeft = 'no_budget_left';

    public function isPaid(): bool
    {
        return $this === self::Credited || $this === self::Capped;
    }

    public function label(): string
    {
        return match ($this) {
            self::Credited => 'Crédité',
            self::Capped => 'Crédité partiellement (plafond atteint)',
            self::AlreadyProcessed => 'Déjà traité',
            self::NothingToCredit => 'Aucune vue nouvelle',
            self::CampaignClosed => 'Campagne fermée',
            self::ClipNotPayable => 'Clip non validé',
            self::BelowThreshold => 'Sous le seuil de vues',
            self::NoRate => 'Aucun taux pour cette plateforme',
            self::NoBudgetLeft => 'Budget épuisé',
        };
    }
}
