<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\Clip;
use App\Models\Payout;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Supprime définitivement des comptes désignés par leur adresse.
 *
 * Une commande plutôt qu'un `DELETE` à la main, pour trois raisons qu'une
 * requête SQL ne couvre pas : elle montre ce qu'elle va faire avant de le
 * faire, elle refuse de laisser la plateforme sans super administrateur, et
 * elle s'arrête si un compte porte des gains ou un versement — un clippeur
 * payé ne s'efface pas, son historique est la preuve de ce qu'on lui a versé.
 *
 * Suppression définitive et non logique : il s'agit de faire le ménage dans
 * des comptes d'essai, et un compte en corbeille garde son adresse, donc
 * empêche de se réinscrire avec.
 */
class PurgeUsersCommand extends Command
{
    protected $signature = 'users:purge
        {motif : Fragment recherché dans l\'adresse e-mail}
        {--force : Supprime réellement, au lieu de seulement lister}';

    protected $description = 'Supprime définitivement les comptes dont l\'adresse contient un fragment donné.';

    public function handle(): int
    {
        $motif = (string) $this->argument('motif');

        if (trim($motif) === '') {
            $this->error('Le motif est vide : refus, cela viserait tous les comptes.');

            return self::FAILURE;
        }

        $comptes = User::withTrashed()
            ->where('email', 'like', '%'.$motif.'%')
            ->get();

        if ($comptes->isEmpty()) {
            $this->info('Aucun compte dont l\'adresse contient « '.$motif.' ».');

            return self::SUCCESS;
        }

        $this->table(
            ['id', 'rôle', 'adresse', 'nom', 'clips', 'gains', 'versements', 'état'],
            $comptes->map(fn (User $u) => [
                $u->getKey(),
                $u->role->value,
                $u->email,
                $u->name,
                Clip::where('user_id', $u->getKey())->count(),
                number_format(Clip::where('user_id', $u->getKey())->sum('earned_cents') / 100, 2, ',', ' ').' €',
                Payout::where('user_id', $u->getKey())->count(),
                $u->trashed() ? 'en corbeille' : 'actif',
            ])->all(),
        );

        if ($bloquants = $this->avecDeLArgent($comptes)) {
            $this->error('Refus : ces comptes ont des gains ou des versements, leur historique est une preuve.');
            $this->line('  '.$bloquants->implode(', '));

            return self::FAILURE;
        }

        if (! $this->resteraUnSuperAdmin($comptes)) {
            $this->error('Refus : la suppression ne laisserait aucun super administrateur.');
            $this->line('  Personne ne pourrait plus ouvrir le back-office, ni se redonner le rôle.');

            return self::FAILURE;
        }

        if (! $this->option('force')) {
            $this->newLine();
            $this->warn('Rien n\'a été supprimé. Relancez avec --force pour agir.');

            return self::SUCCESS;
        }

        $nombre = $comptes->count();

        foreach ($comptes as $compte) {
            $compte->forceDelete();
        }

        $this->newLine();
        $this->info($nombre.' compte'.($nombre > 1 ? 's' : '').' supprimé'.($nombre > 1 ? 's' : '').' définitivement.');

        return self::SUCCESS;
    }

    /**
     * Les comptes qu'on refuse d'effacer parce que de l'argent y est passé.
     *
     * @param  Collection<int, User>  $comptes
     * @return Collection<int, string>|null
     */
    protected function avecDeLArgent(Collection $comptes): ?Collection
    {
        $bloquants = $comptes
            ->filter(fn (User $u) => Clip::where('user_id', $u->getKey())->sum('earned_cents') > 0
                || Payout::where('user_id', $u->getKey())->exists())
            ->map(fn (User $u) => $u->email);

        return $bloquants->isEmpty() ? null : $bloquants->values();
    }

    /**
     * Restera-t-il de quoi ouvrir le back-office ?
     *
     * Le contrôle est fait avant la suppression, pas après : constater la
     * perte une fois les comptes effacés n'avance à rien, puisque se redonner
     * le rôle demande justement d'entrer dans le back-office.
     *
     * @param  Collection<int, User>  $comptes
     */
    protected function resteraUnSuperAdmin(Collection $comptes): bool
    {
        return User::where('role', UserRole::SuperAdmin)
            ->whereNotIn('id', $comptes->modelKeys())
            ->exists();
    }
}
