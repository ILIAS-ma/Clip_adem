<?php

namespace App\Livewire;

use App\Exceptions\ClipSubmissionRefused;
use App\Models\Campaign;
use App\Models\SocialAccount;
use App\Services\Clips\ClipPicker;
use App\Services\Clips\ClipSubmissionService;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Soumettre un clip : d'un toucher dans la liste de ses vidéos récentes — le
 * chemin du téléphone —, ou en collant le lien, en secours.
 */
class SubmitClip extends Component
{
    public Campaign $campaign;

    public string $url = '';

    /**
     * Vidéos récentes par compte lié, chargées à la demande : interroger la
     * plateforme à chaque affichage de la campagne coûterait du quota pour
     * des visiteurs qui ne soumettent rien.
     *
     * Verrouillée : c'est elle qui fait foi au moment du choix, le navigateur
     * ne doit pas pouvoir y glisser une vidéo de son cru.
     *
     * @var list<array<string, mixed>>|null
     */
    #[Locked]
    public ?array $groups = null;

    public ?string $pickError = null;

    public function mount(Campaign $campaign): void
    {
        $this->campaign = $campaign;
    }

    public function loadVideos(ClipPicker $picker): void
    {
        $this->groups = $picker->forCampaign($this->campaign, auth()->user());
        $this->pickError = null;
    }

    public function pick(int $accountId, string $postId, ClipSubmissionService $clips, ClipPicker $picker): void
    {
        $account = SocialAccount::where('user_id', auth()->id())->findOrFail($accountId);

        // Retrouver la vidéo dans la liste proposée : on ne soumet pas un
        // identifiant arbitraire envoyé par le navigateur.
        $video = collect($this->groups ?? [])
            ->firstWhere('account_id', $accountId)['videos'] ?? [];
        $video = collect($video)->firstWhere('id', $postId);

        if (! $video) {
            $this->pickError = 'Cette vidéo n’est plus dans la liste. Rechargez-la et réessayez.';

            return;
        }

        try {
            $clips->submitPicked($this->campaign, auth()->user(), $account, $postId, $video['url'] ?? null);
        } catch (ClipSubmissionRefused $exception) {
            $this->pickError = $exception->getMessage();

            return;
        }

        $picker->forget($account);

        session()->flash('status', 'Clip soumis. Il sera vérifié puis validé par la modération.');

        $this->redirect(route('campaigns.show', $this->campaign), navigate: true);
    }

    public function submit(ClipSubmissionService $clips): void
    {
        $this->validate([
            'url' => ['required', 'string', 'max:2048'],
        ], [
            'url.required' => 'Collez le lien de votre publication.',
        ]);

        try {
            $clips->submit($this->campaign, auth()->user(), trim($this->url));
        } catch (ClipSubmissionRefused $exception) {
            $this->addError('url', $exception->getMessage());

            return;
        }

        $this->reset('url');

        session()->flash('status', 'Clip soumis. Il sera vérifié puis validé par la modération avant d\'être rémunéré.');

        $this->redirect(route('campaigns.show', $this->campaign), navigate: true);
    }

    public function render()
    {
        return view('livewire.submit-clip');
    }
}
