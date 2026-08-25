<?php

namespace Database\Seeders;

use App\Contracts\CampaignBudgetService;
use App\Enums\CampaignStatus;
use App\Enums\ClipStatus;
use App\Enums\PayoutStatus;
use App\Enums\Platform;
use App\Enums\UserRole;
use App\Models\Artist;
use App\Models\BudgetTransaction;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\ClipViewSnapshot;
use App\Models\Payout;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@clip-adem.test'],
            [
                'name' => 'Super administrateur',
                'password' => Hash::make('password'),
                'role' => UserRole::SuperAdmin,
                'email_verified_at' => now(),
            ],
        );

        User::updateOrCreate(
            ['email' => 'moderateur@clip-adem.test'],
            [
                'name' => 'Modérateur',
                'password' => Hash::make('password'),
                'role' => UserRole::Moderator,
                'email_verified_at' => now(),
            ],
        );

        $artist = Artist::updateOrCreate(
            ['slug' => 'nayra'],
            [
                'name' => 'NAYRA',
                'bio' => 'Artiste rap/afro en développement, sortie de single prévue ce trimestre.',
                'tiktok_handle' => 'nayra.officiel',
                'is_active' => true,
                'created_by' => $admin->getKey(),
            ],
        );

        $campaign = Campaign::updateOrCreate(
            ['slug' => 'nayra-nouveau-single'],
            [
                'artist_id' => $artist->getKey(),
                'title' => 'NAYRA — Nouveau single',
                'brief' => "Utiliser l'extrait de 15 secondes du refrain. Mentionner @nayra.officiel "
                    .'en légende. Interdit : contenu politique, alcool, montage avec un autre artiste.',
                'required_hashtags' => ['#nayra', '#nouveausingle'],
                'status' => CampaignStatus::Active,
                'budget_total_cents' => 150_000, // 1 500 €
                'target_views' => 3_000_000,
                'min_views_per_clip' => 1_000,
                'max_payout_per_clip_cents' => 20_000,
                'starts_at' => now()->subWeek(),
                'ends_at' => now()->addMonth(),
                'created_by' => $admin->getKey(),
            ],
        );

        $campaign->rates()->updateOrCreate(
            ['platform' => Platform::TikTok],
            ['rate_per_1k_cents' => 50, 'is_enabled' => true],   // 0,50 € / 1000 vues
        );

        $campaign->rates()->updateOrCreate(
            ['platform' => Platform::Instagram],
            ['rate_per_1k_cents' => 40, 'is_enabled' => true],
        );

        // Quelques clippeurs et leurs clips, crédités via le moteur pour que le
        // grand livre et les compteurs soient cohérents dès le premier seed.
        $budget = app(CampaignBudgetService::class);

        foreach (['lina', 'yanis', 'sofia'] as $index => $handle) {
            $clipper = User::updateOrCreate(
                ['email' => "{$handle}@clippeur.test"],
                [
                    'name' => ucfirst($handle),
                    'password' => Hash::make('password'),
                    'role' => UserRole::Clipper,
                    'paypal_email' => "{$handle}@clippeur.test",
                    'email_verified_at' => now(),
                ],
            );

            $views = [420_000, 180_000, 95_000][$index];

            $clip = Clip::updateOrCreate(
                ['platform' => Platform::TikTok, 'external_post_id' => "seed-{$handle}"],
                [
                    'campaign_id' => $campaign->getKey(),
                    'user_id' => $clipper->getKey(),
                    'url' => "https://www.tiktok.com/@{$handle}/video/seed",
                    'posted_at' => now()->subDays($index + 2),
                    'status' => ClipStatus::Approved,
                    'views_total' => $views,
                    'last_synced_at' => now(),
                ],
            );

            $account = SocialAccount::updateOrCreate(
                ['platform' => Platform::TikTok, 'external_account_id' => "seed-{$handle}"],
                [
                    'user_id' => $clipper->getKey(),
                    'handle' => $handle,
                    'followers_count' => [45_000, 12_000, 3_200][$index],
                    'verified_at' => now(),
                    'is_active' => true,
                ],
            );

            $clip->forceFill(['social_account_id' => $account->getKey()])->save();

            // Courbe de vues plausible, pour alimenter la détection de fraude.
            foreach ([0.25, 0.6, 1.0] as $step => $ratio) {
                ClipViewSnapshot::updateOrCreate(
                    ['clip_id' => $clip->getKey(), 'captured_at' => now()->subDays(3 - $step)],
                    ['views' => (int) ($views * $ratio), 'source' => 'api'],
                );
            }

            if (BudgetTransaction::where('clip_id', $clip->getKey())->doesntExist()) {
                $budget->creditViews($clip, $views, BudgetTransaction::snapshotKey($clip->getKey(), 1));
            }
        }

        $this->seedModerationQueue($campaign);
    }

    /**
     * De quoi voir travailler la modération et les paiements dès le premier
     * lancement : un clip à valider, un clip aux vues manifestement achetées,
     * et un retrait en attente de décision.
     */
    protected function seedModerationQueue(Campaign $campaign): void
    {
        $suspect = User::updateOrCreate(
            ['email' => 'karim@clippeur.test'],
            [
                'name' => 'Karim',
                'password' => Hash::make('password'),
                'role' => UserRole::Clipper,
                'paypal_email' => 'karim@clippeur.test',
                'email_verified_at' => now(),
            ],
        );

        $account = SocialAccount::updateOrCreate(
            ['platform' => Platform::TikTok, 'external_account_id' => 'seed-karim'],
            [
                'user_id' => $suspect->getKey(),
                'handle' => 'karim.clips',
                'followers_count' => 210, // 210 abonnés pour 340 000 vues
                'is_active' => true,
            ],
        );

        $flagged = Clip::updateOrCreate(
            ['platform' => Platform::TikTok, 'external_post_id' => 'seed-karim-suspect'],
            [
                'campaign_id' => $campaign->getKey(),
                'user_id' => $suspect->getKey(),
                'social_account_id' => $account->getKey(),
                'url' => 'https://www.tiktok.com/@karim.clips/video/suspect',
                'posted_at' => now()->subDays(2),
                'status' => ClipStatus::PendingReview,
                'views_total' => 340_000,
                'last_synced_at' => now(),
            ],
        );

        // 2 000 vues, puis 340 000 quatre heures plus tard.
        ClipViewSnapshot::updateOrCreate(
            ['clip_id' => $flagged->getKey(), 'captured_at' => now()->subDays(2)->addHour()],
            ['views' => 2_000, 'source' => 'api'],
        );
        ClipViewSnapshot::updateOrCreate(
            ['clip_id' => $flagged->getKey(), 'captured_at' => now()->subDays(2)->addHours(5)],
            ['views' => 340_000, 'source' => 'api'],
        );

        $lina = User::where('email', 'lina@clippeur.test')->first();

        if ($lina && $lina->availableBalanceCents() > 0 && $lina->payouts()->doesntExist()) {
            Payout::create([
                'user_id' => $lina->getKey(),
                'amount_cents' => min(15_000, $lina->availableBalanceCents()),
                'currency' => 'EUR',
                'status' => PayoutStatus::Requested,
                'paypal_email' => $lina->paypal_email,
                'requested_at' => now()->subHours(6),
            ]);
        }
    }
}
