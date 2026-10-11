<?php

namespace Tests\Feature\Console;

use App\Enums\ClipStatus;
use App\Enums\Platform;
use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\Creator;
use App\Models\Payout;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Suppression définitive de comptes, par fragment d'adresse.
 *
 * Une commande plutôt qu'un DELETE à la main : trois refus qu'une requête SQL
 * ne connaît pas. Elle ne laisse jamais la plateforme sans super
 * administrateur, elle n'efface pas un compte par lequel de l'argent a passé,
 * et elle ne fait rien sans qu'on le demande explicitement.
 */
class PurgeUsersTest extends TestCase
{
    use RefreshDatabase;

    protected function superAdmin(string $email): User
    {
        return User::factory()->create([
            'role' => UserRole::SuperAdmin,
            'email' => $email,
            'email_verified_at' => now(),
        ]);
    }

    protected function clipper(string $email): User
    {
        return User::factory()->create([
            'role' => UserRole::Clipper,
            'email' => $email,
            'email_verified_at' => now(),
        ]);
    }

    #[Test]
    public function it_lists_without_deleting_by_default(): void
    {
        /*
         * Montrer avant d'agir. Une commande qui supprime au premier appel se
         * lance par curiosité, et il est alors trop tard.
         */
        $cible = $this->clipper('ilias.bounabat@gmail.com');
        $this->superAdmin('admin@clip.test');

        $this->artisan('users:purge', ['motif' => 'bounabat'])
            ->assertSuccessful();

        $this->assertNotNull($cible->fresh());
    }

    #[Test]
    public function it_deletes_on_demand(): void
    {
        $cible = $this->clipper('ilias.bounabat@gmail.com');
        $garde = $this->clipper('autre@gmail.com');
        $this->superAdmin('admin@clip.test');

        $this->artisan('users:purge', ['motif' => 'bounabat', '--force' => true])
            ->assertSuccessful();

        $this->assertNull(User::withTrashed()->find($cible->getKey()));
        $this->assertNotNull($garde->fresh());
    }

    #[Test]
    public function it_matches_on_the_email_only(): void
    {
        /*
         * Le nom n'est pas un critère : deux personnes peuvent le partager, et
         * une suppression qui emporte un homonyme n'a aucun moyen de revenir
         * en arrière. L'adresse est unique, elle désigne un compte et un seul.
         */
        $parNom = $this->clipper('tout-autre-chose@gmail.com');
        $parNom->forceFill(['name' => 'Ilias Bounabat'])->save();

        $this->superAdmin('admin@clip.test');

        $this->artisan('users:purge', ['motif' => 'bounabat', '--force' => true])
            ->assertSuccessful();

        $this->assertNotNull($parNom->fresh());
    }

    #[Test]
    public function it_reaches_accounts_already_in_the_bin(): void
    {
        // Un compte en corbeille garde son adresse, donc empêche de se
        // réinscrire avec : le ménage doit l'emporter aussi.
        $cible = $this->clipper('ilias.bounabat@gmail.com');
        $cible->delete();

        $this->superAdmin('admin@clip.test');

        $this->artisan('users:purge', ['motif' => 'bounabat', '--force' => true])
            ->assertSuccessful();

        $this->assertNull(User::withTrashed()->find($cible->getKey()));
    }

    #[Test]
    public function it_refuses_to_leave_no_super_admin(): void
    {
        /*
         * Le contrôle est fait avant, pas après : constater la perte une fois
         * les comptes effacés n'avance à rien, puisque se redonner le rôle
         * demande justement d'entrer dans le back-office.
         */
        $seul = $this->superAdmin('ilias.bounabat@gmail.com');

        $this->artisan('users:purge', ['motif' => 'bounabat', '--force' => true])
            ->assertFailed();

        $this->assertNotNull($seul->fresh());
    }

    #[Test]
    public function it_deletes_an_admin_when_another_remains(): void
    {
        $cible = $this->superAdmin('ilias.bounabat@gmail.com');
        $reste = $this->superAdmin('admin@iplantclip.fr');

        $this->artisan('users:purge', ['motif' => 'bounabat', '--force' => true])
            ->assertSuccessful();

        $this->assertNull(User::withTrashed()->find($cible->getKey()));
        $this->assertNotNull($reste->fresh());
    }

    #[Test]
    public function it_refuses_an_account_that_was_paid(): void
    {
        /*
         * L'historique d'un clippeur payé est la preuve de ce qu'on lui a
         * versé. L'effacer rendrait une contestation indéfendable, et le
         * grand livre ne concorderait plus avec les comptes qui restent.
         */
        $paye = $this->clipper('ilias.bounabat@gmail.com');
        $this->superAdmin('admin@clip.test');

        $campaign = Campaign::factory()
            ->withRate(Platform::TikTok, ratePer1kCents: 100)
            ->funded()
            ->create(['creator_id' => Creator::factory()->create()->getKey()]);

        Clip::factory()->create([
            'user_id' => $paye->getKey(),
            'campaign_id' => $campaign->getKey(),
            'status' => ClipStatus::Approved,
            'earned_cents' => 1_200,
        ]);

        $this->artisan('users:purge', ['motif' => 'bounabat', '--force' => true])
            ->assertFailed();

        $this->assertNotNull($paye->fresh());
    }

    #[Test]
    public function it_refuses_an_account_with_a_payout(): void
    {
        $paye = $this->clipper('ilias.bounabat@gmail.com');
        $this->superAdmin('admin@clip.test');

        Payout::factory()->create(['user_id' => $paye->getKey()]);

        $this->artisan('users:purge', ['motif' => 'bounabat', '--force' => true])
            ->assertFailed();

        $this->assertNotNull($paye->fresh());
    }

    #[Test]
    public function an_empty_pattern_is_refused(): void
    {
        // Sans ce refus, « users:purge "" » viserait tous les comptes de la
        // plateforme, et le garde-fou du super administrateur serait la seule
        // chose entre la commande et la catastrophe.
        $this->clipper('quelquun@gmail.com');
        $this->superAdmin('admin@clip.test');

        $this->artisan('users:purge', ['motif' => '  ', '--force' => true])
            ->assertFailed();

        $this->assertSame(2, User::count());
    }

    #[Test]
    public function an_unknown_pattern_changes_nothing(): void
    {
        $this->clipper('quelquun@gmail.com');
        $this->superAdmin('admin@clip.test');

        $this->artisan('users:purge', ['motif' => 'personne-de-ce-nom', '--force' => true])
            ->assertSuccessful();

        $this->assertSame(2, User::count());
    }
}
