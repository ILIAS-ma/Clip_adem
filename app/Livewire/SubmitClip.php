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
 * Soumettre ses clips : une grille de toutes ses vidéos TikTok, on en coche
 * une ou plusieurs, on valide. Pas de lien à copier — le chemin du téléphone,
 * celui de presque tous les clippeurs.
 */
class SubmitClip extends Component
{
    public Campaign $campaign;

    /**
     * Vidéos par compte lié, chargées à l'ouverture de la grille : interroger
     * la plateforme à chaque affichage de la campagne coûterait du quota pour
     * des visiteurs qui ne soumettent rien.
     *
     * Verrouillée : c'est elle qui fait foi au moment de soumettre, le
     * navigateur ne doit pas pouvoir y glisser une vidéo de son cru.
     *
     * @var list<array<string, mixed>>|null
     */
    #[Locked]
    public ?array $groups = null;

    /** Combien de vidéos montrer par compte ; le défilement en ajoute. */
    #[Locked]
    public int $want = ClipPicker::PER_PAGE;

    public ?string $pickError = null;

    public function mount(Campaign $campaign): void
    {
        $this->campaign = $campaign;
    }

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

    /**
     * Soumet la sélection, vidéo par vidéo : un refus — une vidéo prise entre
     * deux par quelqu'un d'autre — n'empêche pas les autres de passer.
     *
     * @param  list<string>  $keys  « compte:vidéo »
     */
    public function submitSelected(array $keys, ClipSubmissionService $clips, ClipPicker $picker): void
    {
        $submitted = 0;
        $refusals = [];

        foreach (array_unique($keys) as $key) {
            [$accountId, $postId] = array_pad(explode(':', (string) $key, 2), 2, '');

            $account = SocialAccount::where('user_id', auth()->id())->findOrFail((int) $accountId);

            // Retrouver la vidéo dans la liste proposée : on ne soumet pas un
            // identifiant arbitraire envoyé par le navigateur.
            $video = collect(collect($this->groups ?? [])->firstWhere('account_id', $account->getKey())['videos'] ?? [])
                ->firstWhere('id', $postId);

            if (! $video || $video['submitted']) {
                $refusals[] = 'Une vidéo n’est plus disponible. Actualisez la liste.';

                continue;
            }

            try {
                $clips->submitPicked($this->campaign, auth()->user(), $account, $postId, $video['url'] ?? null, $video);
                $submitted++;
            } catch (ClipSubmissionRefused $exception) {
                $refusals[] = $exception->getMessage();
            }

            $picker->forget($account);
        }

        if ($submitted === 0) {
            $this->loadVideos($picker);
            $this->pickError = $refusals[0] ?? 'Sélectionnez au moins une vidéo.';

            return;
        }

        session()->flash('status', $submitted === 1
            ? 'Clip soumis : ses vues comptent dès le prochain relevé.'
            : "{$submitted} clips soumis : leurs vues comptent dès le prochain relevé.");

        // Recharger la page entière, sur l'onglet « Mes clips » : une simple
        // ancre vers la même adresse ne ferait que défiler, sans rien rafraîchir.
        session()->flash('campaign_tab', 'clips');

        $this->redirect(route('campaigns.show', $this->campaign));
    }

    public function render()
    {
        return view('livewire.submit-clip');
    }
}
