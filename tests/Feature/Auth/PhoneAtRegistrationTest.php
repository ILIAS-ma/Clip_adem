<?php

namespace Tests\Feature\Auth;

use App\Contracts\SmsSender;
use App\Enums\UserRole;
use App\Models\Creator;
use App\Models\User;
use App\Services\Sms\SmsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Le numéro à l'inscription, et la vérification pour entrer.
 *
 * Demander le numéro plus tard reviendrait à laisser entrer dix comptes avant
 * de poser la question. Et l'exiger sans le vérifier ne vaut rien : n'importe
 * quelle suite de chiffres passe.
 *
 * Ce qui doit tenir : on ne s'inscrit pas sans numéro lisible, deux comptes ne
 * partagent pas une ligne, et la garde ne s'enferme pas elle-même dehors — ni
 * en bouclant sur sa propre page, ni en coupant la déconnexion.
 */
class PhoneAtRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected array $envoyes = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->envoyes = [];

        $faux = Mockery::mock(SmsSender::class);
        $faux->shouldReceive('send')->andReturnUsing(function ($to, $message) {
            $this->envoyes[] = ['to' => $to, 'message' => $message];
        });
        $faux->shouldReceive('name')->andReturn('test');
        $faux->shouldReceive('isSimulated')->andReturn(true);

        $gestionnaire = Mockery::mock(SmsManager::class);
        $gestionnaire->shouldReceive('driver')->andReturn($faux);
        $gestionnaire->shouldReceive('isConfigured')->andReturn(true);

        $this->app->instance(SmsManager::class, $gestionnaire);
    }

    /** @return array<string, mixed> */
    protected function inscription(array $remplace = []): array
    {
        return array_merge([
            'first_name' => 'Maya',
            'last_name' => 'Bernard',
            'email' => 'maya@clip.test',
            'phone' => '06 12 34 56 78',
            'phone_country' => 'FR',
            'password' => 'MotDePasse!2026',
            'password_confirmation' => 'MotDePasse!2026',
            'role' => UserRole::Clipper->value,
        ], $remplace);
    }

    #[Test]
    public function the_number_is_stored_in_international_form(): void
    {
        /*
         * « 0612… » et « +33612… » désignent le même abonné. Sans
         * normalisation à l'écriture, la contrainte d'unicité — qui est tout
         * l'intérêt du dispositif — ne verrait rien.
         */
        $this->post(route('register'), $this->inscription())->assertRedirect();

        $user = User::where('email', 'maya@clip.test')->firstOrFail();

        $this->assertSame('+33612345678', $user->phone);

        // Saisi n'est pas vérifié : l'envoi d'un SMS pendant la création du
        // compte ferait échouer l'inscription entière si le prestataire ne
        // répond pas.
        $this->assertFalse($user->hasVerifiedPhone());
        $this->assertEmpty($this->envoyes);
    }

    #[Test]
    public function one_cannot_register_without_a_number(): void
    {
        $this->post(route('register'), $this->inscription(['phone' => '']))
            ->assertSessionHasErrors('phone');

        $this->assertDatabaseMissing('users', ['email' => 'maya@clip.test']);
    }

    #[Test]
    public function an_unreadable_number_is_refused(): void
    {
        $this->post(route('register'), $this->inscription(['phone' => 'appelez-moi']))
            ->assertSessionHasErrors('phone');

        $this->assertDatabaseMissing('users', ['email' => 'maya@clip.test']);
    }

    #[Test]
    public function an_unknown_country_is_refused(): void
    {
        // Sans liste blanche, un indicatif inventé produirait un numéro
        // normalisé en apparence mais injoignable.
        $this->post(route('register'), $this->inscription(['phone_country' => 'ZZ']))
            ->assertSessionHasErrors('phone_country');
    }

    #[Test]
    public function a_number_already_used_is_refused_without_a_crash(): void
    {
        /*
         * Sans ce contrôle, la contrainte de base lèverait une
         * QueryException — soit une page d'erreur — là où la personne attend
         * qu'on lui dise simplement que ce numéro est pris.
         */
        User::factory()->create(['phone' => '+33612345678', 'phone_verified_at' => now()]);

        $this->post(route('register'), $this->inscription())
            ->assertSessionHasErrors('phone');

        $this->assertDatabaseMissing('users', ['email' => 'maya@clip.test']);
    }

    #[Test]
    public function registration_leads_straight_to_verification_when_required(): void
    {
        // C'est le moment où la personne a son téléphone en main. L'envoyer
        // d'abord sur son espace la ferait rebondir au premier clic.
        config(['clipping.onboarding.require_phone_verification' => true]);

        $this->post(route('register'), $this->inscription())
            ->assertRedirect(route('phone.verify'));
    }

    #[Test]
    public function registration_goes_home_when_the_control_is_suspended(): void
    {
        config(['clipping.onboarding.require_phone_verification' => false]);

        $this->post(route('register'), $this->inscription())
            ->assertRedirect('/dashboard');
    }

    #[Test]
    public function the_number_given_at_registration_is_prefilled(): void
    {
        // Le retaper serait une occasion de se tromper d'un chiffre, et le
        // code partirait alors vers un inconnu.
        $this->post(route('register'), $this->inscription());

        $this->actingAs(User::where('email', 'maya@clip.test')->firstOrFail())
            ->get(route('phone.verify'))
            ->assertSuccessful()
            ->assertSee('value="+33612345678"', escape: false);
    }

    // ------------------------------------------------------------------
    // La garde
    // ------------------------------------------------------------------

    protected function clipper(array $attributs = []): User
    {
        return User::factory()->create(array_merge([
            'role' => UserRole::Clipper,
            'email_verified_at' => now(),
            'pseudo' => 'maya.clips',
            'country' => 'FR',
            'paypal_email' => 'maya@paypal.test',
            'profile_completed_at' => now(),
        ], $attributs));
    }

    #[Test]
    public function an_unverified_clipper_is_sent_to_the_verification(): void
    {
        config(['clipping.onboarding.require_phone_verification' => true]);

        $this->actingAs($this->clipper())
            ->get(route('dashboard'))
            ->assertRedirect(route('phone.verify'));
    }

    #[Test]
    public function an_unverified_creator_is_sent_to_the_verification(): void
    {
        // Un créateur finance des campagnes : savoir le joindre compte
        // davantage encore le jour où un encaissement est contesté.
        config(['clipping.onboarding.require_phone_verification' => true]);

        $creator = User::factory()->create([
            'role' => UserRole::Creator,
            'email_verified_at' => now(),
        ]);

        Creator::factory()->create(['user_id' => $creator->getKey(), 'is_active' => true]);

        $this->actingAs($creator)
            ->get(route('creator.dashboard'))
            ->assertRedirect(route('phone.verify'));
    }

    #[Test]
    public function a_verified_clipper_passes(): void
    {
        config(['clipping.onboarding.require_phone_verification' => true]);

        $this->actingAs($this->clipper([
            'phone' => '+33612345678',
            'phone_verified_at' => now(),
        ]))
            ->get(route('dashboard'))
            ->assertSuccessful();
    }

    #[Test]
    public function the_guard_never_locks_itself_out(): void
    {
        /*
         * Les trois issues qui doivent rester ouvertes à qui n'a pas de numéro
         * confirmé : la page de vérification elle-même — sans quoi la
         * redirection boucle à l'infini — son profil, et la déconnexion.
         * S'enfermer dans sa session est le genre de panne qu'on ne découvre
         * qu'en voulant partir.
         */
        config(['clipping.onboarding.require_phone_verification' => true]);

        $user = $this->clipper();

        $this->actingAs($user)->get(route('phone.verify'))->assertSuccessful();
        $this->actingAs($user)->get(route('profile.edit'))->assertSuccessful();
        $this->actingAs($user)->post(route('logout'))->assertRedirect('/');
    }

    #[Test]
    public function after_verifying_one_returns_where_one_was_going(): void
    {
        /*
         * Sans mémoire de la page demandée, un lien de campagne partagé perd
         * sa destination à la première vérification : on atterrit sur son
         * profil sans savoir pourquoi, et le lien est à rechercher.
         */
        config(['clipping.onboarding.require_phone_verification' => true]);

        $user = $this->clipper();

        $this->actingAs($user)
            ->get(route('clips.index'))
            ->assertRedirect(route('phone.verify'));

        $this->actingAs($user->fresh())->post(route('phone.send'), [
            'phone' => '0612345678', 'phone_country' => 'FR',
        ]);

        preg_match('/\b(\d{6})\b/', $this->envoyes[0]['message'] ?? '', $m);

        $this->actingAs($user->fresh())
            ->post(route('phone.confirm'), ['code' => $m[1] ?? ''])
            ->assertRedirect(route('clips.index'));

        $this->assertTrue($user->fresh()->hasVerifiedPhone());
    }

    #[Test]
    public function the_control_can_be_suspended(): void
    {
        // Le réglage existe pour une raison concrète : activé sans prestataire
        // SMS, le code n'existe que dans les journaux et plus personne
        // n'entre, administrateur compris.
        config(['clipping.onboarding.require_phone_verification' => false]);

        $this->actingAs($this->clipper())
            ->get(route('dashboard'))
            ->assertSuccessful();
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
