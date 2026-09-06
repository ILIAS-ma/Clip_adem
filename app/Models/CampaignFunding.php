<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un encaissement reçu du créateur pour financer une campagne.
 *
 * C'est le pendant du grand livre des dépenses : en ajout seul, signé, jamais
 * modifié. Un remboursement au créateur est une ligne négative, pas une
 * suppression — sans quoi on ne saurait plus, six mois après, ce qui a
 * réellement transité.
 */
#[Fillable([
    'campaign_id', 'amount_cents', 'currency', 'method',
    'reference', 'note', 'received_at', 'recorded_by',
])]
class CampaignFunding extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'received_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /** Qui a enregistré la ligne — en cas de litige, la question se pose. */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function isRefund(): bool
    {
        return $this->amount_cents < 0;
    }

    /** @return array<string, string> */
    public static function methods(): array
    {
        return [
            'transfer' => 'Virement',
            'card' => 'Carte bancaire',
            'paypal' => 'PayPal',
            'other' => 'Autre',
        ];
    }
}
