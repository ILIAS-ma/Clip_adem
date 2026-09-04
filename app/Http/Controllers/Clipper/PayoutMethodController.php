<?php

namespace App\Http\Controllers\Clipper;

use App\Http\Controllers\Controller;
use App\Http\Requests\PayoutMethodRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Une page, un formulaire : « où voulez-vous être payé ».
 *
 * Le moyen de paiement est sorti du profil général parce que c'est la seule
 * information qu'un clippeur revient modifier — changer de banque, passer de
 * PayPal au virement — et qu'elle ne doit jamais se perdre au milieu du pseudo
 * et du pays.
 */
class PayoutMethodController extends Controller
{
    public function edit(Request $request): View
    {
        return view('clipper.payout-method', [
            'user' => $request->user(),
        ]);
    }

    public function update(PayoutMethodRequest $request): RedirectResponse
    {
        $request->user()->forceFill($request->payoutAttributes())->save();

        return redirect()
            ->route('payout-method.edit')
            ->with('status', 'Moyen de paiement enregistré.');
    }
}
