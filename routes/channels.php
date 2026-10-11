<?php

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Canaux privés de la messagerie
|--------------------------------------------------------------------------
|
| Chaque abonnement passe par ici avant d'être accepté par le serveur
| WebSocket. Un fil n'est ouvert qu'à son utilisateur et à l'équipe ; la
| boîte de réception, à l'équipe seule.
|
*/

Broadcast::channel('conversation.{conversation}', function (User $user, Conversation $conversation) {
    return $user->isStaff() || $conversation->user_id === $user->getKey();
});

Broadcast::channel('staff.inbox', fn (User $user) => $user->isStaff());
