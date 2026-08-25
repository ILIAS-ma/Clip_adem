<?php

namespace App\Console\Commands;

use App\Services\Social\SocialAccountLinker;
use Illuminate\Console\Command;

/**
 * Prolonge les jetons avant expiration.
 *
 * Sans ce passage planifié, un jeton expiré ne se découvre qu'au moment où la
 * synchronisation renvoie des 401 — c'est-à-dire après avoir cessé de compter
 * les vues d'un clippeur qui, lui, n'en sait rien.
 */
class RefreshSocialTokensCommand extends Command
{
    protected $signature = 'social:refresh-tokens {--days=7 : Fenêtre d\'anticipation}';

    protected $description = 'Rafraîchit les jetons OAuth qui approchent de leur expiration.';

    public function handle(SocialAccountLinker $linker): int
    {
        $accounts = $linker->expiring((int) $this->option('days'));

        if ($accounts->isEmpty()) {
            $this->info('Aucun jeton à prolonger.');

            return self::SUCCESS;
        }

        $ok = 0;
        $failed = 0;

        foreach ($accounts as $account) {
            if ($linker->refresh($account)) {
                $ok++;

                continue;
            }

            $failed++;
            $this->warn(sprintf(
                'Compte #%d (%s, @%s) marqué à reconnecter.',
                $account->getKey(),
                $account->platform->label(),
                $account->handle,
            ));
        }

        $this->info("{$ok} jeton(s) prolongé(s), {$failed} compte(s) à reconnecter.");

        return self::SUCCESS;
    }
}
