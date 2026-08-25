<?php

namespace App\Livewire;

use App\Exceptions\ParticipationRefused;
use App\Models\Campaign;
use App\Services\Clips\ParticipationService;
use Livewire\Attributes\Computed;
use Livewire\Component;

class JoinCampaign extends Component
{
    public Campaign $campaign;

    public ?int $socialAccountId = null;

    public function mount(Campaign $campaign): void
    {
        $this->campaign = $campaign;
        $this->socialAccountId = $this->accounts->first()?->getKey();
    }

    #[Computed]
    public function accounts()
    {
        return app(ParticipationService::class)->eligibleAccounts($this->campaign, auth()->user());
    }

    public function join(ParticipationService $participations): void
    {
        $account = $this->accounts->firstWhere('id', $this->socialAccountId);

        if (! $account) {
            $this->addError('socialAccountId', 'Choisissez un compte réseau.');

            return;
        }

        try {
            $participation = $participations->join($this->campaign, auth()->user(), $account);
        } catch (ParticipationRefused $exception) {
            // Le message d'exception est déjà rédigé pour l'utilisateur final :
            // le reformuler ici le ferait diverger du service.
            $this->addError('socialAccountId', $exception->getMessage());

            return;
        }

        session()->flash('status', $participation->status->value === 'approved'
            ? 'Campagne rejointe. Vous pouvez publier puis soumettre votre clip.'
            : 'Demande envoyée. Un administrateur doit valider votre participation.');

        $this->redirect(route('campaigns.show', $this->campaign), navigate: true);
    }

    public function render()
    {
        return view('livewire.join-campaign');
    }
}
