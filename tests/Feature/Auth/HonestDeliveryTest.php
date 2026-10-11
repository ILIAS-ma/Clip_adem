<?php

namespace Tests\Feature\Auth;

use App\Contracts\SmsSender;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Sms\SmsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

/**
 * Ne jamais annoncer un message qui n'est pas parti.
 *
 * Deux pannes vécues sur la production, de la même famille :
 *
 *  - le bouton « renvoyer le lien de confirmation » rendait une page 500,
 *    parce qu'un SMTP était configuré sur un hôte qui n'écoute pas ;
 *  - l'écran de vérification annonçait « un code vient de vous être envoyé
 *    par SMS » alors que le code n'était écrit que dans storage/logs.
 *
 * La première est visible et agaçante. La seconde est pire : la personne
 * guette son téléphone, puis conclut que le site est cassé — et nous ne le
 * saurions jamais.
 */
class HonestDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function utilisateur(array $attributs = []): User
    {
        return User::factory()->create(array_merge([
            'role' => UserRole::Clipper,
            'email_verified_at' => null,
            'pseudo' => 'maya.clips',
            'country' => 'FR',
            'paypal_email' => 'maya@paypal.test',
            'profile_completed_at' => now(),
        ], $attributs));
    }

    // ------------------------------------------------------------------
    // Les e-mails
    // ------------------------------------------------------------------

    #[Test]
    public function resending_the_link_says_so_when_no_mail_can_leave(): void
    {
        // Pilote `log` : rien ne quitte le serveur. Annoncer « lien envoyé »
        // laisserait rafraîchir une boîte vide indéfiniment.
        config(['mail.default' => 'log']);

        $this->actingAs($this->utilisateur())
            ->post('/email/verification-notification')
            ->assertSessionHasErrors('email')
            ->assertSessionMissing('status');
    }

    #[Test]
    public function resending_the_link_does_not_crash_when_the_transport_fails(): void
    {
        /*
         * Le cas vécu : SMTP vers 127.0.0.1:1025, personne à l'écoute. Une
         * TransportException remontait jusqu'à une page 500 — à l'endroit
         * exact où l'on vient parce qu'on est déjà bloqué.
         */
        config(['mail.default' => 'smtp']);

        Mail::shouldReceive('mailer')->andThrow(new TransportException('refus de connexion'));

        $this->actingAs($this->utilisateur())
            ->post('/email/verification-notification')
            ->assertRedirect()
            ->assertSessionHasErrors('email');
    }

    #[Test]
    public function a_forgotten_password_says_so_when_no_mail_can_leave(): void
    {
        // La page où l'on arrive justement parce qu'on ne peut plus entrer :
        // c'est le dernier endroit où mentir sur un envoi.
        config(['mail.default' => 'log']);

        $this->utilisateur(['email' => 'maya@clip.test']);

        $this->post('/forgot-password', ['email' => 'maya@clip.test'])
            ->assertSessionHasErrors('email')
            ->assertSessionMissing('status');
    }

    #[Test]
    public function a_forgotten_password_does_not_crash_when_the_transport_fails(): void
    {
        config(['mail.default' => 'smtp']);

        $this->utilisateur(['email' => 'maya@clip.test']);

        Mail::shouldReceive('mailer')->andThrow(new TransportException('refus de connexion'));

        $this->post('/forgot-password', ['email' => 'maya@clip.test'])
            ->assertRedirect()
            ->assertSessionHasErrors('email');
    }

    #[Test]
    public function a_verified_account_is_simply_sent_home(): void
    {
        // Rien à renvoyer : ni erreur, ni message d'envoi.
        config(['mail.default' => 'log']);

        $this->actingAs($this->utilisateur(['email_verified_at' => now()]))
            ->post('/email/verification-notification')
            ->assertRedirect('/dashboard');
    }

    // ------------------------------------------------------------------
    // Les SMS
    // ------------------------------------------------------------------

    /** Installe un pilote SMS, simulé ou non. */
    protected function piloteSms(bool $simule): void
    {
        $pilote = Mockery::mock(SmsSender::class);
        $pilote->shouldReceive('send')->andReturnNull();
        $pilote->shouldReceive('name')->andReturn($simule ? 'journal' : 'twilio');
        $pilote->shouldReceive('isSimulated')->andReturn($simule);

        $gestionnaire = Mockery::mock(SmsManager::class);
        $gestionnaire->shouldReceive('driver')->andReturn($pilote);
        $gestionnaire->shouldReceive('isConfigured')->andReturn(! $simule);

        $this->app->instance(SmsManager::class, $gestionnaire);
    }

    #[Test]
    public function a_simulated_send_shows_the_code_instead_of_promising_a_sms(): void
    {
        /*
         * Le code n'est montré que parce que rien n'est parti : sans lui, la
         * vérification serait infranchissable. Et il est dit franchement que
         * le SMS n'existe pas, au lieu d'en annoncer un.
         */
        $this->piloteSms(simule: true);

        $user = $this->utilisateur(['email_verified_at' => now()]);

        $reponse = $this->actingAs($user)
            ->post(route('phone.send'), ['phone' => '0612345678', 'phone_country' => 'FR'])
            ->assertRedirect(route('phone.verify'));

        $reponse->assertSessionHas('codeSimule');
        $reponse->assertSessionMissing('status');

        $code = session('codeSimule');

        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);

        $this->actingAs($user->fresh())
            ->get(route('phone.verify'))
            ->assertSuccessful()
            ->assertSee("Aucun SMS n'est parti", escape: false)
            ->assertSee($code);

        // Et ce code marche : afficher un code faux serait pire que ne rien
        // afficher.
        $this->actingAs($user->fresh())
            ->post(route('phone.confirm'), ['code' => $code])
            ->assertSessionHasNoErrors();

        $this->assertTrue($user->fresh()->hasVerifiedPhone());
    }

    #[Test]
    public function a_real_send_never_shows_the_code(): void
    {
        /*
         * L'asymétrie est le cœur du dispositif : il n'existe aucun chemin
         * par lequel un code réellement envoyé puisse s'afficher. Sinon
         * quiconque voit l'écran de quelqu'un verrait son code.
         */
        $this->piloteSms(simule: false);

        $user = $this->utilisateur(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->post(route('phone.send'), ['phone' => '0612345678', 'phone_country' => 'FR'])
            ->assertSessionMissing('codeSimule')
            ->assertSessionHas('status');

        $this->actingAs($user->fresh())
            ->get(route('phone.verify'))
            ->assertSuccessful()
            ->assertDontSee("Aucun SMS n'est parti", escape: false)
            ->assertDontSee('Les SMS ne partent pas encore');
    }

    #[Test]
    public function the_screen_warns_before_one_even_types_a_number(): void
    {
        // Dit avant la saisie, pas après : on ne doit pas entrer son numéro
        // en croyant qu'un SMS va partir.
        $this->piloteSms(simule: true);

        $this->actingAs($this->utilisateur(['email_verified_at' => now()]))
            ->get(route('phone.verify'))
            ->assertSuccessful()
            ->assertSee('Les SMS ne partent pas encore');
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
