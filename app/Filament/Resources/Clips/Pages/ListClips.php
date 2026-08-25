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

    /** Les onglets ordonnent la file : ce qui attend une décision d'abord. */
    public function getTabs(): array
    {
        return [
            'pending' => Tab::make('À modérer')
                ->badge(fn () => ClipResource::getModel()::where('status', ClipStatus::PendingReview)->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn ($query) => $query->where('status', ClipStatus::PendingReview)),

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
        return 'pending';
    }
}
