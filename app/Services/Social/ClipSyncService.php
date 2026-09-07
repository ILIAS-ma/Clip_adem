<?php

namespace App\Services\Social;

use App\Contracts\CampaignBudgetService;
use App\Enums\CampaignStatus;
use App\Enums\ClipStatus;
use App\Enums\ModerationAction;
use App\Enums\Platform;
use App\Models\BudgetTransaction;
use App\Models\Clip;
use App\Models\ClipViewSnapshot;
use App\Models\ModerationLog;
use App\Models\SocialSyncRun;
use App\Services\Clips\ClipComplianceChecker;
use App\Support\Social\PostMetrics;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Relève les vues des clips et les fait créditer par le moteur de budget.
 *
 * Deux règles structurent tout le reste :
 *
 *  - aucun appel réseau pendant une transaction. creditViews() pose un verrou
 *    sur la ligne campagne ; le tenir pendant un appel TikTok bloquerait tous
 *    les crédits de cette campagne. Les API sont donc interrogées d'abord, la
 *    base écrite ensuite.
 *  - un snapshot par relevé, et sa clé d'idempotence en découle. Deux
 *    exécutions du même passage ne peuvent pas payer deux fois les mêmes vues.
 */
class ClipSyncService
{
    public function __construct(
        protected SocialProviderManager $providers,
        protected CampaignBudgetService $budget,
        protected ClipComplianceChecker $compliance,
    ) {}

    /**
     * Synchronise une plateforme et renvoie le journal du passage.
     *
     * @param  int|null  $limit  Nombre maximum de clips, pour borner un passage manuel.
     */
    public function syncPlatform(Platform $platform, ?int $limit = null): SocialSyncRun
    {
        $provider = $this->providers->for($platform);

        // Compteurs posés explicitement : les valeurs par défaut vivent en base,
        // et le modèle fraîchement créé les ignorerait.
        $run = SocialSyncRun::create([
            'platform' => $platform,
            'started_at' => now(),
            'clips_synced' => 0,
            'api_calls' => 0,
            'quota_used' => 0,
            'rate_limited' => false,
        ]);

        $clips = $this->dueClips($platform, $limit);

        if ($clips->isEmpty()) {
            $run->forceFill(['finished_at' => now()])->save();

            return $run;
        }

        $quotaRemaining = $this->remainingQuota($platform, $provider->dailyQuota());
        $calls = 0;
        $quotaUsed = 0;
        $synced = 0;
        $rateLimited = false;
        $error = null;

        foreach ($clips->groupBy('social_account_id') as $accountClips) {
            $account = $accountClips->first()->socialAccount;

            // Interroger un compte à reconnecter ne rapporte que des 401 et
            // consomme le quota qui manquera aux comptes valides.
            if (! $account || ! $account->isSyncable()) {
                continue;
            }

            foreach ($accountClips->chunk($provider->batchSize()) as $batch) {
                $cost = $provider->quotaCostPerCall();

                if ($quotaRemaining !== null && $quotaRemaining < $cost) {
                    $rateLimited = true;
                    break 2;
                }

                try {
                    $metrics = $provider->fetchPosts($account, $batch->pluck('external_post_id')->all());
                } catch (\Throwable $exception) {
                    $error = $exception->getMessage();
                    $rateLimited = str_contains($error, '429');

                    Log::warning('Synchronisation interrompue', [
                        'platform' => $platform->value,
                        'social_account_id' => $account->getKey(),
                        'error' => $error,
                    ]);

                    break 2;
                }

                $calls++;
                $quotaUsed += $cost;

                if ($quotaRemaining !== null) {
                    $quotaRemaining -= $cost;
                }

                $synced += $this->applyMetrics($batch, $metrics);
            }
        }

        $run->forceFill([
            'finished_at' => now(),
            'clips_synced' => $synced,
            'api_calls' => $calls,
            'quota_used' => $quotaUsed,
            'rate_limited' => $rateLimited,
            'error' => $error,
        ])->save();

        return $run;
    }

