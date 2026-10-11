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
    public function resending_the_link_is_simply_closed_when_no_mail_can_leave(): void
    {
        /*
         * Pilote `log` : rien ne quitte le serveur. On reconduit chez soi
         * sans message — expliquer la mécanique interne à quelqu'un qui
         * voulait juste entrer ne lui sert à rien, et un bandeau d'erreur sur
         * une action qu'il n'aurait pas dû pouvoir déclencher ressemble à une
         * panne de son côté.
         */
        config(['mail.default' => 'log']);

        $this->actingAs($this->utilisateur())
            ->post('/email/verification-notification')
            ->assertRedirect('/dashboard')
            ->assertSessionHasNoErrors();
    }

    #[Test]
    public function resending_the_link_is_closed_when_the_control_is_suspended(): void
    {
        // Le numéro de téléphone tient ce rôle désormais : il n'y a plus rien
        // à confirmer par e-mail, même avec un prestataire configuré.
        config([
            'mail.default' => 'smtp',
            'clipping.onboarding.require_email_verification' => false,
        ]);

        $this->actingAs($this->utilisateur())
            ->post('/email/verification-notification')
            ->assertRedirect('/dashboard')
            ->assertSessionHasNoErrors();
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
    public function the_forgotten_password_door_is_closed_when_no_mail_can_leave(): void
    {
        /*
         * La page où l'on arrive justement parce qu'on ne peut plus entrer.
         * Tant qu'aucun lien ne peut partir, elle n'a rien à offrir : le lien
         * qui y menait est masqué sur la page de connexion, et la route
         * elle-même reconduit à la connexion.
         */
        config(['mail.default' => 'log']);

        $this->utilisateur(['email' => 'maya@clip.test']);

        $this->get('/forgot-password')->assertRedirect(route('login'));

        $this->post('/forgot-password', ['email' => 'maya@clip.test'])
            ->assertRedirect(route('login'))
            ->assertSessionHasNoErrors();
    }

    #[Test]
    public function the_login_page_offers_no_link_it_cannot_honour(): void
    {
        // Mieux vaut ne rien promettre que d'expliquer pourquoi on ne tient
        // pas sa promesse.
        config(['mail.default' => 'log']);

        $this->get(route('login'))
            ->assertSuccessful()
            ->assertDontSee(route('password.request', absolute: false), escape: false);

        config(['mail.default' => 'smtp']);

        $this->get(route('login'))
            ->assertSuccessful()
            ->assertSee(route('password.request', absolute: false), escape: false);
    }

    #[Test]
    public function a_forgotten_password_does_not_crash_when_the_transport_fails(): void
    {
        config(['mail.default' => 'smtp']);

        $this->utilisateur(['email' => 'maya@clip.test']);

        Mail::shouldReceive('mailer')->andThrow(new TransportException('refus de connexion'));

        // Là, en revanche, le message a sa place : la personne a bien demandé
        // un lien, et la porte était ouverte. C'est l'envoi qui a échoué.
        $this->post('/forgot-password', ['email' => 'maya@clip.test'])
            ->assertRedirect()
            ->assertSessionHasErrors('email');
    }

    #[Test]
    public function a_verified_account_is_simply_sent_home(): void
    {
        // Rien à renvoyer : ni erreur, ni message d'envoi.
        config(['mail.default' => 'smtp']);

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
    public function the_six_boxes_are_submitted_as_the_form_sends_them(): void
    {
        /*
         * La panne vécue, et la seule qui compte ici : le formulaire
         * recopiait les six cases dans un champ caché par Alpine, et
         * désactivait le bouton tant que ce champ n'était pas rempli. Alpine
         * ne démarrant pas sur cet écran, le bouton restait inerte.
         *
         * La base de production le disait sans ambiguïté : zéro tentative
         * pour un code demandé. Rien n'avait jamais été soumis — le message
         * « code incorrect » n'y était pour rien, et chercher du côté du
         * code lui-même ne menait nulle part.
         *
         * D'où ce test : le navigateur envoie six champs, et c'est cette
         * forme-là qui doit marcher. Sans JavaScript du tout.
         */
        $this->piloteSms(simule: true);

        $user = $this->utilisateur(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->post(route('phone.send'), ['phone' => '0612345678', 'phone_country' => 'FR']);

        $code = session('codeSimule');

        $this->actingAs($user->fresh())
            ->post(route('phone.confirm'), ['code' => str_split($code)])
            ->assertSessionHasNoErrors();

        $this->assertTrue($user->fresh()->hasVerifiedPhone());
    }

    #[Test]
    public function an_incomplete_code_is_refused_without_counting_as_an_attempt(): void
    {
        // Une case oubliée n'est pas une tentative de devinette : la
        // compter rapprocherait du blocage quelqu'un qui n'a rien deviné.
        $this->piloteSms(simule: true);

        $user = $this->utilisateur(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->post(route('phone.send'), ['phone' => '0612345678', 'phone_country' => 'FR']);

        $this->actingAs($user->fresh())
            ->post(route('phone.confirm'), ['code' => ['1', '2', '3', '', '', '']])
            ->assertSessionHasErrors('code');

        $this->assertSame(0, $user->fresh()->phone_code_attempts);
    }

    #[Test]
    public function the_form_can_be_submitted_without_javascript(): void
    {
        /*
         * L'invariant qui avait cédé, vérifié sur le balisage lui-même.
         *
         * Les tests côté serveur postaient le code directement : ils
         * passaient tous pendant que l'écran ne soumettait jamais rien. Ce
         * qu'ils ne voyaient pas, c'était le formulaire — un champ caché
         * rempli par Alpine, et un bouton désactivé tant qu'il restait vide.
         *
         * Trois propriétés suffisent à garantir qu'un navigateur peut
         * soumettre sans JavaScript, et ce sont elles qu'on garde : des
         * champs réellement nommés, un bouton jamais désactivé, et aucune
         * valeur qui dépende d'une liaison dynamique.
         */
        $this->piloteSms(simule: true);

        $user = $this->utilisateur(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->post(route('phone.send'), ['phone' => '0612345678', 'phone_country' => 'FR']);

        $html = $this->actingAs($user->fresh())
            ->get(route('phone.verify'))
            ->assertSuccessful()
            ->getContent();

        $formulaire = $this->formulaireDuCode($html);

        $this->assertSame(
            6,
            substr_count($formulaire, 'name="code[]"'),
            'Les six cases doivent être des champs nommés, pas une façade.',
        );

        $this->assertStringNotContainsString('disabled', $formulaire,
            'Un bouton désactivé par une liaison dynamique est inerte sans JavaScript.');

        $this->assertStringNotContainsString(':value=', $formulaire,
            'La valeur soumise ne doit dépendre d’aucune liaison dynamique.');

        $this->assertStringNotContainsString('x-model', $formulaire,
            'Les cases ne doivent pas tenir leur valeur d’un état JavaScript.');
    }

    /** Le formulaire de saisie du code, isolé du reste de la page. */
    protected function formulaireDuCode(string $html): string
    {
        $debut = strpos($html, route('phone.confirm'));

        $this->assertNotFalse($debut, 'Le formulaire du code est introuvable.');

        $fin = strpos($html, '</form>', $debut);

        $this->assertNotFalse($fin, 'Le formulaire du code n’est pas refermé.');

        return substr($html, $debut, $fin - $debut);
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

    #[Test]
    public function the_profile_does_not_nag_about_email_while_the_control_is_suspended(): void
    {
        // Un avertissement sans objet et un lien qui ne mène à rien, puisque
        // aucun e-mail ne part : retirés tant que le contrôle est suspendu.
        config(['clipping.onboarding.require_email_verification' => false]);

        $this->actingAs($this->utilisateur(['email_verified_at' => null]))
            ->get(route('profile.edit'))
            ->assertSuccessful()
            ->assertDontSee('pas encore confirmée');
    }

    #[Test]
    public function the_profile_still_asks_when_the_control_is_on(): void
    {
        config(['clipping.onboarding.require_email_verification' => true]);

        $this->actingAs($this->utilisateur(['email_verified_at' => null]))
            ->get(route('profile.edit'))
            ->assertSuccessful()
            ->assertSee('pas encore confirmée');
    }
}
