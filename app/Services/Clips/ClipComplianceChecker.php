<?php

namespace App\Services\Clips;

use App\Models\Campaign;
use App\Models\Clip;
use App\Support\Social\PostMetrics;

/**
 * Confronte une publication aux exigences de la campagne.
 *
 * Produit un rapport, jamais une décision : un clip conforme reste en attente
 * de modération, parce qu'un hashtag correct ne dit rien du respect réel du
 * brief. À l'inverse, un clip non conforme arrive devant le modérateur avec
 * ses motifs, prêt à être refusé en un clic.
 *
 * Le résultat est figé sur le clip et non recalculé à l'affichage : la
 * modération doit voir ce qui était vrai au moment des faits, pas ce que la
 * légende est devenue depuis.
 */
class ClipComplianceChecker
{
    public const PASSED = 'passed';

    public const FAILED = 'failed';

    public const PENDING = 'pending';

    public function check(Clip $clip, PostMetrics $metrics): Clip
    {
        $campaign = $clip->campaign;
        $checks = [];

        $checks[] = $this->checkOwnership($clip, $metrics);
        $checks[] = $this->checkHashtags($campaign, $metrics);
        $checks[] = $this->checkDuration($campaign, $metrics);
        $checks[] = $this->checkWindow($campaign, $metrics);

        $checks = array_values(array_filter($checks));
        $failed = collect($checks)->contains(fn (array $check) => $check['passed'] === false);

        $clip->forceFill([
            'caption' => $metrics->caption ?? $clip->caption,
            'duration_seconds' => $metrics->durationSeconds ?? $clip->duration_seconds,
            'posted_at' => $metrics->postedAt ?? $clip->posted_at,
            'compliance_status' => $failed ? self::FAILED : self::PASSED,
            'compliance' => [
                'checked_at' => now()->toIso8601String(),
                'checks' => $checks,
            ],
        ])->save();

        return $clip;
    }

    /** @return array{label: string, passed: bool, detail: ?string}|null */
    protected function checkOwnership(Clip $clip, PostMetrics $metrics): ?array
    {
        $expected = $clip->socialAccount?->external_account_id;

        if (! $expected || ! $metrics->ownerExternalId) {
            return null;
        }

        return [
            'label' => 'Publication émise par le compte lié',
            'passed' => $metrics->ownerExternalId === $expected,
            'detail' => $metrics->ownerExternalId === $expected
                ? null
                : 'La publication appartient à un autre compte que celui utilisé pour rejoindre la campagne.',
        ];
    }

    /** @return array{label: string, passed: bool, detail: ?string}|null */
    protected function checkHashtags(?Campaign $campaign, PostMetrics $metrics): ?array
    {
        $required = $campaign?->required_hashtags ?? [];

        if ($required === []) {
            return null;
        }

        $caption = mb_strtolower((string) $metrics->caption);

        $missing = array_values(array_filter(
            $required,
            fn (string $tag) => ! str_contains($caption, mb_strtolower($tag)),
        ));

        return [
            'label' => 'Hashtags obligatoires présents',
            'passed' => $missing === [],
            'detail' => $missing === [] ? null : 'Manquant : '.implode(', ', $missing),
        ];
    }

    /** @return array{label: string, passed: bool, detail: ?string}|null */
    protected function checkDuration(?Campaign $campaign, PostMetrics $metrics): ?array
    {
        $minimum = config('clipping.compliance.min_duration_seconds');

        if (! $minimum || $metrics->durationSeconds === null) {
            return null;
        }

        return [
            'label' => "Durée d'au moins {$minimum} secondes",
            'passed' => $metrics->durationSeconds >= $minimum,
            'detail' => $metrics->durationSeconds >= $minimum
                ? null
                : "Durée relevée : {$metrics->durationSeconds} s.",
        ];
    }

    /** @return array{label: string, passed: bool, detail: ?string}|null */
    protected function checkWindow(?Campaign $campaign, PostMetrics $metrics): ?array
    {
        if (! $campaign || ! $metrics->postedAt) {
            return null;
        }

        $tooEarly = $campaign->starts_at && $metrics->postedAt->lt($campaign->starts_at);
        $tooLate = $campaign->ends_at && $metrics->postedAt->gt($campaign->ends_at);

        return [
            'label' => 'Publié pendant la campagne',
            'passed' => ! $tooEarly && ! $tooLate,
            'detail' => match (true) {
                $tooEarly => 'Publié avant le début de la campagne, le '.$metrics->postedAt->format('d/m/Y').'.',
                $tooLate => 'Publié après la fin de la campagne, le '.$metrics->postedAt->format('d/m/Y').'.',
                default => null,
            },
        ];
    }
}
