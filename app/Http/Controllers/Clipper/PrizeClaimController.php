<?php

namespace App\Http\Controllers\Clipper;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\PrizeClaim;
use App\Services\Clippers\ClipperPrizes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * « Réclamer mes prix » : prévenir l'équipe qu'un classement terminé n'a pas encore été versé.
 *
 * Ne verse rien. Les prix sont attribués par l'équipe, qui vérifie d'abord les égalités et les
 * signalements ; la demande la prévient simplement que quelqu'un attend.
 */
class PrizeClaimController extends Controller
{
    public function store(Request $request, Campaign $campaign, ClipperPrizes $prizes): RedirectResponse
    {
        $clipper = $request->user();

        // Seulement pour un prix acquis (campagne finie) et pas encore versé.
        abort_unless($prizes->hasPending($clipper, $campaign), 403);

        $claim = PrizeClaim::firstOrCreate(
            ['user_id' => $clipper->getKey(), 'campaign_id' => $campaign->getKey()],
            ['claimed_at' => now()],
        );

        return back()->with('status', $claim->wasRecentlyCreated
            ? 'Demande envoyée. L\'équipe vérifie le classement puis verse vos prix : vous serez prévenu.'
            : 'Votre demande est déjà enregistrée : l\'équipe verse les prix après vérification du classement.');
    }
}
