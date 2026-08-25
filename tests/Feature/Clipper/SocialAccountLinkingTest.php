<?php

namespace Tests\Feature\Clipper;

use App\Contracts\SocialProvider;
use App\Enums\Platform;
use App\Enums\UserRole;
use App\Exceptions\SocialProviderFailed;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Social\FakeSocialProvider;
use App\Services\Social\SocialAccountLinker;
use App\Services\Social\SocialProviderManager;
use App\Support\Social\ConnectedAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SocialAccountLinkingTest extends TestCase
{
    use RefreshDatabase;

    protected function clipper(): User
    {
        return User::factory()->create([
            'role' => UserRole::Clipper,
            'email_verified_at' => now(),
            'pseudo' => 'clip'.fake()->unique()->numberBetween(1, 99999),
            'country' => 'FR',
            'paypal_email' => fake()->unique()->safeEmail(),
        ]);
    }

    #[Test]
    public function without_credentials_the_simulated_provider_takes_over(): void
    {
        // C'est ce qui permet de construire tout l'aval — conformité, synchro,
        // gains — pendant les jours ouvrés que prennent les revues TikTok et Meta.
        $manager = app(SocialProviderManager::class);

        foreach (Platform::cases() as $platform) {
            $this->assertTrue($manager->isSimulated($platform));
            $this->assertInstanceOf(FakeSocialProvider::class, $manager->for($platform));
        }
    }

    #[Test]
    public function the_oauth_round_trip_links_an_account(): void
    {
        $clipper = $this->clipper();

        $redirect = $this->actingAs($clipper)->get(route('social.redirect', 'tiktok'));
        $redirect->assertRedirect();

        // Le fournisseur simulé renvoie directement vers notre callback.
        $callback = $redirect->headers->get('Location');

        $this->actingAs($clipper)->get($callback)->assertRedirect(route('accounts.index'));

        $account = $clipper->socialAccounts()->sole();
        $this->assertSame(Platform::TikTok, $account->platform);
        $this->assertTrue($account->is_active);
        $this->assertFalse($account->needs_reconnect);
        $this->assertNotNull($account->access_token);
    }

    #[Test]
    public function a_forged_callback_without_the_session_state_is_refused(): void
    {
        // Sans ce contrôle, un tiers pourrait faire rattacher son propre compte
        // au profil de la victime.
        $clipper = $this->clipper();

        $this->actingAs($clipper)
            ->get(route('social.callback', ['platform' => 'tiktok', 'code' => 'x', 'state' => 'faux']))
            ->assertRedirect(route('accounts.index'))
            ->assertSessionHasErrors('social');

        $this->assertSame(0, $clipper->socialAccounts()->count());
    }

    #[Test]
    public function relinking_updates_the_account_instead_of_duplicating_it(): void
    {
        $clipper = $this->clipper();
        $provider = new FakeSocialProvider(Platform::TikTok);
        $linker = app(SocialAccountLinker::class);

        $connected = $provider->connect('code-stable');
        $first = $linker->link($clipper, $connected);
        $second = $linker->link($clipper, $provider->connect('code-stable'));

        // Les clips existants doivent rester rattachés au même enregistrement.
        $this->assertSame($first->getKey(), $second->getKey());
        $this->assertSame(1, $clipper->socialAccounts()->count());
    }

    #[Test]
    public function an_account_already_linked_elsewhere_cannot_be_stolen(): void
    {
        $provider = new FakeSocialProvider(Platform::TikTok);
        $linker = app(SocialAccountLinker::class);
        $connected = $provider->connect('code-partage');

        $linker->link($this->clipper(), $connected);

        $this->expectException(SocialProviderFailed::class);
        $this->expectExceptionMessage('déjà lié à un autre profil');

        $linker->link($this->clipper(), $connected);
    }

    #[Test]
    public function relinking_clears_the_reconnection_flag(): void
    {
        $clipper = $this->clipper();
        $provider = new FakeSocialProvider(Platform::TikTok);
        $linker = app(SocialAccountLinker::class);

        $account = $linker->link($clipper, $provider->connect('code'));
        $account->forceFill(['needs_reconnect' => true, 'last_error' => 'jeton expiré'])->save();

        $linker->link($clipper, $provider->connect('code'));

        $account->refresh();
        $this->assertFalse($account->needs_reconnect);
        $this->assertNull($account->last_error);
    }

    #[Test]
    public function unlinking_keeps_the_record_and_wipes_the_tokens(): void
    {
        $clipper = $this->clipper();
        $account = SocialAccount::factory()->create(['user_id' => $clipper->getKey()]);

        $this->actingAs($clipper)
            ->delete(route('accounts.destroy', $account))
            ->assertRedirect(route('accounts.index'));

        $account->refresh();
        // Les clips soumis référencent ce compte : le supprimer rendrait leur
        // historique illisible.
        $this->assertFalse($account->is_active);
        $this->assertNull($account->access_token);
        $this->assertDatabaseHas('social_accounts', ['id' => $account->getKey()]);
    }

    #[Test]
    public function a_clipper_cannot_unlink_someone_elses_account(): void
    {
        $account = SocialAccount::factory()->create(['user_id' => $this->clipper()->getKey()]);

        $this->actingAs($this->clipper())
            ->delete(route('accounts.destroy', $account))
            ->assertForbidden();
    }

    #[Test]
    public function a_failed_refresh_flags_the_account_instead_of_throwing(): void
    {
        // La synchronisation sautera ce compte plutôt que de brûler du quota
        // sur des 401, et le clippeur verra le bandeau d'alerte.
        $account = SocialAccount::factory()->create(['user_id' => $this->clipper()->getKey()]);

        $linker = new SocialAccountLinker(new class extends SocialProviderManager
        {
            public function for(Platform $platform): SocialProvider
            {
                return new class(Platform::TikTok) extends FakeSocialProvider
                {
                    public function refresh(SocialAccount $account): ConnectedAccount
                    {
                        throw SocialProviderFailed::missingRefreshToken(Platform::TikTok);
                    }
                };
            }
        });

        $this->assertFalse($linker->refresh($account));

        $account->refresh();
        $this->assertTrue($account->needs_reconnect);
        $this->assertNotNull($account->last_error);
    }
}
