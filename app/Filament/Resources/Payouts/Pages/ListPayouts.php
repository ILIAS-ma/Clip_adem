<?php

namespace App\Filament\Resources\Payouts\Pages;

use App\Enums\PayoutStatus;
use App\Filament\Resources\Payouts\PayoutResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListPayouts extends ListRecords
{
    protected static string $resource = PayoutResource::class;

    /** L'ordre des onglets suit le cycle de vie de l'argent. */
    public function getTabs(): array
    {
        return [
            'requested' => Tab::make('À valider')
                ->badge(fn () => PayoutResource::getModel()::where('status', PayoutStatus::Requested)->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn ($query) => $query->where('status', PayoutStatus::Requested)),

            'approved' => Tab::make('Prêts à partir')
                ->badge(fn () => PayoutResource::getModel()::where('status', PayoutStatus::Approved)->count())
                ->modifyQueryUsing(fn ($query) => $query->where('status', PayoutStatus::Approved)),

            'processing' => Tab::make('En vol')
                ->modifyQueryUsing(fn ($query) => $query->where('status', PayoutStatus::Processing)),

            'failed' => Tab::make('Échecs')
                ->badge(fn () => PayoutResource::getModel()::where('status', PayoutStatus::Failed)->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn ($query) => $query->where('status', PayoutStatus::Failed)),

            'all' => Tab::make('Tous'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'requested';
    }
}
