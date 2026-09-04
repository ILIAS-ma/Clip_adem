<?php

namespace App\Filament\Resources\Creators;

use App\Filament\Resources\Creators\Pages\CreateCreator;
use App\Filament\Resources\Creators\Pages\EditCreator;
use App\Filament\Resources\Creators\Pages\ListCreators;
use App\Filament\Resources\Creators\RelationManagers\CampaignsRelationManager;
use App\Filament\Resources\Creators\Schemas\CreatorForm;
use App\Filament\Resources\Creators\Tables\CreatorsTable;
use App\Models\Creator;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CreatorResource extends Resource
{
    protected static ?string $model = Creator::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMicrophone;

    protected static ?string $navigationLabel = 'Créateurs';

    protected static ?string $modelLabel = 'créateur';

    protected static ?string $pluralModelLabel = 'créateurs';

    protected static string|\UnitEnum|null $navigationGroup = 'Catalogue';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return CreatorForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CreatorsTable::configure($table);
    }

    /**
     * Les fiches créées par un créateur depuis l'inscription publique arrivent
     * inactives. Sans ce badge, elles resteraient invisibles et le créateur
     * attendrait une validation que personne ne sait devoir faire.
     */
    public static function getNavigationBadge(): ?string
    {
        $pending = static::getModel()::whereNotNull('user_id')->where('is_active', false)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getRelations(): array
    {
        return [
            // L'« historique des campagnes » n'est pas une table : c'est cette
            // relation, présentée dans un onglet de la fiche créateur.
            CampaignsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCreators::route('/'),
            'create' => CreateCreator::route('/create'),
            'edit' => EditCreator::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
