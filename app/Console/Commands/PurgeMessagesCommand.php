<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Console\Command;

/**
 * Efface les messages de plus de trois mois.
 *
 * Le chat sert à régler des questions du moment — un versement, un clip
 * refusé — pas à archiver. Le garder sans limite accumulerait des données
 * personnelles dont plus personne n'a l'usage.
 *
 * Glissant plutôt qu'à date fixe : chaque message part trois mois après son
 * envoi. Une remise à zéro trimestrielle de tout le chat aurait effacé, le
 * jour venu, une conversation commencée la veille.
 *
 * Les fils vidés restent en base mais quittent la boîte de réception de
 * l'équipe : ils n'ont plus rien à montrer.
 */
class PurgeMessagesCommand extends Command
{
    protected $signature = 'messages:purge
        {--days=90 : Âge, en jours, au-delà duquel un message est effacé}';

    protected $description = 'Efface les messages de la messagerie plus anciens que trois mois.';

    public function handle(): int
    {
        $jours = (int) $this->option('days');

        if ($jours < 1) {
            $this->error('Durée invalide : refus, cela effacerait tout le chat.');

            return self::FAILURE;
        }

        $effaces = Message::query()->where('created_at', '<', now()->subDays($jours))->delete();

        Conversation::query()
            ->whereNotNull('last_message_at')
            ->whereDoesntHave('messages')
            ->update(['last_message_at' => null]);

        $this->info($effaces.' message(s) de plus de '.$jours.' jours effacé(s).');

        return self::SUCCESS;
    }
}
