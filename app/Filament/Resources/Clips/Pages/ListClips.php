<?php

namespace App\Filament\Resources\Clips\Pages;

use App\Enums\ClipStatus;
use App\Filament\Resources\Clips\ClipResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListClips extends ListRecords
{
    protected static string $resource = ClipResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    /** Pas de file à modérer : tout clip soumis est validé d'office. */
    public function getTabs(): array
    {
        return [
            'approved' => Tab::make('Validés')
                ->modifyQueryUsing(fn ($query) => $query->where('status', ClipStatus::Approved)),

            'invalidated' => Tab::make('Invalidés')
                ->modifyQueryUsing(fn ($query) => $query->whereIn('status', [
                    ClipStatus::Invalidated,
                    ClipStatus::Rejected,
                ])),

            'all' => Tab::make('Tous'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'approved';
    }
}
