<?php

namespace App\Services\Referrals;

use App\Enums\ClipStatus;
use App\Models\Clip;
use App\Models\ReferralCommission;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * La prime de parrainage : un montant fixe par filleul qui poste assez de clips dans le mois.
 *
 * Elle est versée à la main par l'équipe, le 25 : on regarde qui remplit la condition, on vérifie, on verse.
 * Un filleul ne rapporte sa prime qu'une fois, tous mois confondus : « 7 € par clippeur amené », pas par mois.
 */
class ReferralBonusService
{
    public function amountCents(): int
    {
        return (int) config('clipping.referrals.bonus_cents');
    }

    public function minClips(): int
    {
        return (int) config('clipping.referrals.bonus_min_clips');
    }

    /**
     * Les filleuls qui remplissent la condition ce mois-là et dont la prime n'a jamais été versée.
     *
     * @return Collection<int, object{referred_id: int, referrer_id: int, referred: string, referrer: string, clips: int}>
     */
    public function qualifying(CarbonInterface $month): Collection
    {
        $from = $month->copy()->startOfMonth();
        $to = $month->copy()->endOfMonth();

        $counts = Clip::query()
            ->where('status', ClipStatus::Approved)
            ->whereBetween('posted_at', [$from, $to])
            ->whereIn('user_id', User::whereNotNull('referred_by')->where('is_banned', false)->select('id'))
            ->selectRaw('user_id, COUNT(*) as clips')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) >= ?', [$this->minClips()])
            ->pluck('clips', 'user_id');

        $paid = ReferralCommission::whereNotNull('bonus_month')->pluck('referred_id')->all();

        $referred = User::whereIn('id', $counts->keys())->with('referrer')->get()->keyBy('id');

        return $counts->map(function ($clips, $userId) use ($referred) {
            $filleul = $referred[$userId] ?? null;
            $parrain = $filleul?->referrer;

            if (! $filleul || ! $parrain || $parrain->is_banned) {
                return null;
            }

            return (object) [
                'referred_id' => (int) $userId,
                'referrer_id' => (int) $parrain->getKey(),
                'referred' => $filleul->displayName(),
                'referrer' => $parrain->displayName(),
                'clips' => (int) $clips,
            ];
        })->filter()->reject(fn ($row) => in_array($row->referred_id, $paid, true))->values();
    }

    /** Les primes déjà versées pour ce mois. */
    public function paidFor(CarbonInterface $month): Collection
    {
        return ReferralCommission::query()
            ->where('bonus_month', $month->format('Y-m'))
            ->with(['referrer', 'referred'])
            ->latest('id')
            ->get();
    }

    /**
     * Verse la prime à chaque parrain concerné. Rejouable sans risque : un filleul déjà primé est ignoré.
     *
     * @return int Combien de primes ont été versées
     */
    public function pay(CarbonInterface $month): int
    {
        $amount = $this->amountCents();

        if ($amount <= 0) {
            return 0;
        }

        $paid = 0;

        foreach ($this->qualifying($month) as $row) {
            try {
                DB::transaction(fn () => ReferralCommission::create([
                    'referrer_id' => $row->referrer_id,
                    'referred_id' => $row->referred_id,
                    'budget_transaction_id' => null,
                    'amount_cents' => $amount,
                    'base_cents' => 0,
                    'rate_bp' => 0,
                    'bonus_month' => $month->format('Y-m'),
                ]));
                $paid++;
            } catch (QueryException) {
                // Déjà versée : l'index unique est le garde-fou.
            }
        }

        return $paid;
    }
}
