<?php

namespace App\Filament\Resources\Payouts;

use App\Enums\PayoutStatus;
use App\Filament\Resources\Payouts\Pages\ListPayouts;
use App\Filament\Resources\Payouts\Tables\PayoutsTable;
use App\Models\Payout;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PayoutResource extends Resource
{
    protected static ?string $model = Payout::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Retraits';

    protected static ?string $modelLabel = 'retrait';

    protected static ?string $pluralModelLabel = 'retraits';

    protected static string|\UnitEnum|null $navigationGroup = 'Finances';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return PayoutsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    /** Ce qui attend une décision humaine. */
    public static function getNavigationBadge(): ?string
    {
        $pending = static::getModel()::where('status', PayoutStatus::Requested)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayouts::route('/'),
        ];
    }
}
