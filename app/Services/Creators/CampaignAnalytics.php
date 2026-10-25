<?php

namespace App\Services\Creators;

use App\Enums\ClipStatus;
use App\Models\Campaign;
use App\Models\Clip;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * L'audience d'une ou plusieurs campagnes, lue sur les clips eux-mêmes.
 *
 * CreatorStatsService répond à « combien ça a coûté » depuis le grand livre. Il
 * ne dit rien d'un classement : une campagne sans paiement aux vues ne laisse
 * aucune écriture, et son créateur verrait zéro partout. Ici, on lit ce que la
 * campagne a produit — vues, clips, clippeurs — et on le lit encore après sa fin,
 * puisque les publications continuent de vivre.
 *
 * Seuls les clips validés comptent. Les noms sont des pseudos : le créateur n'a
 * pas à connaître l'identité civile des clippeurs.
 */
class CampaignAnalytics
{
    /** Au-delà, un graphique journalier n'est plus lisible, et la requête plus lourde. */
    protected const MAX_DAYS = 90;

    /**
     * Les grands chiffres.
     *
     * @param  array<int, int>  $ids  identifiants de campagnes
     * @return array{views: int, clips: int, clippers: int, best: int, average: int}
     */
    public function overview(array $ids): array
    {
        $row = $this->rows($ids)
            ->selectRaw('COUNT(*) as clips, COUNT(DISTINCT user_id) as clippers')
            ->selectRaw('COALESCE(SUM(views_total), 0) as views, COALESCE(MAX(views_total), 0) as best')
            ->first();

        $clips = (int) ($row->clips ?? 0);
        $views = (int) ($row->views ?? 0);

        return [
            'views' => $views,
            'clips' => $clips,
            'clippers' => (int) ($row->clippers ?? 0),
            'best' => (int) ($row->best ?? 0),
            'average' => $clips > 0 ? intdiv($views, $clips) : 0,
        ];
    }

