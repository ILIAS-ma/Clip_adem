<?php

namespace App\Services\Clips;

use App\Contracts\CampaignBudgetService;
use App\Enums\ClipStatus;
use App\Enums\ParticipationStatus;
use App\Enums\Platform;
use App\Exceptions\ClipSubmissionRefused;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Moderation\ClipModerationService;
use App\Support\Clips\ClipUrl;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Soumission d'un lien de clip par un clippeur.
 *
 * Le clip naît validé : il n'y a pas de file de modération, tout clip soumis
 * compte dès son premier relevé. Le garde-fou qui reste est automatique — une
 * vidéo qui n'appartient pas au compte lié est écartée au premier relevé
 * (ClipSyncService::flagStolenPost) —, et l'admin peut toujours invalider.
 */
class ClipSubmissionService
{
    public function __construct(
        protected ClipUrlParser $parser,
        protected CampaignBudgetService $budget,
    ) {}

    /**
     * @throws ClipSubmissionRefused
     */
    public function submit(Campaign $campaign, User $clipper, string $url): Clip
    {
        return $this->submitParsed($campaign, $clipper, $this->parser->parse($url));
    }

    /**
     * Soumission d'une vidéo choisie au doigt dans la liste des publications
     * récentes du compte lié — le chemin du téléphone, sans lien à copier.
     *
     * La vidéo vient de la liste de CE compte : la participation est donc
     * cherchée sur ce compte précisément, et le contrôle de pseudo n'a rien à
     * vérifier. Les autres règles (campagne ouverte, doublons) restent celles
     * d'un lien collé.
     */
    public function submitPicked(Campaign $campaign, User $clipper, SocialAccount $account, string $externalPostId, ?string $url = null, array $details = []): Clip
    {
        abort_unless($account->user_id === $clipper->getKey(), 403);

        // L'adresse de partage porte le vrai nom d'utilisateur : on la lit si
        // possible, sinon on la reconstruit depuis l'identifiant.
        $parsed = null;

        if ($url) {
            try {
                $parsed = $this->parser->parse($url);
            } catch (ClipSubmissionRefused) {
                $parsed = null;
            }
        }

        if (! $parsed || $parsed->externalPostId !== $externalPostId) {
            $handle = static::looksLikeUsername((string) $account->handle) ? ltrim($account->handle, '@') : 'user';
            $parsed = new ClipUrl(
                $account->platform,
                $externalPostId,
                match ($account->platform) {
                    Platform::TikTok => "https://www.tiktok.com/@{$handle}/video/{$externalPostId}",
                    Platform::YouTube => "https://www.youtube.com/shorts/{$externalPostId}",
                    Platform::Instagram => "https://www.instagram.com/reel/{$externalPostId}/",
                },
            );
        }

        // La miniature, la légende et la date sont déjà là : les garder évite une
        // vignette vide dans « Mes clips » jusqu'au premier relevé.
        $details = array_filter([
            'thumbnail_url' => $details['thumbnail'] ?? null,
            'caption' => isset($details['caption']) ? mb_substr((string) $details['caption'], 0, 2000) : null,
            'posted_at' => $details['posted_at'] ?? null,
        ]);

        return $this->submitParsed($campaign, $clipper, new ClipUrl($parsed->platform, $parsed->externalPostId, $parsed->canonicalUrl), $account, $details);
    }

