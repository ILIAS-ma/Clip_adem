<?php

namespace App\Filament\Widgets;

use App\Enums\ClipStatus;
use App\Enums\UserRole;
use App\Models\Clip;
use App\Models\User;
use App\Services\Reporting\ReportingService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlatformOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    // Deux par ligne sur téléphone : quatre tuiles pleine largeur font un écran de défilement pour rien.
    protected int|array|null $columns = ['default' => 2, 'lg' => 3];

    protected function getStats(): array
    {
        $stats = app(ReportingService::class)->globalStats();

        // Les inscrits qui peuvent clipper, et ceux qui ont vraiment soumis au moins un clip.
        $clippers = User::where('role', UserRole::Clipper)->count();
        $withClip = Clip::distinct()->count('user_id');
        $clipsTotal = Clip::count();
        $clipsApproved = Clip::where('status', ClipStatus::Approved)->count();

        return [
            Stat::make('Clippeurs', number_format($clippers, 0, ',', ' '))
                ->description(number_format($withClip, 0, ',', ' ').' ont soumis au moins un clip')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),

            Stat::make('Clips au total', number_format($clipsTotal, 0, ',', ' '))
                ->description(number_format($clipsApproved, 0, ',', ' ').' validés')
                ->descriptionIcon('heroicon-m-film')
                ->color('primary'),

            Stat::make('Budget engagé', static::euros($stats['budget_engaged_cents']))
                ->description($stats['active_campaigns'].' campagne(s) active(s)')
                ->descriptionIcon('heroicon-m-megaphone')
                ->color('gray'),

            Stat::make('Consommé', static::euros($stats['spent_cents']))
                ->description(static::euros($stats['remaining_cents']).' restants')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            // L'écart entre consommé et versé est la dette réelle envers les
            // clippeurs : c'est le chiffre qui doit être provisionné.
            Stat::make('Dû aux clippeurs', static::euros($stats['owed_cents']))
                ->description(static::euros($stats['paid_cents']).' déjà versés')
                ->descriptionIcon('heroicon-m-arrow-up-right')
                ->color($stats['owed_cents'] > 0 ? 'warning' : 'gray'),

            Stat::make('Vues générées', number_format($stats['views'], 0, ',', ' '))
                ->description($stats['real_cpm_cents'] === null
                    ? $stats['clips'].' clip(s)'
                    : 'CPM réel '.static::euros($stats['real_cpm_cents']))
                ->descriptionIcon('heroicon-m-eye')
                ->color('primary'),
        ];
    }

    protected static function euros(int $cents): string
    {
        return number_format($cents / 100, 2, ',', ' ').' €';
    }
}
