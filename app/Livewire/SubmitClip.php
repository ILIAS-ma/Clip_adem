<?php

namespace App\Livewire;

use App\Exceptions\ClipSubmissionRefused;
use App\Exceptions\ParticipationRefused;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\SocialAccount;
use App\Services\Clips\ClipPicker;
use App\Services\Clips\ClipSubmissionService;
use App\Services\Clips\ParticipationService;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Gérer ses clips : une grille de toutes ses vidéos TikTok. On coche pour
 * soumettre, on décoche un clip déjà soumis pour le retirer, on enregistre.
 * Pas de lien à copier — le chemin du téléphone, celui de presque tous les
 * clippeurs.
 */
class SubmitClip extends Component
{
    public Campaign $campaign;

    /**
     * Vidéos par compte lié, chargées à l'ouverture de la grille : interroger
     * la plateforme à chaque affichage de la campagne coûterait du quota pour
     * des visiteurs qui ne soumettent rien.
     *
     * Verrouillée : c'est elle qui fait foi au moment d'enregistrer, le
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
     * Enregistre la grille : soumet les vidéos cochées, retire celles que le
     * clippeur a décochées. Vidéo par vidéo : un refus — une vidéo prise entre
     * deux par quelqu'un d'autre, un clip déjà payé — n'empêche pas les autres.
     *
     * @param  list<string>  $add  « compte:vidéo » à soumettre
     * @param  list<string>  $remove  « compte:vidéo » à retirer
     */
    /** Ce que le clippeur peut envoyer maintenant, pour la grille ; null = sans limite. */
    public function allowance(): ?int
    {
        return app(ClipSubmissionService::class)->allowance($this->campaign, auth()->user());
    }

    protected function allowanceMessage(ClipSubmissionService $clips, int $wanted, int $allowance): string
    {
        $campaign = $this->campaign;
        $perHour = $campaign->hourlySubmissionLimit();
        $hourlyLeft = $perHour !== null ? max(0, $perHour - $clips->submittedInLastHour($campaign, auth()->user())) : null;

        if ($hourlyLeft === 0) {
            $next = $clips->nextSlotAt($campaign, auth()->user());

            return sprintf(
                'Cette campagne limite les envois à %d vidéo%s par heure. Tu pourras en envoyer une autre %s.',
                $perHour,
                $perHour > 1 ? 's' : '',
                $next ? 'à '.$next->timezone(config('app.timezone'))->format('H\hi') : 'dans moins d’une heure',
            );
        }

        if ($perHour !== null && $hourlyLeft < ($campaign->submissionBatchLimit() ?? PHP_INT_MAX)) {
            return sprintf(
                'Cette campagne limite les envois à %d vidéo%s par heure : tu peux en envoyer %d maintenant, et tu en as choisi %d.',
                $perHour,
                $perHour > 1 ? 's' : '',
                $allowance,
                $wanted,
            );
        }

        return sprintf(
            'Cette campagne limite chaque envoi à %d vidéos : tu en as choisi %d. Enregistre-en %d, puis choisis les suivantes.',
            $allowance,
            $wanted,
            $allowance,
        );
    }

    public function save(array $add, array $remove, ClipSubmissionService $clips, ClipPicker $picker, ParticipationService $participations): void
    {
        $added = 0;
        $removed = 0;
        $refusals = [];

        // Les limites de la campagne (par envoi, par heure) : refusées en bloc, sans rien enregistrer à moitié.
        $wanted = count(array_unique($add));
        $allowance = $clips->allowance($this->campaign, auth()->user());

        if ($allowance !== null && $wanted > $allowance) {
            $this->loadVideos($picker);
            $this->pickError = $this->allowanceMessage($clips, $wanted, $allowance);

            return;
        }

        foreach (['add' => array_unique($add), 'remove' => array_unique($remove)] as $action => $keys) {
            foreach ($keys as $key) {
                [$accountId, $postId] = array_pad(explode(':', (string) $key, 2), 2, '');

                $account = SocialAccount::where('user_id', auth()->id())->findOrFail((int) $accountId);

                // Retrouver la vidéo dans la liste proposée : on n'agit pas sur
                // un identifiant arbitraire envoyé par le navigateur.
                $video = collect(collect($this->groups ?? [])->firstWhere('account_id', $account->getKey())['videos'] ?? [])
                    ->firstWhere('id', $postId);

                try {
                    if ($action === 'add') {
                        if (! $video || $video['submitted']) {
                            throw new ClipSubmissionRefused('Une vidéo n’est plus disponible. Actualisez la liste.');
                        }

                        if (! ($video['eligible'] ?? true)) {
                            throw new ClipSubmissionRefused($video['ineligible_reason'] ?? 'Cette vidéo ne compte pas pour cette campagne.');
                        }

                        // Pas de demande à part : choisir une vidéo d'un compte qui n'a pas
                        // encore rejoint la campagne le fait rejoindre.
                        $participations->existing($this->campaign, $account)
                            ?? $participations->join($this->campaign, auth()->user(), $account);

                        $clips->submitPicked($this->campaign, auth()->user(), $account, $postId, $video['url'] ?? null, $video);
                        $added++;
                    } else {
                        if (! $video || ! $video['removable']) {
                            throw ClipSubmissionRefused::cannotWithdraw();
                        }

                        $clip = Clip::where('platform', $account->platform)
                            ->where('external_post_id', $postId)
                            ->where('campaign_id', $this->campaign->getKey())
                            ->where('user_id', auth()->id())
                            ->firstOrFail();

                        $clips->withdraw($clip, auth()->user());
                        $removed++;
                    }
                } catch (ClipSubmissionRefused|ParticipationRefused $exception) {
                    $refusals[] = $exception->getMessage();
                }

                $picker->forget($account);
            }
        }

        if ($added + $removed === 0) {
            $this->loadVideos($picker);
            $this->pickError = $refusals[0] ?? 'Aucun changement à enregistrer.';

            return;
        }

        $done = collect([
            $added ? ($added === 1 ? '1 clip soumis' : "{$added} clips soumis") : null,
            $removed ? ($removed === 1 ? '1 clip retiré' : "{$removed} clips retirés") : null,
        ])->filter()->implode(' · ');

        session()->flash('status', $refusals ? $done.' · '.count($refusals).' refusé(s) : '.$refusals[0] : $done.'.');

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
