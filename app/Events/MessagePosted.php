<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Un message vient d'être posté : les écrans ouverts se rafraîchissent.
 *
 * Diffusé par la file d'attente plutôt qu'à l'envoi : si le serveur
 * WebSocket est arrêté, le message part quand même — seule l'arrivée en
 * direct est perdue, et les pages se rattrapent par leur relevé régulier.
 * Une diffusion immédiate ferait échouer l'envoi avec elle.
 *
 * Le corps du message ne voyage pas : l'événement dit seulement « il y a du
 * nouveau dans tel fil », et chaque écran relit le fil avec ses propres
 * droits. Rien ne peut fuiter par le canal.
 */
class MessagePosted implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public Message $message) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversation.'.$this->message->conversation_id),
            new PrivateChannel('staff.inbox'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.posted';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->message->conversation_id,
            'from_staff' => $this->message->from_staff,
        ];
    }
}
