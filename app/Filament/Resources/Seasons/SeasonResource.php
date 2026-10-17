<?php

namespace App\Filament\Resources\Seasons;

use App\Filament\Resources\Seasons\Pages\CreateSeason;
use App\Filament\Resources\Seasons\Pages\EditSeason;
use App\Filament\Resources\Seasons\Pages\ListSeasons;
use App\Models\Season;
use App\Services\Reporting\ReportingService;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Saisons du classement. Une saison ne verse rien d'elle-même : la récompense
 * annoncée se paie à la main, une fois le podium arrêté.
 */
class SeasonResource extends Resource
{
    protected static ?string $model = Season::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static ?string $navigationLabel = 'Saisons';

    protected static ?string $modelLabel = 'saison';

    protected static ?string $pluralModelLabel = 'saisons';

    protected static string|\UnitEnum|null $navigationGroup = 'Catalogue';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Saison')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Nom')
                        ->placeholder('Saison 1')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('prize')
                        ->label('Récompense annoncée')
                        ->placeholder('200 € au premier, 100 € au deuxième')
                        ->maxLength(255)
                        ->helperText('Affichée aux clippeurs. Rien n’est versé automatiquement.'),

                    DatePicker::make('starts_on')
                        ->label('Premier jour')
                        ->required(),

                    DatePicker::make('ends_on')
                        ->label('Dernier jour')
                        ->required()
                        ->afterOrEqual('starts_on')
                        ->helperText('Inclus. Si deux saisons se chevauchent, la plus récente est affichée.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Saison')
                    ->weight('bold')
                    ->description(fn (Season $record) => $record->prize),

                TextColumn::make('period')
                    ->label('Période')
                    ->state(fn (Season $record) => $record->starts_on->format('d/m/Y').' → '.$record->ends_on->format('d/m/Y')),

                TextColumn::make('state')
                    ->label('État')
                    ->badge()
                    ->state(fn (Season $record) => match (true) {
                        $record->isRunning() => 'En cours',
                        $record->starts_on->isFuture() => 'À venir',
                        default => 'Terminée',
                    })
                    ->color(fn (Season $record) => match (true) {
                        $record->isRunning() => 'success',
                        $record->starts_on->isFuture() => 'info',
                        default => 'gray',
                    }),

                // Le podium en un coup d'œil, pour savoir à qui verser la
                // récompense sans ouvrir l'espace clippeur.
                TextColumn::make('leader')
                    ->label('En tête')
                    ->state(function (Season $record) {
                        $top = app(ReportingService::class)->topClippersInSeason($record, 1)->first();

                        return $top
                            ? $top->name.' · '.number_format($top->earned_cents / 100, 2, ',', ' ').' €'
                            : '—';
                    }),
            ])
            ->defaultSort('starts_on', 'desc')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSeasons::route('/'),
            'create' => CreateSeason::route('/create'),
            'edit' => EditSeason::route('/{record}/edit'),
        ];
    }
}
