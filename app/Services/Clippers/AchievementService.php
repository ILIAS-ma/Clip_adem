<?php

namespace App\Services\Clippers;

use App\Enums\ClipStatus;
use App\Enums\PayoutStatus;
use App\Enums\Platform;
use App\Models\TrainingLesson;
use App\Models\User;
use App\Support\Clippers\Achievement;
use Illuminate\Support\Collection;

/**
 * Succès au-delà du niveau XP : le niveau récompense la carrière entière,
 * ceux-ci marquent des étapes précises (premier clip, premier retrait...)
 * qu'un chiffre de niveau seul ne raconte pas.
 *
 * Toujours recalculés depuis les mêmes tables que le reste du tableau de
 * bord — jamais un compteur dénormalisé qui pourrait diverger.
 */
class AchievementService
{
    /** @return Collection<int, Achievement> */
    public function for(User $clipper): Collection
    {
        $paidViews = (int) $clipper->clips()->sum('paid_views');
        $approvedClips = $clipper->clips()->where('status', ClipStatus::Approved)->count();
        $paidPayouts = $clipper->payouts()->where('status', PayoutStatus::Paid)->count();
        $campaignsCount = $clipper->clips()
            ->where('status', ClipStatus::Approved)
            ->distinct('campaign_id')
            ->count('campaign_id');
        $platformsLinked = $clipper->socialAccounts()->distinct()->pluck('platform')->count();

        return collect([
            new Achievement(
                key: 'first_clip',
                label: 'Premier clip',
                description: 'Soumettre un premier clip.',
                unlocked: $clipper->clips()->exists(),
            ),
            new Achievement(
                key: 'first_approved',
                label: 'Premier clip validé',
                description: 'Faire valider un premier clip par la modération.',
                unlocked: $approvedClips > 0,
            ),
            new Achievement(
                key: 'views_10k',
                label: '10 000 vues',
                description: 'Atteindre 10 000 vues rémunérées cumulées.',
                unlocked: $paidViews >= 10_000,
            ),
            new Achievement(
                key: 'views_100k',
                label: '100 000 vues',
                description: 'Atteindre 100 000 vues rémunérées cumulées.',
                unlocked: $paidViews >= 100_000,
            ),
            new Achievement(
                key: 'views_1m',
                label: '1 000 000 vues',
                description: 'Atteindre 1 000 000 de vues rémunérées cumulées.',
                unlocked: $paidViews >= 1_000_000,
            ),
            new Achievement(
                key: 'first_payout',
                label: 'Premier retrait',
                description: 'Recevoir un premier versement.',
                unlocked: $paidPayouts > 0,
            ),
            new Achievement(
                key: 'multi_platform',
                label: 'Multi-plateforme',
                description: 'Lier un compte sur TikTok, YouTube et Instagram.',
                unlocked: $platformsLinked >= count(Platform::cases()),
            ),
            new Achievement(
                key: 'loyal',
                label: 'Fidèle',
                description: 'Faire valider un clip dans 5 campagnes différentes.',
                unlocked: $campaignsCount >= 5,
            ),
            new Achievement(
                key: 'trained',
                label: 'Formé',
                description: 'Terminer toutes les leçons de la formation.',
                unlocked: $this->finishedTraining($clipper),
            ),
        ]);
    }

    /**
     * Toutes les leçons publiées, au moment présent : une leçon ajoutée plus
     * tard rouvre le succès, plutôt que de le laisser affirmer une formation
     * complète qui ne l'est plus.
     */
    protected function finishedTraining(User $clipper): bool
    {
        $published = TrainingLesson::query()->published()->pluck('id');

        return $published->isNotEmpty()
            && $clipper->completedLessons()->whereIn('training_lessons.id', $published)->count() === $published->count();
    }
}
