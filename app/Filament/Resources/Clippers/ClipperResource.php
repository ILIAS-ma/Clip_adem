<?php

namespace App\Filament\Resources\Clippers;

use App\Enums\UserRole;
use App\Filament\Resources\Clippers\Pages\ListClippers;
use App\Filament\Resources\Clippers\Pages\ViewClipper;
use App\Filament\Resources\Clippers\RelationManagers\ClipsRelationManager;
use App\Filament\Resources\Clippers\RelationManagers\SocialAccountsRelationManager;
use App\Filament\Resources\Clippers\Schemas\ClipperInfolist;
use App\Filament\Resources\Clippers\Tables\ClippersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Vue « clippeurs » de la table users.
 *
 * Les comptes sont créés par l'espace clippeur : le back-office les consulte,
 * les bannit, mais ne les crée ni ne les édite.
 */
class ClipperResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Clippeurs';

    protected static ?string $modelLabel = 'clippeur';

    protected static ?string $pluralModelLabel = 'clippeurs';

    protected static string|\UnitEnum|null $navigationGroup = 'Modération';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    /** Le panel ne doit jamais laisser un admin se retrouver dans cette liste. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('role', UserRole::Clipper);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ClipperInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClippersTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getRelations(): array
    {
        return [
            SocialAccountsRelationManager::class,
            ClipsRelationManager::class,
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $banned = static::getModel()::where('role', UserRole::Clipper)->where('is_banned', true)->count();

        return $banned > 0 ? (string) $banned : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClippers::route('/'),
            'view' => ViewClipper::route('/{record}'),
        ];
    }
}