    /**
     * Écrit les relevés et déclenche le crédit.
     *
     * @param  Collection<int, Clip>  $clips
     * @param  Collection<string, PostMetrics>  $metrics
     */
    /**
     * La plateforme ne renvoie plus cette publication.
     *
     * On ne touche JAMAIS aux vues ni aux gains déjà acquis : le budget a été
     * consommé, et seule une invalidation par un modérateur peut le rendre. Ce
     * qu'on fait ici, c'est rendre la disparition visible — parce que sans ça,
     * publier, encaisser sur trois jours puis effacer ne laissait aucune trace.
     *
     * Le journal n'est écrit qu'une fois, au franchissement du seuil : un
     * relevé horaire produirait sinon vingt-quatre lignes par jour pour la même
     * publication, et la file de modération deviendrait illisible.
     */
    /**
     * Relève un clip précis, à la demande.
     *
     * TikTok ne pousse rien : il n'existe aucun webhook sur le compteur de
     * vues, tout se fait en interrogeant. Entre deux passages automatiques, un
     * clippeur qui veut voir où il en est n'a donc que ce chemin — et le lui
     * refuser le pousserait à recharger la page toutes les trente secondes en
     * pensant que la plateforme est cassée.
     *
     * Le délai de garde est ce qui empêche ce bouton de devenir une attaque
     * sur notre propre quota d'API.
     *
     * @return bool Faux si le clip vient d'être relevé, ou n'est pas relevable.
     */
    public function syncClip(Clip $clip): bool
    {
        return $this->syncClipWithMetrics($clip) !== null;
    }

    /**
     * Identique à syncClip(), mais renvoie le relevé brut plutôt qu'un simple
     * booléen.
     *
     * Sert à l'affichage détaillé (likes, commentaires, partages) : ces
     * chiffres ne sont pas persistés sur le clip — seuls views_total et les
     * champs de conformité le sont — donc syncClip() seul ne permet pas de
     * les lire après coup.
     *
     * @return PostMetrics|null Null pour les mêmes raisons que syncClip()
     *                          renvoie faux : délai de garde actif, compte non
     *                          synchronisable, échec du fournisseur, ou
     *                          publication introuvable côté plateforme.
     */
    public function syncClipWithMetrics(Clip $clip): ?PostMetrics
    {
        $cooldown = (int) config('clipping.sync.manual_cooldown_minutes');

        if ($clip->last_synced_at && $clip->last_synced_at->gt(now()->subMinutes($cooldown))) {
            return null;
        }

        $account = $clip->socialAccount;

        if (! $account || ! $account->isSyncable()) {
            return null;
        }

        $provider = $this->providers->for($clip->platform);

        try {
            $metrics = $provider->fetchPosts($account, [$clip->external_post_id]);
        } catch (\Throwable $exception) {
            Log::warning('Relevé manuel impossible', [
                'clip_id' => $clip->getKey(),
                'error' => $exception->getMessage(),
            ]);

            return null;
        }

        // Le même chemin que le relevé automatique : conformité, instantané,
        // crédit du budget. Deux chemins d'écriture pour la même chose
        // finiraient par diverger, et c'est de l'argent qui passe ici.
        return $this->applyMetrics(collect([$clip]), $metrics) > 0
            ? $metrics->get($clip->external_post_id)
            : null;
    }

    /**
     * Signale une publication qui n'appartient pas au compte lié.
     *
     * Une seule ligne de journal, au premier constat : le relevé repasse
     * toutes les trois heures, et vingt-quatre lignes par jour pour le même
     * clip rendraient la file de modération inutilisable.
     */
    protected function flagStolenPost(Clip $clip): void
    {
        $already = ModerationLog::where('subject_type', $clip->getMorphClass())
            ->where('subject_id', $clip->getKey())
            ->where('action', ModerationAction::ClipNotOwned)
            ->exists();

        if ($already) {
            return;
        }

        ModerationLog::record(
            ModerationAction::ClipNotOwned,
            $clip,
            null,
            'Publication émise par un autre compte que celui lié : aucun crédit versé.',
        );

        Log::warning('Publication non détenue par le clippeur', [
            'clip_id' => $clip->getKey(),
            'user_id' => $clip->user_id,
        ]);
    }

    protected function noteMissingPost(Clip $clip): void
    {
        $wasFlagged = $clip->hasDisappeared();

        $clip->forceFill([
            'last_synced_at' => now(),
            'missing_since' => $clip->missing_since ?? now(),
            'missing_checks' => $clip->missing_checks + 1,
        ])->save();

        if ($wasFlagged || ! $clip->hasDisappeared()) {
            return;
        }

        ModerationLog::record(
            ModerationAction::ClipDisappeared,
            $clip,
            null,
            $clip->earned_cents > 0
                ? sprintf(
                    'Publication introuvable depuis %d relevés, après %s € déjà crédités.',
                    $clip->missing_checks,
                    number_format($clip->earned_cents / 100, 2, ',', ' '),
                )
                : sprintf('Publication introuvable depuis %d relevés.', $clip->missing_checks),
        );

        Log::warning('Publication disparue de la plateforme', [
            'clip_id' => $clip->getKey(),
            'platform' => $clip->platform->value,
            'earned_cents' => $clip->earned_cents,
        ]);
    }

