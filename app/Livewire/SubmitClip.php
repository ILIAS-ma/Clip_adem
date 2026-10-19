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

    /** Combien de vidéos montrer par compte ; « Voir plus » en ajoute. */
    #[Locked]
    public int $want = ClipPicker::PER_PAGE;

    /** Vidéos soumises depuis l'ouverture de la liste, pour le message de réussite. */
    public int $pickedCount = 0;

    public function loadVideos(ClipPicker $picker): void
    {
        $this->groups = $picker->forCampaign($this->campaign, auth()->user(), $this->want);
        $this->pickError = null;
    }

    public function loadMore(ClipPicker $picker): void
    {
        $this->want += ClipPicker::PER_PAGE;
        $this->loadVideos($picker);
    }

    /** « Actualiser » : relire TikTok, pour une vidéo qu'on vient de publier. */
    public function refreshVideos(ClipPicker $picker): void
    {
        foreach ($this->groups ?? [] as $group) {
            $account = SocialAccount::where('user_id', auth()->id())->find($group['account_id']);

            if ($account) {
                $picker->forget($account);
            }
        }

        $this->loadVideos($picker);
    }

    public function pick(int $accountId, string $postId, ClipSubmissionService $clips): void
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

        /*
         * On reste sur la liste, sans recharger la page : un clippeur qui
         * rattrape vingt vidéos les soumet à la suite, d'un toucher chacune,
         * au lieu de rouvrir la liste et de la faire défiler à chaque fois.
         */
        $this->groups = array_map(function (array $group) use ($accountId, $postId) {
            if ($group['account_id'] === $accountId) {
                $group['videos'] = array_values(array_filter($group['videos'], fn (array $v) => $v['id'] !== $postId));
            }

            return $group;
        }, $this->groups);

        $this->pickedCount++;
        $this->pickError = null;
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

        session()->flash('status', 'Clip soumis : ses vues comptent dès le prochain relevé.');

        $this->redirect(route('campaigns.show', $this->campaign), navigate: true);
    }

    public function render()
    {
        return view('livewire.submit-clip');
    }
}
