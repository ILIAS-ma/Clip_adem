<?php

namespace App\Services\Clippers;

use App\Enums\ClipStatus;
use App\Enums\UserRole;
use App\Models\BudgetTransaction;
use App\Support\Clippers\RetentionSignal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Les clippeurs qui risquent de partir.
 *
 * Un clippeur qui abandonne ne prévient pas : il cesse de poster. Ce radar le
 * repère avant, sur trois signaux lus dans les clips et le grand livre —
 * jamais dans un compteur stocké.
 *
 *  - À risque : il poste encore, mais ne gagne rien. Beaucoup d'effort pour
 *    rien, c'est la frustration qui fait partir.
 *  - Gains en baisse : il gagnait, il gagne nettement moins.
 *  - Inactif : il a déjà été payé, et ne poste plus.
 */
class RetentionRadar
{
    /** @return Collection<int, RetentionSignal> indexée par identifiant de clippeur */
    public function detect(?Carbon $now = null): Collection
    {
        $now ??= now();
        $config = config('clipping.retention');
        $window = (int) $config['window_days'];

        $since = $now->copy()->subDays($window);
        $previous = $now->copy()->subDays($window * 2);
        $ledger = (new BudgetTransaction)->getTable();

        $rows = DB::table('users')
            ->where('users.role', UserRole::Clipper->value)
            ->where('users.is_banned', false)
            ->whereNull('users.deleted_at')
            ->select('users.id')
            ->selectSub(fn ($q) => $q->from('clips')->whereColumn('clips.user_id', 'users.id')
                ->where('clips.submitted_at', '>=', $since)->selectRaw('COUNT(*)'), 'clips_window')
            ->selectSub(fn ($q) => $q->from('clips')->whereColumn('clips.user_id', 'users.id')
                ->where('clips.status', ClipStatus::Approved->value)->selectRaw('COUNT(*)'), 'approved_total')
            ->selectSub(fn ($q) => $q->from('clips')->whereColumn('clips.user_id', 'users.id')
                ->selectRaw('MAX(submitted_at)'), 'last_clip_at')
            ->selectSub(fn ($q) => $q->from($ledger)->whereColumn($ledger.'.user_id', 'users.id')
                ->where($ledger.'.created_at', '>=', $since)
                ->selectRaw('COALESCE(SUM(amount_cents), 0)'), 'earned_window')
            ->selectSub(fn ($q) => $q->from($ledger)->whereColumn($ledger.'.user_id', 'users.id')
                ->where($ledger.'.created_at', '>=', $previous)
                ->where($ledger.'.created_at', '<', $since)
                ->selectRaw('COALESCE(SUM(amount_cents), 0)'), 'earned_previous')
            ->get();

        return $rows
            ->map(fn ($row) => $this->classify($row, $now, $config))
            ->filter()
            ->keyBy('userId');
    }

    protected function classify(object $row, Carbon $now, array $config): ?RetentionSignal
    {
        $clips = (int) $row->clips_window;
        $earned = (int) $row->earned_window;
        $earnedBefore = (int) $row->earned_previous;
        $lastClip = $row->last_clip_at ? Carbon::parse($row->last_clip_at) : null;
        $reasons = [];
        $level = null;

        if ($clips >= $config['busy_min_clips'] && $earned <= 0) {
            $level = RetentionSignal::AT_RISK;
            $reasons[] = "{$clips} clips en {$config['window_days']} jours, 0 € gagné";
        }

        if ($earnedBefore >= $config['drop_min_previous_cents'] && $earned < $earnedBefore * $config['drop_ratio']) {
            $level ??= RetentionSignal::DROPPING;
            $reasons[] = sprintf(
                'Gains en baisse : %s € contre %s € la période précédente',
                number_format(max(0, $earned) / 100, 2, ',', ' '),
                number_format($earnedBefore / 100, 2, ',', ' '),
            );
        }

        if ((int) $row->approved_total > 0 && $lastClip && $lastClip->lt($now->copy()->subDays($config['inactive_days']))) {
            $level ??= RetentionSignal::INACTIVE;
            $reasons[] = 'Plus de clip depuis '.(int) $lastClip->diffInDays($now).' jours';
        }

        if ($level === null) {
            return null;
        }

        return new RetentionSignal(
            userId: (int) $row->id,
            level: $level,
            reasons: $reasons,
            clipsInWindow: $clips,
            earnedInWindowCents: $earned,
            earnedPreviousCents: $earnedBefore,
            lastClipAt: $lastClip,
        );
    }
}
