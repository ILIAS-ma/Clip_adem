<?php

namespace App\Services\Referrals;

use App\Models\BudgetTransaction;
use App\Models\Clip;
use App\Models\ReferralCommission;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Parrainage entre clippeurs.
 *
 * LA règle du dispositif, celle dont tout le reste découle : **la commission
 * ne sort jamais du budget d'une campagne**. Elle est payée par la plateforme
 * sur sa marge. Sinon le créateur financerait la croissance de la plateforme
 * sans le savoir, et le budget qu'il a provisionné ne servirait plus
 * entièrement à ses vues — ce que le contrôle d'encaissement existe justement
 * pour garantir.
 *
 * Le filleul n'est jamais amputé non plus : il touche exactement ce que ses
 * vues valent. La commission s'ajoute par-dessus.
 */
class ReferralService
{
    /** Nom du cookie qui porte le code entre l'arrivée et l'inscription. */
    public const COOKIE = 'clip_referral';

    /**
     * Code partagé par un clippeur.
     *
     * Attribué à la demande plutôt qu'à l'inscription : la majorité des
     * comptes ne parraineront jamais personne, et une colonne unique remplie
     * pour rien complique les migrations pour rien.
     */
    public function codeFor(User $user): string
    {
        if ($user->referral_code) {
            return $user->referral_code;
        }

        // Alphabet sans O/0 ni I/1 : ces codes se recopient à la main depuis
        // une story, et une confusion coûte un parrainage.
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $code = substr($user->pseudo ? Str::upper(Str::slug($user->pseudo, '')) : '', 0, 4);
            $code .= collect(range(1, 8 - strlen($code)))
                ->map(fn () => $alphabet[random_int(0, strlen($alphabet) - 1)])
                ->join('');
        } while (User::where('referral_code', $code)->exists());

        $user->forceFill(['referral_code' => $code])->save();

        return $code;
    }

    /** Le lien complet à partager. */
    public function linkFor(User $user): string
    {
        return route('register', ['parrain' => $this->codeFor($user)]);
    }

    /**
     * Rattache un filleul à son parrain, à l'inscription.
     *
     * Le code peut venir de l'URL ou du cookie posé à l'arrivée : quelqu'un
     * clique un lien, regarde la page d'accueil, puis s'inscrit dix minutes
     * plus tard. Sans le cookie, le parrainage serait perdu à chaque fois.
     */
    public function attachFromRequest(User $user, Request $request): void
    {
        $code = $request->input('parrain')
            ?? $request->query('parrain')
            ?? $request->cookie(self::COOKIE);

        if (blank($code)) {
            return;
        }

        $this->attach($user, (string) $code);
    }

    public function attach(User $user, string $code): bool
    {
        $referrer = User::where('referral_code', Str::upper(trim($code)))->first();

        // On ne se parraine pas soi-même, et un compte banni ne recrute pas.
        if (! $referrer || $referrer->is($user) || $referrer->is_banned) {
            return false;
        }

        // Le parrain est figé à l'inscription : le laisser changer ensuite
        // permettrait de déplacer des commissions déjà acquises.
        if ($user->referred_by !== null) {
            return false;
        }

        $user->forceFill([
            'referred_by' => $referrer->getKey(),
            'referred_at' => now(),
        ])->save();

        return true;
    }

    /**
     * Verse la commission due au parrain pour un crédit de vues.
     *
     * Appelée après coup, jamais dans la transaction du budget : une écriture
     * de parrainage qui échouerait ne doit pas annuler le paiement des vues
     * d'un clippeur.
     */
    public function creditFor(BudgetTransaction $transaction): ?ReferralCommission
    {
        if ($transaction->amount_cents <= 0 || $transaction->user_id === null) {
            return null;
        }

        $referred = User::find($transaction->user_id);
        $referrer = $referred?->referrer;

        if (! $referrer || $referrer->is_banned) {
            return null;
        }

        $rateBp = (int) config('clipping.referrals.rate_bp');
        // Arrondi vers le bas : la plateforme ne paie jamais un centime de
        // plus que le barème, et jamais moins que zéro.
        $amount = max(0, intdiv($transaction->amount_cents * $rateBp, 10_000));

        if ($amount === 0) {
            return null;
        }

        try {
            return DB::transaction(fn () => ReferralCommission::create([
                'referrer_id' => $referrer->getKey(),
                'referred_id' => $referred->getKey(),
                'budget_transaction_id' => $transaction->getKey(),
                'amount_cents' => $amount,
                'base_cents' => $transaction->amount_cents,
                // Barème figé sur la ligne : le changer plus tard ne doit pas
                // réécrire les commissions déjà acquises.
                'rate_bp' => $rateBp,
            ]));
        } catch (QueryException $exception) {
            // Violation de l'index unique : la commission existe déjà. C'est
            // le garde-fou d'idempotence, un rejeu ne double pas la mise.
            return null;
        }
    }

    /**
     * Reprend les commissions versées sur des vues finalement invalidées.
     *
     * Par des lignes négatives, jamais par une suppression : le parrain doit
     * pouvoir comprendre pourquoi son total a baissé.
     */
    public function reverseForClip(Clip $clip, BudgetTransaction $reversal): void
    {
        // Toutes les commissions nées des crédits de ce clip, y compris les
        // reprises déjà écrites : leur somme dit ce qu'il reste à reprendre.
        $outstanding = ReferralCommission::query()
            ->whereIn('budget_transaction_id', BudgetTransaction::where('clip_id', $clip->getKey())->select('id'))
            ->get()
            ->groupBy('referrer_id')
            ->map(fn ($lines) => [
                'referrer_id' => $lines->first()->referrer_id,
                'referred_id' => $lines->first()->referred_id,
                'rate_bp' => $lines->first()->rate_bp,
                'amount_cents' => (int) $lines->sum('amount_cents'),
                'base_cents' => (int) $lines->sum('base_cents'),
            ])
            ->filter(fn (array $line) => $line['amount_cents'] > 0);

        foreach ($outstanding as $commission) {

            try {
                ReferralCommission::create([
                    'referrer_id' => $commission['referrer_id'],
                    'referred_id' => $commission['referred_id'],
                    'budget_transaction_id' => $reversal->getKey(),
                    'amount_cents' => -$commission['amount_cents'],
                    'base_cents' => -$commission['base_cents'],
                    'rate_bp' => $commission['rate_bp'],
                ]);
            } catch (QueryException $exception) {
                Log::warning('Reprise de commission de parrainage impossible', [
                    'referrer_id' => $commission['referrer_id'],
                    'clip_id' => $clip->getKey(),
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }
}
