<?php

namespace App\Filament\Resources\Clips;

use App\Enums\ClipStatus;
use App\Filament\Resources\Clips\Pages\ListClips;
use App\Filament\Resources\Clips\Tables\ClipsTable;
use App\Models\Clip;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * File de modération.
 *
 * Les clips sont créés par le module clippeur, jamais depuis le back-office :
 * cette ressource est en lecture et en décision, pas en édition.
 */
class ClipResource extends Resource
{
    protected static ?string $model = Clip::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFilm;

    protected static ?string $navigationLabel = 'Clips';

    protected static ?string $modelLabel = 'clip';

    protected static ?string $pluralModelLabel = 'clips';

    protected static string|\UnitEnum|null $navigationGroup = 'Modération';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return ClipsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    /** Le badge signale ce qui attend une décision. */
    public static function getNavigationBadge(): ?string
    {
        $pending = static::getModel()::where('status', ClipStatus::PendingReview)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClips::route('/'),
        ];
    }
}
