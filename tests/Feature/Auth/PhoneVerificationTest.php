<?php

namespace Tests\Feature\Auth;

use App\Contracts\SmsSender;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Auth\PhoneVerification;
use App\Services\Sms\SmsManager;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Vérification du numéro de téléphone.
 *
 * Un numéro confirmé est le seul identifiant réellement coûteux à multiplier :
 * une adresse jetable s'obtient en dix secondes, une ligne téléphonique non.
 * C'est lui qui rend un bannissement dissuasif, donc ce qui protège les
 * budgets d'un fraudeur qui créerait dix comptes.
 *
 * Trois choses doivent tenir, et chacune défend contre une attaque
 * différente : le code n'est jamais lisible en base, on ne peut pas faire
 * pleuvoir des SMS sur le numéro d'un tiers, et on ne peut pas deviner un
 * code à force d'essais.
 */
class PhoneVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected array $envoyes = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->envoyes = [];

        // Pilote de test : on retient les messages sans rien envoyer. Sans
        // lui, chaque exécution de la suite coûterait de l'argent.
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

    protected function user(array $attributs = []): User
    {
        return User::factory()->create(array_merge([
            'role' => UserRole::Clipper,
            'email_verified_at' => now(),
            'country' => 'FR',
        ], $attributs));
    }

    /** Le code tel qu'il est parti dans le message. */
    protected function codeEnvoye(): string
    {
        preg_match('/\b(\d{6})\b/', $this->envoyes[0]['message'] ?? '', $m);

        return $m[1] ?? '';
    }

    #[Test]
    public function a_code_is_sent_and_accepted(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->post(route('phone.send'), ['phone' => '06 12 34 56 78', 'phone_country' => 'FR'])
            ->assertRedirect(route('phone.verify'));

        $this->assertCount(1, $this->envoyes);
        $this->assertSame('+33612345678', $this->envoyes[0]['to']);

        $this->actingAs($user->fresh())
            ->post(route('phone.confirm'), ['code' => $this->codeEnvoye()])
            ->assertRedirect(route('profile.edit'));

        $this->assertTrue($user->fresh()->hasVerifiedPhone());
    }

    #[Test]
    public function the_code_is_never_readable_in_the_database(): void
    {
        /*
         * Un vidage de la base ne doit pas suffire à prendre la main sur un
         * compte en cours de vérification. Le code y est haché, comme un mot
         * de passe.
         */
        $user = $this->user();

        $this->actingAs($user)->post(route('phone.send'), [
            'phone' => '0612345678', 'phone_country' => 'FR',
        ]);

        $enregistre = (string) $user->fresh()->phone_code_hash;
        $code = $this->codeEnvoye();

        $this->assertNotSame($code, $enregistre);
        $this->assertStringNotContainsString($code, $enregistre);
        $this->assertTrue(Hash::check($code, $enregistre));
    }

    #[Test]
    public function a_wrong_code_is_refused(): void
    {
        $user = $this->user();

        $this->actingAs($user)->post(route('phone.send'), [
            'phone' => '0612345678', 'phone_country' => 'FR',
        ]);

        $faux = $this->codeEnvoye() === '000000' ? '111111' : '000000';

        $this->actingAs($user->fresh())
            ->post(route('phone.confirm'), ['code' => $faux])
            ->assertSessionHasErrors('code');

        $this->assertFalse($user->fresh()->hasVerifiedPhone());
    }

    #[Test]
    public function guessing_stops_after_a_few_attempts(): void
    {
        /*
         * Six chiffres font un million de combinaisons, mais quelques
         * milliers de tentatives suffisent à en trouver une si rien ne les
         * compte. Passé la limite, même le bon code est refusé : il faut en
         * redemander un.
         */
        $user = $this->user();

        $this->actingAs($user)->post(route('phone.send'), [
            'phone' => '0612345678', 'phone_country' => 'FR',
        ]);

        $bon = $this->codeEnvoye();
        $verification = app(PhoneVerification::class);

        for ($i = 0; $i < (int) config('clipping.phone.max_attempts'); $i++) {
            $verification->verifier($user->fresh(), '000000');
        }

        $this->assertFalse($verification->verifier($user->fresh(), $bon));
        $this->assertFalse($user->fresh()->hasVerifiedPhone());
    }

    #[Test]
    public function an_expired_code_no_longer_works(): void
    {
        $user = $this->user();

        $this->actingAs($user)->post(route('phone.send'), [
            'phone' => '0612345678', 'phone_country' => 'FR',
        ]);

        $bon = $this->codeEnvoye();

        $this->travel((int) config('clipping.phone.code_minutes') + 1)->minutes();

        $this->assertFalse(app(PhoneVerification::class)->verifier($user->fresh(), $bon));
    }

    #[Test]
    public function one_cannot_rain_messages_on_a_number(): void
    {
        // Chaque envoi coûte de l'argent, et le numéro visé n'est pas
        // forcément celui de la personne qui clique.
        $user = $this->user();

        $this->actingAs($user)->post(route('phone.send'), [
            'phone' => '0612345678', 'phone_country' => 'FR',
        ]);

        $this->actingAs($user->fresh())
            ->post(route('phone.send'), ['phone' => '0612345678', 'phone_country' => 'FR'])
            ->assertSessionHasErrors('phone');

        $this->assertCount(1, $this->envoyes);
    }

    #[Test]
    public function a_number_serves_a_single_account(): void
    {
        /*
         * C'est tout l'intérêt du dispositif : sans unicité, la même ligne
         * couvrirait dix comptes, et le bannissement d'un fraudeur ne lui
         * coûterait rien.
         */
        $premier = $this->user(['phone' => '+33612345678', 'phone_verified_at' => now()]);
        $second = $this->user();

        $this->actingAs($second)
            ->post(route('phone.send'), ['phone' => '0612345678', 'phone_country' => 'FR'])
            ->assertSessionHasErrors('phone');

        $this->assertEmpty($this->envoyes);
        $this->assertNull($second->fresh()->phone);
        $this->assertTrue($premier->fresh()->hasVerifiedPhone());
    }

    public static function numeros(): array
    {
        return [
            'national français' => ['0612345678', 'FR', '+33612345678'],
            'avec espaces' => ['06 12 34 56 78', 'FR', '+33612345678'],
            'avec points' => ['06.12.34.56.78', 'FR', '+33612345678'],
            'déjà international' => ['+33612345678', 'FR', '+33612345678'],
            'préfixe 00' => ['0033612345678', 'FR', '+33612345678'],
            'belge' => ['0470123456', 'BE', '+32470123456'],
            'marocain' => ['0612345678', 'MA', '+212612345678'],
            'vide' => ['', 'FR', null],
            'texte' => ['appelez-moi', 'FR', null],
        ];
    }

    #[Test]
    #[DataProvider('numeros')]
    public function a_number_is_always_stored_the_same_way(string $saisi, string $pays, ?string $attendu): void
    {
        // « 0612… » et « +33612… » désignent le même abonné : sans
        // normalisation, la contrainte d'unicité ne verrait rien.
        $this->assertSame($attendu, PhoneNumber::normalise($saisi, $pays));
    }

    #[Test]
    public function the_displayed_number_is_masked(): void
    {
        // Un numéro complet affiché à l'écran se lit par-dessus l'épaule, et
        // se retrouve sur les captures d'écran qu'on nous envoie.
        $masque = PhoneNumber::masque('+33612345678');

        $this->assertStringStartsWith('+336', $masque);
        $this->assertStringEndsWith('78', $masque);
        $this->assertStringNotContainsString('12345', $masque);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
