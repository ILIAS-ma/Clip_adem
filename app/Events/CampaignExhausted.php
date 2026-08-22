<?php

namespace App\Events;

use App\Models\Campaign;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Émis quand une campagne bascule Active → Épuisée.
 *
 * Dispatché APRÈS le commit : aucun listener ne doit s'exécuter pendant que
 * la ligne campagne est verrouillée.
 */
class CampaignExhausted
{
    use Dispatchable;

    public function __construct(public Campaign $campaign) {}
}
