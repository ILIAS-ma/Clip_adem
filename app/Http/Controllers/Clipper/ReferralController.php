<?php

namespace App\Http\Controllers\Clipper;

use App\Http\Controllers\Controller;
use App\Models\ReferralCommission;
use App\Services\Referrals\ReferralService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReferralController extends Controller
{
    public function __invoke(Request $request, ReferralService $referrals): View
    {
        $user = $request->user();

        $filleuls = $user->referrals()
            ->withSum('clips as clip_earnings', 'earned_cents')
            ->orderByDesc('referred_at')
            ->get();

        return view('clipper.referrals', [
            'user' => $user,
            'code' => $referrals->codeFor($user),
            'link' => $referrals->linkFor($user),
            'ratePercent' => config('clipping.referrals.rate_bp') / 100,

            'filleuls' => $filleuls,
            'earnedCents' => $user->referralEarnedCents(),

            // Ce que chaque filleul a rapporté, pour que le classement ne soit
            // pas qu'une liste de noms.
            'perReferred' => ReferralCommission::where('referrer_id', $user->getKey())
                ->selectRaw('referred_id, SUM(amount_cents) as cents')
                ->groupBy('referred_id')
                ->pluck('cents', 'referred_id'),

            'commissions' => ReferralCommission::where('referrer_id', $user->getKey())
                ->with('referred')
                ->latest('id')
                ->limit(30)
                ->get(),
        ]);
    }
}