    protected function submitParsed(Campaign $campaign, User $clipper, ClipUrl $parsed, ?SocialAccount $onlyAccount = null, array $details = []): Clip
    {
        if (! $this->budget->acceptsNewClips($campaign)) {
            throw ClipSubmissionRefused::campaignClosed();
        }

        /*
         * Un clippeur peut avoir plusieurs participations sur une même
         * campagne — une par compte lié, et il en relie de nouveaux avec le
         * temps. Prendre la première venue rattache le clip à un compte qui
         * n'est peut-être plus utilisable, et le relevé des vues échoue alors
         * pour une raison qui n'a rien à voir avec la vidéo.
         *
         * On préfère donc un compte réellement interrogeable, et le plus
         * récemment lié à égalité : c'est celui depuis lequel on vient
         * vraisemblablement de publier.
         */
        $participation = $campaign->participations()
            ->where('user_id', $clipper->getKey())
            ->whereIn('status', [ParticipationStatus::Pending, ParticipationStatus::Approved])
            ->with('socialAccount')
            ->get()
            ->filter(fn ($p) => $p->socialAccount?->platform === $parsed->platform)
            ->filter(fn ($p) => $onlyAccount === null || $p->social_account_id === $onlyAccount->getKey())
            ->sortByDesc(fn ($p) => [
                $p->socialAccount->isSyncable() ? 1 : 0,
                $p->socialAccount->getKey(),
            ])
            ->first();

        if (! $participation) {
            // Soit il n'a pas rejoint, soit il a rejoint avec un compte d'une
            // autre plateforme : le message doit distinguer les deux.
            $anyParticipation = $campaign->participations()
                ->where('user_id', $clipper->getKey())
                ->with('socialAccount')
                ->first();

            throw $anyParticipation && $anyParticipation->socialAccount
                ? ClipSubmissionRefused::platformMismatch($parsed->platform, $anyParticipation->socialAccount->platform)
                : ClipSubmissionRefused::noParticipation();
        }

        // Contrôle immédiat, sans appel réseau : si l'URL porte un pseudo et
        // qu'il ne correspond pas à celui du compte lié, la vidéo n'est
        // probablement pas la sienne. Un second contrôle, plus fiable mais
        // différé au premier relevé de vues, compare l'identifiant API du
        // propriétaire (ClipComplianceChecker::checkOwnership) — celui-ci
        // n'est qu'un premier filtre, pas remplacé par lui.
        $accountHandle = $participation->socialAccount->handle;

        /*
         * On ne compare que deux identifiants de même nature.
         *
         * L'URL porte un nom d'utilisateur — « coolerkek0 ». Selon la portée
         * accordée, la plateforme ne nous rend parfois que le nom affiché —
         * « zebi land » — qui est un libellé libre, avec espaces et accents.
         * Les confondre refusait la vidéo de son propre auteur en lui
         * affirmant qu'elle venait d'un autre compte : le message accusait, et
         * il avait tort.
         *
         * Quand la valeur stockée ne peut pas être un nom d'utilisateur, ce
         * filtre n'a rien de comparable et se tait. La vérification qui compte
         * reste entière : `ClipComplianceChecker::checkOwnership` compare
         * l'identifiant renvoyé par l'API au premier relevé de vues, et lui ne
         * se trompe pas.
         */
        if ($parsed->handle && $accountHandle && static::looksLikeUsername($accountHandle)
            && strcasecmp(ltrim($parsed->handle, '@'), ltrim($accountHandle, '@')) !== 0) {
            throw ClipSubmissionRefused::handleMismatch($parsed->handle, $accountHandle);
        }

        if ($participation->status !== ParticipationStatus::Approved) {
            throw ClipSubmissionRefused::participationNotApproved();
        }

        try {
            // Rechargé après insertion : `paid_views` et `earned_cents` ne sont
            // pas assignables — seul le moteur de budget les écrit — donc le
            // modèle en mémoire ignorerait leurs valeurs par défaut.
            $clip = DB::transaction(fn () => Clip::create([
                'campaign_id' => $campaign->getKey(),
                'participation_id' => $participation->getKey(),
                'user_id' => $clipper->getKey(),
                'social_account_id' => $participation->social_account_id,
                'platform' => $parsed->platform,
                'external_post_id' => $parsed->externalPostId,
                'url' => $parsed->canonicalUrl,
                'submitted_at' => now(),
                'status' => ClipStatus::Approved,
                'compliance_status' => 'pending',
                'views_total' => 0,
                ...$details,
            ])->refresh());
        } catch (QueryException $exception) {
            // L'unicité (platform, external_post_id) couvre aussi le cas où un
            // autre clippeur a déjà soumis le même post.
            $existant = Clip::where('platform', $parsed->platform)
                ->where('external_post_id', $parsed->externalPostId)
                ->first();

            if ($existant) {
                throw $existant->user_id === $clipper->getKey()
                    ? ClipSubmissionRefused::alreadySubmittedByYou()
                    : ClipSubmissionRefused::alreadySubmittedBySomeoneElse();
            }

            throw $exception;
        }

        // Hors transaction, comme une validation manuelle : trace dans le
        // journal de modération et prime de lancement, qui ne doit jamais
        // faire échouer la soumission.
        return app(ClipModerationService::class)->approve($clip);
    }

    /**
     * La valeur stockée peut-elle être un nom d'utilisateur ?
     *
     * Les plateformes limitent les noms d'utilisateur aux lettres, chiffres,
     * points, tirets et underscores. Un nom affiché contient au contraire des
     * espaces, des accents, parfois des émojis. La distinction n'est pas
     * cosmétique : c'est elle qui décide si l'on peut refuser une vidéo à
     * quelqu'un en lui disant qu'elle n'est pas la sienne.
     */
    protected static function looksLikeUsername(string $handle): bool
    {
        return (bool) preg_match('/^@?[A-Za-z0-9._-]+$/', $handle);
    }
}
