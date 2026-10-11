<?php

namespace Tests\Feature\Messaging;

use App\Enums\ModerationAction;
use App\Enums\UserRole;
use App\Events\MessagePosted;
use App\Filament\Actions\PasswordResetLinkAction;
use App\Filament\Pages\Messagerie;
use App\Filament\Pages\SendBroadcast;
use App\Filament\Resources\Clippers\Pages\ListClippers;
use App\Livewire\Messages;
use App\Models\Conversation;
use App\Models\ModerationLog;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La messagerie entre l'équipe et les utilisateurs, et le lien de
 * réinitialisation qui remplace l'e-mail de mot de passe oublié.
 */
class MessagingTest extends TestCase
{
    use RefreshDatabase;

    protected function clipper(array $attributs = []): User
    {
        return User::factory()->create(array_merge([
            'role' => UserRole::Clipper,
            'email_verified_at' => now(),
        ], $attributs));
    }

    protected function staff(UserRole $role = UserRole::SuperAdmin): User
    {
        return User::factory()->create([
            'role' => $role,
            // 2FA déjà configurée, sinon le panel redirige vers sa mise en place.
            'app_authentication_secret' => 'JBSWY3DPEHPK3PXP',
        ]);
    }

    #[Test]
    public function a_user_writes_to_the_team_and_the_screens_are_told(): void
    {
        Event::fake([MessagePosted::class]);

        $clipper = $this->clipper();

        Livewire::actingAs($clipper)
            ->test(Messages::class)
            ->set('body', "  Mon versement n'est pas arrivé.  ")
            ->call('send')
            ->assertHasNoErrors()
            ->assertSet('body', '')
            ->assertSee("Mon versement n'est pas arrivé.");

        $message = Conversation::for($clipper)->messages()->sole();

        $this->assertFalse($message->from_staff);
        $this->assertSame("Mon versement n'est pas arrivé.", $message->body);
        Event::assertDispatched(MessagePosted::class, fn ($e) => $e->message->is($message));
    }

    #[Test]
    public function the_page_answers_for_clippers_and_creators(): void
    {
        $this->actingAs($this->clipper())->get(route('messages.index'))->assertOk()->assertSee('Messages');

        $createur = User::factory()->create(['role' => UserRole::Creator, 'email_verified_at' => now()]);
        $this->actingAs($createur)->get(route('messages.index'))->assertOk();
    }

    #[Test]
    public function the_team_is_sent_to_its_inbox(): void
    {
        $this->actingAs($this->staff(UserRole::Moderator))
            ->get(route('messages.index'))
            ->assertRedirect('/admin/messagerie');
    }

    #[Test]
    public function a_user_never_sees_someone_elses_thread(): void
    {
        $autre = $this->clipper();
        Conversation::for($autre)->post($autre, 'Message privé de quelqu’un d’autre');

        Livewire::actingAs($this->clipper())
            ->test(Messages::class)
            ->assertDontSee('Message privé de quelqu’un d’autre');
    }

    #[Test]
    public function an_empty_or_oversized_message_is_refused(): void
    {
        $test = Livewire::actingAs($this->clipper())->test(Messages::class);

        $test->set('body', '   ')->call('send')->assertHasErrors('body');
        $test->set('body', str_repeat('a', 2001))->call('send')->assertHasErrors('body');

        $this->assertSame(0, \App\Models\Message::count());
    }

    #[Test]
    public function sending_is_throttled(): void
    {
        Event::fake([MessagePosted::class]);

        $test = Livewire::actingAs($this->clipper())->test(Messages::class);

        foreach (range(1, 10) as $i) {
            $test->set('body', "message $i")->call('send')->assertHasNoErrors();
        }

        $test->set('body', 'un de trop')->call('send')->assertHasErrors('body');

        $this->assertSame(10, \App\Models\Message::count());
    }

    #[Test]
    public function the_unread_badge_counts_team_replies_until_the_thread_is_opened(): void
    {
        $clipper = $this->clipper();
        $equipe = $this->staff();
        $conversation = Conversation::for($clipper);
        $conversation->post($equipe, 'Réponse 1');
        $conversation->post($equipe, 'Réponse 2');

        $this->actingAs($clipper)
            ->get(route('notifications.index'))
            ->assertSee('2 non lus', escape: false);

        // Ouvrir le fil marque lu.
        $this->get(route('messages.index'))->assertOk();

        $this->assertSame(0, $conversation->unreadForUser()->count());
    }

    #[Test]
    public function the_team_replies_from_the_back_office(): void
    {
        Event::fake([MessagePosted::class]);

        $clipper = $this->clipper();
        $conversation = Conversation::for($clipper);
        $conversation->post($clipper, 'Bonjour, une question');

        $this->assertSame('1', Messagerie::getNavigationBadge());

        $moderateur = $this->staff(UserRole::Moderator);

        Livewire::actingAs($moderateur)
            ->test(Messagerie::class)
            ->assertSee($clipper->displayName())
            ->call('open', $conversation->getKey())
            ->assertSee('Bonjour, une question')
            ->set('reply', 'On regarde ça.')
            ->call('sendReply')
            ->assertHasNoErrors();

        $reponse = $conversation->messages()->latest('id')->first();

        $this->assertTrue($reponse->from_staff);
        $this->assertSame('On regarde ça.', $reponse->body);

        // Le message du clippeur est lu par l'équipe.
        $this->assertSame(0, $conversation->unreadForStaff()->count());
        Event::assertDispatched(MessagePosted::class);
    }

