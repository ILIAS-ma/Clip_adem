<?php

namespace App\Console\Commands;

use App\Contracts\CampaignBudgetService;
use App\Enums\CampaignStatus;
use App\Enums\ClipStatus;
use App\Enums\ParticipationStatus;
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
use App\Services\Clippers\ClipperProgressionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Quatre comptes de démonstration, un par rôle, prêts à la connexion.
 *
 * Chacun arrive sur un espace déjà rempli : un tableau de bord vide ne montre
 * que des états d'attente, et c'est précisément ce qu'on ne cherche pas à voir
 * quand on découvre le produit.
 *
 * Idempotente : relancer la commande met les comptes à jour sans les dupliquer.
 */
class CreateDemoAccountsCommand extends Command
{
    protected $signature = 'demo:accounts {--password=password : Mot de passe commun aux quatre comptes}';

    protected $description = 'Crée quatre comptes de démonstration remplis, un par rôle.';

    protected string $password;

    public function handle(CampaignBudgetService $budget, ClipperProgressionService $progression): int
    {
        $this->password = $this->option('password');

        $admin = $this->staff('admin@clip.test', 'Admin Démo', UserRole::SuperAdmin);
        $this->staff('moderateur@clip.test', 'Modérateur Démo', UserRole::Moderator);

        $artist = $this->artist($admin);
        $campaign = $this->campaign($artist, $admin);

        $this->clipper($campaign, $budget, $progression);

        $this->newLine();
        $this->info('Comptes prêts. Mot de passe commun : '.$this->password);
        $this->table(
            ['Rôle', 'Compte', 'Arrive sur'],
            [
                ['Administrateur', 'admin@clip.test', '/admin'],
                ['Modérateur', 'moderateur@clip.test', '/admin'],
                ['Clippeur', 'clippeur@clip.test', '/dashboard'],
                ['Artiste', 'artiste@clip.test', '/artiste'],
            ],
        );

        return self::SUCCESS;
    }

    protected function staff(string $email, string $name, UserRole $role): User
    {
        return tap(User::withTrashed()->firstOrNew(['email' => $email]), function (User $user) use ($name, $role) {
            $user->forceFill([
                'name' => $name,
                'password' => Hash::make($this->password),
                'role' => $role,
                'email_verified_at' => now(),
                'is_banned' => false,
                'deleted_at' => null,
            ])->save();
        });
    }

    protected function artist(User $admin): Artist
    {
        $user = tap(User::withTrashed()->firstOrNew(['email' => 'artiste@clip.test']), function (User $user) {
            $user->forceFill([
                'name' => 'Sami Toure',
                'password' => Hash::make($this->password),
                'role' => UserRole::Artist,
                'email_verified_at' => now(),
                'is_banned' => false,
                'deleted_at' => null,
            ])->save();
        });

        return tap(Artist::withTrashed()->firstOrNew(['slug' => 'saya']), function (Artist $artist) use ($user, $admin) {
            $artist->forceFill([
                'user_id' => $user->getKey(),
                'name' => 'SAYA',
                'bio' => 'Nouvel EP en préparation, sortie prévue au printemps.',
                'tiktok_handle' => 'saya.music',
                'instagram_handle' => 'saya.music',
                // Active : l'artiste doit voir ses statistiques dès sa connexion.
                'is_active' => true,
                'created_by' => $admin->getKey(),
                'deleted_at' => null,
            ])->save();
        });
    }

    protected function campaign(Artist $artist, User $admin): Campaign
    {
        $campaign = tap(Campaign::withTrashed()->firstOrNew(['slug' => 'saya-ep-printemps']), function (Campaign $campaign) use ($artist, $admin) {
            $campaign->forceFill([
                'artist_id' => $artist->getKey(),
                'title' => 'SAYA — EP printemps',
                'brief' => "Utiliser les 20 premières secondes du morceau. Mentionner @saya.music en légende.\n"
                    .'Interdit : contenu politique, alcool, montage avec un autre artiste.',
                'required_hashtags' => ['#saya', '#epprintemps'],
                'status' => CampaignStatus::Active,
                'budget_total_cents' => 500_000,   // 5 000 €
                'spent_cents' => 0,
                'target_views' => 8_000_000,
                'min_views_per_clip' => 1_000,
                'max_payout_per_clip_cents' => 60_000,
                'starts_at' => now()->subWeeks(2),
                'ends_at' => now()->addMonths(2),
                'requires_approval' => false,
                'created_by' => $admin->getKey(),
                'deleted_at' => null,
            ])->save();
        });

        $campaign->rates()->updateOrCreate(
            ['platform' => Platform::TikTok],
            ['rate_per_1k_cents' => 100, 'is_enabled' => true],
        );

        $campaign->rates()->updateOrCreate(
            ['platform' => Platform::Instagram],
            ['rate_per_1k_cents' => 80, 'is_enabled' => true],
        );

        return $campaign->fresh();
    }

