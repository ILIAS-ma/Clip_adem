<?php

namespace App\Livewire;

use App\Enums\PayoutStatus;
use App\Exceptions\PayoutRefused;
use App\Services\Payouts\PayoutService;
use Livewire\Component;

class RequestPayout extends Component
{
    /** Montant saisi en euros ; converti en centimes au dernier moment. */
    public string $amount = '';

    public function mount(): void
    {
        $available = auth()->user()->availableBalanceCents();

        if ($available > 0) {
            $this->amount = number_format($available / 100, 2, '.', '');
        }
    }

    public function request(PayoutService $payouts): void
    {
        $this->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
        ], [
            'amount.required' => 'Indiquez un montant.',
            'amount.numeric' => 'Le montant doit être un nombre.',
        ]);

        try {
            $payout = $payouts->request(auth()->user(), (int) round((float) $this->amount * 100));
        } catch (PayoutRefused $exception) {
            // Le message du service est déjà rédigé pour l'utilisateur final.
            $this->addError('amount', $exception->getMessage());

            return;
        }

        session()->flash('status', $payout->status === PayoutStatus::Approved
            ? 'Retrait validé automatiquement. Il partira au prochain envoi de lot PayPal.'
            : 'Demande enregistrée. Un administrateur doit la valider avant le virement.');

        $this->redirect(route('earnings.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.request-payout', [
            'available' => auth()->user()->availableBalanceCents(),
            'minimum' => config('clipping.payouts.minimum_cents'),
        ]);
    }
}