    #[Test]
    public function the_team_can_write_first(): void
    {
        Event::fake([MessagePosted::class]);

        $clipper = $this->clipper(['pseudo' => 'maya.clips']);

        Livewire::actingAs($this->staff())
            ->test(Messagerie::class)
            ->set('search', 'maya')
            ->assertSee('maya.clips')
            ->call('startWith', $clipper->getKey())
            ->set('reply', 'Votre compte TikTok est à reconnecter.')
            ->call('sendReply')
            ->assertHasNoErrors();

        $this->assertSame(1, Conversation::for($clipper)->unreadForUser()->count());
    }

    #[Test]
    public function the_inbox_is_closed_to_users(): void
    {
        $this->actingAs($this->clipper())->get('/admin/messagerie')->assertForbidden();
    }

    #[Test]
    public function channels_are_open_only_to_their_owner_and_the_team(): void
    {
        $proprietaire = $this->clipper();
        $intrus = $this->clipper();
        $conversation = Conversation::for($proprietaire);

        $canaux = Broadcast::getChannels();
        $fil = $canaux['conversation.{conversation}'];
        $boite = $canaux['staff.inbox'];

        $this->assertTrue($fil($proprietaire, $conversation));
        $this->assertFalse($fil($intrus, $conversation));
        $this->assertTrue($fil($this->staff(UserRole::Moderator), $conversation));

        $this->assertTrue($boite($this->staff()));
        $this->assertFalse($boite($proprietaire));
    }

    #[Test]
    public function a_group_announcement_lands_in_each_chat(): void
    {
        /*
         * La page d'annonce n'envoyait que des e-mails : le site n'en
         * envoyant pas, rien n'arrivait. Elle écrit maintenant dans le fil de
         * chacun — et ne vise plus les seules adresses confirmées, qui sont
         * presque inexistantes tant que la confirmation est suspendue.
         */
        Event::fake([MessagePosted::class]);
        config(['mail.default' => 'log']);

        $a = $this->clipper(['email_verified_at' => null]);
        $b = $this->clipper();
        $banni = $this->clipper(['is_banned' => true]);
        $createur = User::factory()->create(['role' => UserRole::Creator]);

        Livewire::actingAs($this->staff())
            ->test(SendBroadcast::class)
            ->set('data.audience', 'clippers')
            ->set('data.subject', 'Nouvelle campagne')
            ->set('data.body', 'Elle ouvre demain.')
            ->set('data.action_label', 'Voir')
            ->set('data.action_url', 'https://echoclip.fr/campagnes')
            ->call('send')
            ->assertHasNoErrors();

        foreach ([$a, $b] as $clipper) {
            $message = Conversation::for($clipper)->messages()->sole();
            $this->assertTrue($message->from_staff);
            $this->assertSame("Nouvelle campagne\n\nElle ouvre demain.\n\nVoir : https://echoclip.fr/campagnes", $message->body);
            $this->assertSame(1, $clipper->notifications()->count());
        }

        $this->assertSame(0, Conversation::for($banni)->messages()->count());
        $this->assertSame(0, Conversation::for($createur)->messages()->count());
        Event::assertDispatchedTimes(MessagePosted::class, 2);
    }

    #[Test]
    public function messages_older_than_three_months_are_erased(): void
    {
        $clipper = $this->clipper();
        $ancien = $this->clipper();

        $conversation = Conversation::for($clipper);
        $vieux = $conversation->post($clipper, 'Il y a quatre mois');
        $vieux->forceFill(['created_at' => now()->subMonths(4)])->save();
        $conversation->post($clipper, 'Hier');

        $fil = Conversation::for($ancien);
        $fil->post($ancien, 'Très vieux')->forceFill(['created_at' => now()->subDays(91)])->save();

        $this->artisan('messages:purge')->assertSuccessful();

        $this->assertSame(['Hier'], $conversation->messages()->pluck('body')->all());

        // Un fil vidé quitte la boîte de réception de l'équipe.
        $this->assertNull($fil->fresh()->last_message_at);
        $this->assertNotNull($conversation->fresh()->last_message_at);
    }

    #[Test]
    public function the_purge_refuses_a_zero_duration(): void
    {
        $clipper = $this->clipper();
        Conversation::for($clipper)->post($clipper, 'À garder');

        $this->artisan('messages:purge', ['--days' => 0])->assertFailed();

        $this->assertSame(1, \App\Models\Message::count());
    }

    #[Test]
    public function the_super_admin_gets_a_working_reset_link(): void
    {
        $clipper = $this->clipper();

        Livewire::actingAs($this->staff())
            ->test(ListClippers::class)
            ->callAction(TestAction::make('passwordResetLink')->table($clipper))
            ->assertNotified();

        $this->assertTrue(ModerationLog::query()
            ->where('action', ModerationAction::PasswordResetLinkIssued)
            ->where('subject_id', $clipper->getKey())
            ->exists());

        // Le lien produit est accepté par le formulaire de réinitialisation.
        $this->actingAs($this->staff());
        $lien = PasswordResetLinkAction::linkFor($clipper);
        auth()->logout();

        $this->get($lien)->assertOk();
    }

    #[Test]
    public function a_reset_link_is_never_offered_to_a_moderator_or_for_the_team(): void
    {
        $clipper = $this->clipper();

        $this->actingAs($this->staff(UserRole::Moderator));
        $this->assertFalse(PasswordResetLinkAction::allowed($clipper));

        $this->actingAs($this->staff());
        $this->assertTrue(PasswordResetLinkAction::allowed($clipper));
        $this->assertFalse(PasswordResetLinkAction::allowed($this->staff(UserRole::Moderator)));
    }
}
