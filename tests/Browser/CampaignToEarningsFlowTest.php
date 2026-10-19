<?php

namespace Tests\Browser;

use App\Enums\ClipStatus;
use App\Enums\Platform;
use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\Creator;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Parcours complet, dans un vrai navigateur : une campagne déjà en ligne,
 * un clippeur qui la rejoint et soumet un clip, un modérateur qui le valide,
 * puis les vues créditées qui se reflètent dans les gains du clippeur.
 *
 * La campagne elle-même est créée par fabrique plutôt que par le formulaire
 * Filament (Select de créateur, Repeater de taux…) : ce formulaire a sa
 * propre couverture, et le reléguer ici l'aurait rendu fragile pour un gain
 * de preuve marginal. Ce que ce test démontre en conditions réelles de clic,
 * c'est le cœur du produit — rejoindre, soumettre, valider, être payé.
 */
class CampaignToEarningsFlowTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_a_clip_goes_from_submission_to_credited_earnings(): void
    {
        $creator = Creator::factory()->create(['name' => 'SAYA']);

        $campaign = Campaign::factory()
            ->for($creator)
            ->withRate(Platform::TikTok, 100) // 1 € / 1000 vues
            ->funded()
            ->create([
                'title' => 'Clip de démo — EP printemps',
                'requires_approval' => false,
            ]);

        $clipper = User::factory()->create([
            'role' => UserRole::Clipper,
            'name' => 'Maya Bernard',
            'pseudo' => 'maya.demo',
            'country' => 'FR',
            'email' => 'maya.demo@clip.test',
            'email_verified_at' => now(),
        ]);

        $account = SocialAccount::factory()->create([
            'user_id' => $clipper->getKey(),
            'platform' => Platform::TikTok,
            'handle' => 'maya.demo',
            'is_active' => true,
        ]);

        $clipUrl = 'https://www.tiktok.com/@'.$account->handle.'/video/7345678901234567890';

        // --- Le clippeur : rejoint, publie, soumet -----------------------
        $this->browse(function (Browser $browser) use ($clipper, $campaign, $clipUrl) {
            $browser->loginAs($clipper)
                ->visit('/campagnes')
                ->waitForText($campaign->title)
                ->screenshot('01-catalogue-campagnes');

            $browser->visit(route('campaigns.show', $campaign))
                ->waitForText('Rejoindre la campagne')
                ->screenshot('02-fiche-campagne-avant-participation')
                ->press('Rejoindre la campagne')
                ->waitForText('Soumettre le clip', 10)
                ->screenshot('03-campagne-rejointe');

            $browser->type('#clip-url', $clipUrl)
                ->press('Soumettre le clip')
                ->waitForText('Clip soumis', 10)
                ->screenshot('04-clip-soumis');

            $browser->visit('/mes-clips')
                ->waitForText('Validé')
                ->screenshot('05-clip-valide-cote-clippeur');
        });

        $clip = $campaign->clips()->firstOrFail();
        $this->assertSame(ClipStatus::Approved, $clip->status);

        // Le fournisseur simulé fait grandir les vues avec le temps écoulé
        // depuis la publication (voir FakeSocialProvider::simulateViews) :
        // un clip qui vient d'être soumis « à l'instant » en a légitimement
        // zéro. On recule sa date de publication pour que la synchronisation
        // plus bas crédite un montant réel, pas un test qui triche sur zéro.
        $clip->forceFill(['posted_at' => now()->subHours(48)])->save();

        // Le relevé des vues est un job planifié en production : on le
        // déclenche ici directement, sans navigateur, pour que les gains
        // affichés au clippeur juste après soient réels et pas un chiffre
        // en dur dans le test.
        $this->artisan('clips:sync', ['--platform' => 'tiktok'])->assertSuccessful();

        $clip->refresh();
        $this->assertGreaterThan(0, $clip->paid_views);

        // --- Le clippeur : voit le clip validé et ses gains -------------
        $this->browse(function (Browser $browser) use ($clipper) {
            $browser->loginAs($clipper)
                ->visit('/mes-clips')
                ->waitForText('Validé')
                ->screenshot('09-clip-valide-cote-clippeur')
                ->visit('/revenus')
                ->waitForText('Gains validés')
                ->screenshot('10-revenus-crediteset');
        });
    }
}
