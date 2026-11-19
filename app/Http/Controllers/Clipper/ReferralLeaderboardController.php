<?php

namespace App\Http\Controllers\Clipper;

use App\Http\Controllers\Controller;
use App\Services\Referrals\ReferralService;
use App\Services\Referrals\ReferrerLeaderboard;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Le classement des parrains : qui amène le plus de clippeurs qui participent vraiment. */
class ReferralLeaderboardController extends Controller
{
    public function __invoke(Request $request, ReferrerLeaderboard $leaderboard, ReferralService $referrals): View
    {
        // Parrainage suspendu (REFERRALS_ENABLED=false) : la page n'existe plus pour les clippeurs.
        abort_unless(config('clipping.referrals.enabled'), 404);

        $user = $request->user();
        $period = ReferrerLeaderboard::period($request->query('periode'));

        return view('clipper.referral-leaderboard', $leaderboard->for($user, $period) + [
            'period' => $period,
            'link' => $referrals->linkFor($user),
            'prize' => config('clipping.referrals.leaderboard_prize'),
            'viewerId' => $user->getKey(),
            'rule' => ReferrerLeaderboard::rule(),
        ]);
    }
}
