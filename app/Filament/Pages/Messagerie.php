<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Events\MessagePosted;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * La boîte de réception de l'équipe.
 *
 * Tous les fils, du plus récent au plus ancien, ceux qui attendent une
 * réponse marqués d'un compteur. Ouverte aux modérateurs comme au
 * super-administrateur : répondre à une question n'engage pas d'argent.
 *
 * On peut aussi écrire le premier : chercher quelqu'un et lui ouvrir un fil,
 * pour le prévenir d'un clip refusé ou d'un compte à reconnecter.
 */
class Messagerie extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'Messagerie';

    protected static ?string $title = 'Messagerie';

    protected static ?string $slug = 'messagerie';

    protected static string|\UnitEnum|null $navigationGroup = 'Communication';

    protected string $view = 'filament.pages.messagerie';

    /** L'équipe ne s'écrit pas à elle-même : elle n'a pas de fil. */
    protected const STAFF_ROLES = [UserRole::SuperAdmin, UserRole::Moderator];

    public ?int $openId = null;

    public string $reply = '';

    public string $search = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->isStaff() ?? false;
    }

    /** Le nombre de messages qui attendent l'équipe, dans le menu. */
    public static function getNavigationBadge(): ?string
    {
        $attente = Message::query()->where('from_staff', false)->whereNull('read_at')->count();

        return $attente > 0 ? (string) $attente : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public function getListeners(): array
    {
        return ['echo-private:staff.inbox,.message.posted' => '$refresh'];
    }

    /** @return Collection<int, Conversation> */
    public function conversations(): Collection
    {
        return Conversation::query()
            ->whereNotNull('last_message_at')
            ->when(mb_strlen(trim($this->search)) >= 2, fn (Builder $q) => $q->whereHas(
                'user',
                fn (Builder $u) => $this->matching($u),
            ))
            ->with(['user', 'latestMessage'])
            ->withCount('unreadForStaff')
            ->orderByDesc('last_message_at')
            ->limit(60)
            ->get();
    }

    /**
     * Les personnes qui correspondent à la recherche et n'ont pas encore de
     * fil : c'est par là qu'on écrit le premier.
     *
     * @return Collection<int, User>
     */
    public function newRecipients(): Collection
    {
        if (mb_strlen(trim($this->search)) < 2) {
            return collect();
        }

        return $this->matching(User::query())
            ->whereNotIn('role', static::STAFF_ROLES)
            ->whereDoesntHave('conversation', fn (Builder $q) => $q->whereNotNull('last_message_at'))
            ->orderBy('name')
            ->limit(8)
            ->get();
    }

    public function open(int $conversationId): void
    {
        $this->openId = $conversationId;
        $this->resetValidation();
    }

    public function startWith(int $userId): void
    {
        $user = User::query()->whereNotIn('role', static::STAFF_ROLES)->findOrFail($userId);

        $this->openId = Conversation::for($user)->getKey();
        $this->search = '';
    }

    public function openConversation(): ?Conversation
    {
        return $this->openId ? Conversation::with('user')->find($this->openId) : null;
    }

    /** @return Collection<int, Message> */
    public function thread(Conversation $conversation): Collection
    {
        // Ouvrir le fil, c'est lire ce que l'utilisateur a écrit.
        $conversation->markReadBy(auth()->user());

        return $conversation->messages()
            ->with('sender:id,name,pseudo')
            ->latest('id')
            ->limit(150)
            ->get()
            ->reverse()
            ->values();
    }

    public function sendReply(): void
    {
        $conversation = $this->openConversation();

        if (! $conversation) {
            return;
        }

        $this->reply = trim($this->reply);

        $this->validate([
            'reply' => ['required', 'string', 'max:'.Message::MAX_LENGTH],
        ], [
            'reply.required' => 'Écrivez une réponse.',
            'reply.max' => 'Message trop long ('.Message::MAX_LENGTH.' caractères au plus).',
        ]);

        MessagePosted::dispatch($conversation->post(auth()->user(), $this->reply));

        $this->reset('reply');
    }

    protected function matching(Builder $query): Builder
    {
        $terme = '%'.trim($this->search).'%';

        return $query->where(fn (Builder $q) => $q
            ->where('name', 'like', $terme)
            ->orWhere('pseudo', 'like', $terme)
            ->orWhere('email', 'like', $terme));
    }
}
