<?php

namespace App\Console\Commands;

use App\Enums\CampaignStatus;
use App\Enums\Platform;
use App\Models\Campaign;
use App\Models\User;
use App\Services\Social\SocialProviderManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Contrôle avant ouverture.
 *
 * Le développement se fait volontairement les garde-fous baissés — e-mails
 * capturés en local, contrôles suspendus, fournisseurs simulés. Aucun de ces
 * réglages ne doit survivre à la mise en ligne, et un `.env` recopié tel quel
 * est la façon la plus banale de mettre un site ouvert en danger.
 *
 * Cette commande dit, en une page, ce qui bloque et ce qui inquiète. Elle rend
 * un code de sortie non nul s'il reste un point bloquant, pour qu'un script de
 * déploiement puisse s'arrêter dessus.
 */
class PreflightCommand extends Command
{
    protected $signature = 'clip:preflight';

    protected $description = 'Vérifie que la plateforme est prête à être ouverte au public.';

    /** @var array<int, array{0: string, 1: string, 2: string}> */
    protected array $rows = [];

    protected int $blockers = 0;

    protected int $warnings = 0;

    public function handle(SocialProviderManager $providers): int
    {
        $this->checkEnvironment();
        $this->checkGates();
        $this->checkMail();
        $this->checkSocial($providers);
        $this->checkPayments();
        $this->checkStorage();
        $this->checkData();

        $this->newLine();
        $this->table(['', 'Point', 'Constat'], $this->rows);
        $this->newLine();

        if ($this->blockers > 0) {
            $this->error(sprintf(
                '%d point(s) bloquant(s) et %d avertissement(s). Ne pas ouvrir en l\'état.',
                $this->blockers,
                $this->warnings,
            ));

            return self::FAILURE;
        }

        if ($this->warnings > 0) {
            $this->warn($this->warnings.' avertissement(s). Rien ne bloque, mais lisez-les.');

            return self::SUCCESS;
        }

        $this->info('Tout est en ordre.');

        return self::SUCCESS;
    }

    // ------------------------------------------------------------------

    protected function checkEnvironment(): void
    {
        $this->assert(
            'Environnement',
            app()->isProduction(),
            app()->environment(),
            'APP_ENV doit valoir « production » : c\'est lui qui coupe les fournisseurs simulés.',
        );

        $this->assert(
            'Affichage des erreurs',
            ! config('app.debug'),
            config('app.debug') ? 'APP_DEBUG=true' : 'désactivé',
            'APP_DEBUG=true expose la configuration, les requêtes SQL et des extraits de code au premier visiteur venu.',
        );

        $url = (string) config('app.url');

        $this->assert(
            'Adresse du site',
            str_starts_with($url, 'https://') && ! str_contains($url, 'localhost') && ! str_contains($url, '127.0.0.1'),
            $url ?: '(vide)',
            'APP_URL sert à fabriquer les liens des e-mails et de parrainage : en local, ils pointeront vers la machine du serveur.',
        );

        $this->assert(
            'Clé d\'application',
            filled(config('app.key')),
            filled(config('app.key')) ? 'présente' : 'absente',
            'Sans APP_KEY, les sessions et les IBAN chiffrés sont illisibles.',
        );
    }

    protected function checkGates(): void
    {
        $gates = [
            'require_email_verification' => 'Vérification d\'e-mail',
            'require_complete_profile' => 'Profil complet obligatoire',
            'require_admin_2fa' => '2FA administrateur',
            'require_creator_validation' => 'Validation des fiches créateur',
            'require_funded_campaigns' => 'Campagnes provisionnées',
        ];

        foreach ($gates as $key => $label) {
            $this->assert(
                $label,
                (bool) config("clipping.onboarding.{$key}"),
                config("clipping.onboarding.{$key}") ? 'actif' : 'SUSPENDU',
                match ($key) {
                    'require_email_verification' => 'Sans elle, une adresse jetable rend le bannissement inopérant.',
                    'require_admin_2fa' => 'Sans elle, un compte admin compromis donne accès aux paiements.',
                    'require_funded_campaigns' => 'Sans elle, la plateforme peut promettre de l\'argent qu\'elle n\'a pas reçu.',
                    'require_creator_validation' => 'Sans elle, n\'importe qui apparaît au catalogue sous le nom qu\'il veut.',
                    default => 'Un contrôle suspendu « le temps de voir l\'interface » ne doit pas survivre à l\'ouverture.',
                },
            );
        }
    }

    protected function checkMail(): void
    {
        $mailer = (string) config('mail.default');
        $host = (string) config('mail.mailers.smtp.host');

        $captured = in_array($mailer, ['log', 'array'], true)
            || in_array($host, ['127.0.0.1', 'localhost', 'mailpit', 'mailhog'], true);

        $this->assert(
            'Envoi des e-mails',
            ! $captured,
            $captured ? "capturé localement ({$mailer} / {$host})" : $mailer,
            'Aucun e-mail ne sortirait : ni confirmation d\'adresse, ni avis de versement, ni message groupé.',
        );
    }

