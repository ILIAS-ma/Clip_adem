<?php

namespace App\Http\Controllers\Clipper;

use App\Enums\LegalStatus;
use App\Http\Controllers\Controller;
use App\Rules\Siret;
use App\Services\Payouts\LegalStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Le clippeur déclare son statut : particulier, ou SIRET à faire vérifier. */
class LegalStatusController extends Controller
{
    public function update(Request $request, LegalStatusService $service): RedirectResponse
    {
        $data = $request->validateWithBag('legalStatus', [
            'legal_status' => ['required', Rule::enum(LegalStatus::class)],
            'siret' => ['nullable', 'required_if:legal_status,'.LegalStatus::Business->value, new Siret],
        ], [
            'siret.required_if' => 'Indiquez votre numéro SIRET.',
        ]);

        $status = LegalStatus::from($data['legal_status']);
        $user = $service->declare($request->user(), $status, $data['siret'] ?? null);

        return redirect()
            ->route('payout-method.edit')
            ->with('status', $status === LegalStatus::Business && $user->hasPendingSiret()
                ? 'SIRET enregistré. L’équipe le vérifie au registre, vous serez prévenu.'
                : 'Statut légal enregistré.');
    }
}
