<?php

namespace App\Livewire;

use App\Events\MessagePosted;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Le fil d'un utilisateur avec l'équipe.
 *
 * Les nouveaux messages arrivent par WebSocket ; la vue garde aussi un
 * relevé lent, pour le cas où la connexion temps réel est coupée — un
 * réseau d'entreprise qui bloque les WebSockets, un serveur Reverb
 * redémarré. Le direct est un confort, pas une condition.
 */
class Messages extends Component
{
    public string $body = '';

    /** Ce qu'on affiche du passé : assez pour reprendre le fil. */
    protected const HISTORY = 100;

    public function getListeners(): array
    {
        return [
            "echo-private:conversation.{$this->conversation->getKey()},.message.posted" => '$refresh',
        ];
    }

    #[Computed]
    public function conversation(): Conversation
    {
        return Conversation::for(auth()->user());
    }

    public function send(): void
    {
        $this->body = trim($this->body);

        $this->validate([
            'body' => ['required', 'string', 'max:'.Message::MAX_LENGTH],
        ], [
            'body.required' => 'Écrivez un message.',
            'body.max' => 'Message trop long ('.Message::MAX_LENGTH.' caractères au plus).',
        ]);

        // Une conversation, pas un mégaphone : dix messages par minute
        // suffisent à n'importe quel échange, et pas à inonder l'équipe.
        $cle = 'messages:'.auth()->id();

        if (RateLimiter::tooManyAttempts($cle, 10)) {
            $this->addError('body', 'Doucement : réessayez dans '.RateLimiter::availableIn($cle).' s.');

            return;
        }

        RateLimiter::hit($cle, 60);

        MessagePosted::dispatch($this->conversation->post(auth()->user(), $this->body));

        $this->reset('body');
    }

    public function render()
    {
        $conversation = $this->conversation;

        // Ouvrir le fil, c'est lire ce que l'équipe a écrit.
        $conversation->markReadBy(auth()->user());

        return view('livewire.messages', [
            'messages' => $conversation->messages()
                ->with('sender:id,name,pseudo,role')
                ->latest('id')
                ->limit(static::HISTORY)
                ->get()
                ->reverse()
                ->values(),
        ]);
    }
}