    protected function checkSocial(SocialProviderManager $providers): void
    {
        foreach (Platform::cases() as $platform) {
            // En production, l'absence de clés lève une exception au premier
            // usage : la liaison de compte et le relevé des vues tomberaient
            // tous les deux, c'est-à-dire le produit entier.
            $configured = rescue(fn () => ! $providers->isSimulated($platform), false, report: false);

            $this->assert(
                'Intégration '.$platform->label(),
                $configured,
                $configured ? 'configurée' : 'AUCUNE CLÉ',
                'Sans identifiants, plus aucun clippeur ne peut lier son compte et aucune vue n\'est relevée.',
            );
        }

        $google = filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));

        $this->assert(
            'Connexion Google',
            $google,
            $google ? 'configurée' : 'absente',
            'Le bouton reste simplement caché. Rien ne casse.',
            blocking: false,
        );
    }

    protected function checkPayments(): void
    {
        $mode = (string) config('services.paypal.mode');
        $keys = filled(config('services.paypal.client_id')) && filled(config('services.paypal.client_secret'));

        $this->assert(
            'PayPal',
            $keys,
            $keys ? $mode : 'aucune clé',
            'Sans identifiants, aucun versement automatique ne peut partir.',
        );

        $this->assert(
            'PayPal en production',
            $mode === 'live',
            $mode,
            'En « sandbox », les versements ne quittent jamais le bac à sable.',
            blocking: $keys,
        );

        $this->assert(
            'Signature des webhooks PayPal',
            filled(config('services.paypal.webhook_id')),
            filled(config('services.paypal.webhook_id')) ? 'vérifiée' : 'PAYPAL_WEBHOOK_ID absent',
            'Sans elle, n\'importe qui peut déclarer un versement comme payé.',
        );
    }

    protected function checkStorage(): void
    {
        // Windows crée une jonction plutôt qu'un lien symbolique : `is_link()`
        // y répond faux alors que le dossier est bien servi. Le test se fait
        // donc sur l'existence, et le résultat est calculé une seule fois.
        clearstatcache(true, public_path('storage'));
        $linked = is_dir(public_path('storage')) || is_link(public_path('storage'));

        $this->assert(
            'Lien public du stockage',
            $linked,
            $linked ? 'en place' : 'absent',
            'Les pièces jointes des briefs seraient inaccessibles : lancez `php artisan storage:link`.',
        );

        // La file porte les notifications et les envois groupés. On ne peut pas
        // vérifier qu'un worker tourne, mais un tas de travaux anciens le dit.
        if (config('queue.default') === 'database' && Schema::hasTable('jobs')) {
            $stuck = DB::table('jobs')->where('created_at', '<', now()->subHour()->timestamp)->count();

            $this->assert(
                'File d\'attente',
                $stuck === 0,
                $stuck === 0 ? 'à jour' : $stuck.' travaux en souffrance depuis plus d\'une heure',
                'Personne ne consomme la file : les e-mails ne partent pas. Vérifiez que le cron `schedule:run` tourne.',
                blocking: false,
            );
        }
    }

    protected function checkData(): void
    {
        $exposed = Campaign::where('status', CampaignStatus::Active)->get()
            ->filter(fn (Campaign $campaign) => $campaign->exposureCents() > 0);

        $this->assert(
            'Solvabilité des campagnes',
            $exposed->isEmpty(),
            $exposed->isEmpty()
                ? 'aucun découvert'
                : $exposed->count().' campagne(s), '.number_format($exposed->sum(fn (Campaign $c) => $c->exposureCents()) / 100, 2, ',', ' ').' € à découvert',
            'Ces campagnes ont dépensé plus que ce qui a été encaissé : la différence sort de votre poche.',
            blocking: false,
        );

        $adminsWithout2fa = User::query()
            ->whereIn('role', ['super_admin', 'moderator'])
            ->whereNull('app_authentication_secret')
            ->count();

        $this->assert(
            '2FA du personnel',
            $adminsWithout2fa === 0,
            $adminsWithout2fa === 0 ? 'tous équipés' : $adminsWithout2fa.' compte(s) sans 2FA',
            'Ils seront renvoyés vers l\'écran de configuration à leur prochaine connexion.',
            blocking: false,
        );

        $demo = User::where('email', 'like', '%@clip.test')->count();

        $this->assert(
            'Comptes de démonstration',
            $demo === 0,
            $demo === 0 ? 'aucun' : $demo.' compte(s) en @clip.test',
            'Mots de passe publics et connus : à supprimer avant l\'ouverture.',
        );
    }

    // ------------------------------------------------------------------

    protected function assert(string $label, bool $ok, string $state, string $why, bool $blocking = true): void
    {
        if ($ok) {
            $this->rows[] = ['<fg=green>OK</>', $label, $state];

            return;
        }

        if ($blocking) {
            $this->blockers++;
            $this->rows[] = ['<fg=red>BLOQUE</>', $label, $state.' — '.$why];

            return;
        }

        $this->warnings++;
        $this->rows[] = ['<fg=yellow>ATTENTION</>', $label, $state.' — '.$why];
    }
}