    protected function applyMetrics(Collection $clips, Collection $metrics): int
    {
        $synced = 0;

        foreach ($clips as $clip) {
            $post = $metrics->get($clip->external_post_id);

            if (! $post) {
                $this->noteMissingPost($clip);

                continue;
            }

            // La publication est de retour : elle était privée un moment, ou
            // l'API a bafouillé. On efface l'alerte plutôt que de laisser un
            // soupçon s'installer sur quelqu'un qui n'a rien fait.
            if ($clip->isMissing()) {
                $clip->forceFill(['missing_since' => null, 'missing_checks' => 0])->save();
            }

            // Le compteur des plateformes descend régulièrement : on écrit la
            // valeur telle quelle, le moteur de budget refuse de son côté tout
            // crédit négatif.
            $snapshot = ClipViewSnapshot::create([
                'clip_id' => $clip->getKey(),
                'views' => $post->views,
                'source' => 'api',
                'captured_at' => now(),
            ]);

            $clip->forceFill([
                'views_total' => $post->views,
                'last_synced_at' => now(),
            ])->save();

            // Premier relevé : c'est le moment où l'on connaît enfin la légende
            // et la durée réelles, donc où la conformité devient vérifiable.
            if ($clip->compliance_status === ClipComplianceChecker::PENDING || $clip->compliance_status === null) {
                $this->compliance->check($clip, $post);
            }

            /*
             * La publication appartient à quelqu'un d'autre : on ne crédite
             * pas, et on n'attend pas un modérateur pour ça.
             *
             * Les autres contrôles relèvent du jugement — un hashtag oublié se
             * discute — et laissent donc le crédit suivre son cours, quitte à
             * être repris par une invalidation. Celui-ci est factuel et
             * binaire, et se tromper signifie payer un clippeur pour la vidéo
             * virale d'un inconnu. La fenêtre existait vraiment : le contrôle
             * tournait juste avant, et le crédit partait quand même deux
             * lignes plus bas.
             */
            if (ClipComplianceChecker::ownershipFailed($clip->refresh())) {
                $this->flagStolenPost($clip);

                $synced++;

                continue;
            }

            $this->budget->creditViews(
                $clip,
                $post->views,
                BudgetTransaction::snapshotKey($clip->getKey(), $snapshot->getKey()),
            );

            $synced++;
        }

        return $synced;
    }

    /**
     * Clips à relever maintenant, cadence dégressive appliquée.
     *
     * @return Collection<int, Clip>
     */
    public function dueClips(Platform $platform, ?int $limit = null): Collection
    {
        $config = config('clipping.sync');
        $now = now();

        return Clip::query()
            ->with(['socialAccount', 'campaign'])
            ->where('platform', $platform)
            ->whereIn('status', [ClipStatus::Approved, ClipStatus::PendingReview])
            ->whereHas('campaign', fn ($q) => $q->whereNotIn('status', [
                CampaignStatus::Draft,
                CampaignStatus::Archived,
            ]))
            // Passé un mois, une publication ne bouge plus assez pour justifier
            // un appel d'API.
            ->where(function ($q) use ($config, $now) {
                $q->whereNull('posted_at')
                    ->orWhere('posted_at', '>=', $now->copy()->subDays($config['stop_after_days']));
            })
            ->get()
            ->filter(fn (Clip $clip) => $this->isDue($clip, $now))
            ->when($limit, fn (Collection $c) => $c->take($limit))
            ->values();
    }

    protected function isDue(Clip $clip, CarbonInterface $now): bool
    {
        if (! $clip->last_synced_at) {
            return true;
        }

        $config = config('clipping.sync');
        $reference = $clip->posted_at ?? $clip->submitted_at ?? $clip->created_at;
        $ageHours = $reference->diffInHours($now);

        $interval = match (true) {
            // Un clip qui ne rapporte plus rien n'a plus besoin d'être suivi de
            // près : ses vues restent comptées, simplement moins souvent.
            ! $this->isPayable($clip) => $config['unpayable_interval_hours'],
            $ageHours <= $config['fresh_window_hours'] => $config['fresh_interval_hours'],
            default => $config['mature_interval_hours'],
        };

        return $clip->last_synced_at->addHours($interval)->lte($now);
    }

    protected function isPayable(Clip $clip): bool
    {
        return $clip->status === ClipStatus::Approved
            && $clip->campaign
            && $clip->campaign->acceptsCredits();
    }

    /** Quota encore disponible aujourd'hui, ou null si la plateforme n'en publie pas. */
    protected function remainingQuota(Platform $platform, ?int $dailyQuota): ?int
    {
        if ($dailyQuota === null) {
            return null;
        }

        return max(0, $dailyQuota - SocialSyncRun::quotaUsedToday($platform));
    }
}
