<?php

namespace App\Filament\Resources\Clips\Pages;

use App\Enums\ClipStatus;
use App\Filament\Resources\Clips\ClipResource;
use App\Models\Clip;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListClips extends ListRecords
{
    protected static string $resource = ClipResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    /** Pas de file d'attente : tout clip soumis est validé d'office, et l'onglet « Refusés » sert à revalider à la main. */
    public function getTabs(): array
    {
        return [
            'approved' => Tab::make('Validés')
                ->modifyQueryUsing(fn ($query) => $query->where('status', ClipStatus::Approved)),

            // Les clips écartés, avec leur motif : c'est ici qu'on les revalide à la main, un par un ou en lot.
            'rejected' => Tab::make('Refusés')
                ->badge(fn () => Clip::where('status', ClipStatus::Rejected)->count() ?: null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn ($query) => $query->where('status', ClipStatus::Rejected)),

            'invalidated' => Tab::make('Invalidés')
                ->modifyQueryUsing(fn ($query) => $query->where('status', ClipStatus::Invalidated)),

            'all' => Tab::make('Tous'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'approved';
    }
}
