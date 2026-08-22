<?php

namespace Database\Seeders;

use App\Contracts\CampaignBudgetService;
use App\Enums\CampaignStatus;
use App\Enums\ClipStatus;
use App\Enums\Platform;
use App\Enums\UserRole;
use App\Models\Artist;
use App\Models\BudgetTransaction;
use App\Models\Campaign;
use App\Models\Clip;
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

            if (BudgetTransaction::where('clip_id', $clip->getKey())->doesntExist()) {
                $budget->creditViews($clip, $views, BudgetTransaction::snapshotKey($clip->getKey(), 1));
            }
        }
    }
}