    /**
     * L'évolution des vues, jour par jour.
     *
     * - `cumulative` : le total des vues à la fin de chaque jour, chaque clip pris
     *   à son dernier relevé connu. Il inclut les vues qu'un clip avait déjà au
     *   moment où on l'a relevé pour la première fois.
     * - `gains` : ce qui s'est ajouté ce jour-là, SANS ce premier relevé. Sinon un
     *   clip ancien, soumis tard, dessinerait un pic qui n'est pas une journée de vues.
     *
     * @param  array<int, int>  $ids
     * @return array{labels: list<string>, cumulative: list<int>, gains: list<int>, peak_day: ?string, peak_gain: int}
     */
    public function timeline(array $ids, int $days = self::MAX_DAYS): array
    {
        $empty = ['labels' => [], 'cumulative' => [], 'gains' => [], 'peak_day' => null, 'peak_gain' => 0];
        $days = min(max($days, 1), self::MAX_DAYS);

        $snapshots = fn () => DB::table('clip_view_snapshots as s')
            ->join('clips as c', 'c.id', '=', 's.clip_id')
            ->whereIn('c.campaign_id', $ids)
            ->where('c.status', ClipStatus::Approved->value)
            ->whereRaw(...Campaign::postingWindowSql('c'));

        $first = $snapshots()->min('s.captured_at');

        if ($first === null) {
            return $empty;
        }

        $from = Carbon::parse($first)->startOfDay();
        $earliest = now()->startOfDay()->subDays($days - 1);

        if ($from->lt($earliest)) {
            $from = $earliest;
        }

        // Les clips relevés avant la fenêtre partent de leur dernier total connu.
        $last = $snapshots()
            ->where('s.captured_at', '<', $from)
            ->groupBy('s.clip_id')
            ->selectRaw('s.clip_id, MAX(s.views) as v')
            ->pluck('v', 'clip_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        $rows = $snapshots()
            ->where('s.captured_at', '>=', $from)
            ->selectRaw('DATE(s.captured_at) as d, s.clip_id, MAX(s.views) as v')
            ->groupBy('d', 's.clip_id')
            ->get()
            ->groupBy('d');

        $labels = [];
        $cumulative = [];
        $gains = [];

        for ($day = $from->copy(); $day->lte(now()->startOfDay()); $day->addDay()) {
            $key = $day->format('Y-m-d');
            $gain = 0;

            foreach ($rows->get($key, []) as $row) {
                $id = (int) $row->clip_id;
                $views = (int) $row->v;

                if (isset($last[$id])) {
                    $gain += max(0, $views - $last[$id]);
                }

                $last[$id] = max($last[$id] ?? 0, $views);
            }

            $labels[] = $key;
            $cumulative[] = array_sum($last);
            $gains[] = $gain;
        }

        $peak = $gains === [] ? 0 : max($gains);

        return [
            'labels' => $labels,
            'cumulative' => $cumulative,
            'gains' => $gains,
            'peak_day' => $peak > 0 ? $labels[array_search($peak, $gains, true)] : null,
            'peak_gain' => $peak,
        ];
    }

    /**
     * Les clips publiés chaque jour, zéros compris.
     *
     * @param  array<int, int>  $ids
     * @return array{labels: list<string>, values: list<int>}
     */
    public function postsPerDay(array $ids, int $days = self::MAX_DAYS): array
    {
        $dates = $this->rows($ids)
            ->selectRaw('DATE(COALESCE(posted_at, submitted_at)) as d, COUNT(*) as n')
            ->groupBy('d')
            ->pluck('n', 'd');

        if ($dates->isEmpty()) {
            return ['labels' => [], 'values' => []];
        }

        $from = Carbon::parse($dates->keys()->min())->startOfDay();
        $earliest = now()->startOfDay()->subDays(min($days, self::MAX_DAYS) - 1);

        if ($from->lt($earliest)) {
            $from = $earliest;
        }

        $labels = [];
        $values = [];

        for ($day = $from->copy(); $day->lte(now()->startOfDay()); $day->addDay()) {
            $labels[] = $day->format('Y-m-d');
            $values[] = (int) ($dates[$day->format('Y-m-d')] ?? 0);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * Combien de clips dans chaque tranche de vues.
     *
     * Dit si la campagne a été portée par quelques vidéos virales ou par un grand
     * nombre de clips moyens — ce qu'un total de vues ne montre pas.
     *
     * @param  array<int, int>  $ids
     * @return list<array{label: string, count: int, share: int}>
     */
    public function distribution(array $ids): array
    {
        $buckets = [
            ['label' => 'Moins de 100 vues', 'min' => 0, 'max' => 99],
            ['label' => '100 à 1 000', 'min' => 100, 'max' => 999],
            ['label' => '1 000 à 10 000', 'min' => 1000, 'max' => 9_999],
            ['label' => '10 000 à 100 000', 'min' => 10_000, 'max' => 99_999],
            ['label' => '100 000 et plus', 'min' => 100_000, 'max' => PHP_INT_MAX],
        ];

        $views = $this->rows($ids)->pluck('views_total');
        $total = max(1, $views->count());

        return array_map(function (array $bucket) use ($views, $total) {
            $count = $views->filter(fn ($v) => $v >= $bucket['min'] && $v <= $bucket['max'])->count();

            return ['label' => $bucket['label'], 'count' => $count, 'share' => (int) round($count / $total * 100)];
        }, $buckets);
    }

    /**
     * Les clippeurs qui ont le plus porté la campagne, par pseudo.
     *
     * @param  array<int, int>  $ids
     * @return Collection<int, object{name: string, clips: int, views: int, best: int}>
     */
    public function topClippers(array $ids, int $limit = 8): Collection
    {
        return $this->rows($ids)
            ->join('users', 'users.id', '=', 'clips.user_id')
            ->groupBy('clips.user_id', 'users.pseudo', 'users.name')
            ->selectRaw("COALESCE(NULLIF(users.pseudo, ''), users.name) as name")
            ->selectRaw('COUNT(*) as clips, SUM(clips.views_total) as views, MAX(clips.views_total) as best')
            ->orderByDesc('views')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => (object) [
                'name' => (string) $row->name,
                'clips' => (int) $row->clips,
                'views' => (int) $row->views,
                'best' => (int) $row->best,
            ]);
    }

    /**
     * Les clips les plus vus, avec leur miniature.
     *
     * @param  array<int, int>  $ids
     * @return Collection<int, Clip>
     */
    public function topClips(array $ids, int $limit = 6): Collection
    {
        return Clip::query()
            ->whereIn('campaign_id', $ids)
            ->where('status', ClipStatus::Approved->value)
            ->with(['user:id,name,pseudo', 'campaign:id,title'])
            ->orderByDesc('views_total')
            ->limit($limit)
            ->get();
    }

    /**
     * Répartition par plateforme.
     *
     * @param  array<int, int>  $ids
     * @return Collection<int, object{platform: string, clips: int, views: int}>
     */
    public function platforms(array $ids): Collection
    {
        return $this->rows($ids)
            ->groupBy('platform')
            ->selectRaw('platform, COUNT(*) as clips, COALESCE(SUM(views_total), 0) as views')
            ->orderByDesc('views')
            ->get()
            ->map(fn ($row) => (object) [
                'platform' => (string) $row->platform,
                'clips' => (int) $row->clips,
                'views' => (int) $row->views,
            ]);
    }

    /**
     * Une phrase qui dit où en est le créateur, avec les vraies vues.
     *
     * @param  array{views: int, clips: int, clippers: int}  $overview
     */
    public function headline(array $overview, int $running, int $total): string
    {
        if ($total === 0) {
            return 'Aucune campagne lancée pour le moment.';
        }

        if ($overview['clips'] === 0) {
            return $running > 0
                ? 'Campagne lancée, les premiers clips arrivent.'
                : 'Aucun clip n’a été publié sur vos campagnes.';
        }

        return sprintf(
            '%s vues générées par %d clip%s de %d clippeur%s.',
            number_format($overview['views'], 0, ',', ' '),
            $overview['clips'],
            $overview['clips'] > 1 ? 's' : '',
            $overview['clippers'],
            $overview['clippers'] > 1 ? 's' : '',
        );
    }

    /** Les clips validés des campagnes demandées, en modèles. */
    protected function clips(array $ids)
    {
        // Les mêmes clips que ceux du classement : une vidéo publiée hors de la campagne n'y compte pas.
        return Clip::query()
            ->whereIn('clips.campaign_id', $ids)
            ->where('clips.status', ClipStatus::Approved->value)
            ->whereRaw(...Campaign::postingWindowSql('clips'));
    }

    /** La même chose en lignes brutes : un agrégat n'a que faire des conversions du modèle. */
    protected function rows(array $ids)
    {
        return $this->clips($ids)->toBase();
    }
}