    protected function clipper(Campaign $campaign, CampaignBudgetService $budget, ClipperProgressionService $progression): User
    {
        $clipper = tap(User::withTrashed()->firstOrNew(['email' => 'clippeur@clip.test']), function (User $user) {
            $user->forceFill([
                'name' => 'Maya Bernard',
                'pseudo' => 'maya.clips',
                'country' => 'FR',
                'paypal_email' => 'maya.clips@paypal.test',
                'password' => Hash::make($this->password),
                'role' => UserRole::Clipper,
                'email_verified_at' => now(),
                'profile_completed_at' => now(),
                'is_banned' => false,
                'deleted_at' => null,
            ])->save();
        });

        $account = SocialAccount::updateOrCreate(
            ['platform' => Platform::TikTok, 'external_account_id' => 'demo-clippeur-tiktok'],
            [
                'user_id' => $clipper->getKey(),
                'handle' => 'maya.clips',
                'access_token' => 'demo-access-token',
                'refresh_token' => 'demo-refresh-token',
                'token_expires_at' => now()->addDays(60),
                'scopes' => ['read.profile', 'read.metrics'],
                'followers_count' => 68_000,
                'verified_at' => now(),
                'is_active' => true,
                'needs_reconnect' => false,
            ],
        );

        // Deux campagnes distinctes : la variété compte dans le calcul de
        // l'expérience, et le tableau de bord a ainsi plus d'un clip à montrer.
        $campaigns = collect([$campaign, Campaign::where('slug', 'nayra-nouveau-single')->first()])->filter();

        foreach ($campaigns as $index => $target) {
            $target->participations()->updateOrCreate(
                ['social_account_id' => $account->getKey()],
                [
                    'user_id' => $clipper->getKey(),
                    'status' => ParticipationStatus::Approved,
                    'applied_at' => now()->subDays(10 - $index),
                    'approved_at' => now()->subDays(10 - $index),
                ],
            );

            $views = [420_000, 95_000][$index] ?? 50_000;

            $clip = Clip::updateOrCreate(
                ['platform' => Platform::TikTok, 'external_post_id' => 'demo-clippeur-'.$target->getKey()],
                [
                    'campaign_id' => $target->getKey(),
                    'user_id' => $clipper->getKey(),
                    'social_account_id' => $account->getKey(),
                    'url' => 'https://www.tiktok.com/@maya.clips/video/demo'.$target->getKey(),
                    'caption' => 'Ce son tourne en boucle 🔥 '.implode(' ', $target->required_hashtags ?? []),
                    'duration_seconds' => 24,
                    'posted_at' => now()->subDays(8 - $index),
                    'submitted_at' => now()->subDays(8 - $index),
                    'status' => ClipStatus::Approved,
                    'compliance_status' => 'passed',
                    'views_total' => $views,
                    'last_synced_at' => now(),
                ],
            );

            foreach ([0.3, 0.7, 1.0] as $step => $ratio) {
                ClipViewSnapshot::updateOrCreate(
                    ['clip_id' => $clip->getKey(), 'captured_at' => now()->subDays(3 - $step)],
                    ['views' => (int) ($views * $ratio), 'source' => 'api'],
                );
            }

            if (BudgetTransaction::where('clip_id', $clip->getKey())->doesntExist()) {
                $budget->creditViews($clip, $views, BudgetTransaction::snapshotKey($clip->getKey(), 1));
            }
        }

        // Un retrait en attente : la page Revenus a ainsi une ligne d'historique
        // et la file du back-office une décision à prendre.
        $clipper->refresh();

        if ($clipper->payouts()->doesntExist() && $clipper->availableBalanceCents() >= 5_000) {
            Payout::create([
                'user_id' => $clipper->getKey(),
                'amount_cents' => 5_000,
                'currency' => 'EUR',
                'status' => PayoutStatus::Requested,
                'paypal_email' => $clipper->paypal_email,
                'requested_at' => now()->subDay(),
            ]);
        }

        $progression->forget($clipper);

        $level = $progression->for($clipper->fresh());
        $this->line(sprintf(
            'Clippeur : niveau %s, %s XP, solde %s €.',
            $level->level->label(),
            number_format($level->careerXp, 0, ',', ' '),
            number_format($clipper->fresh()->availableBalanceCents() / 100, 2, ',', ' '),
        ));

        return $clipper;
    }
}
