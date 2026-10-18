<?php

namespace App\Filament\Resources\Participations;

use App\Enums\ParticipationStatus;
use App\Filament\Actions\ParticipationActions;
use App\Filament\Resources\Participations\Pages\ListParticipations;
use App\Models\CampaignParticipation;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Demandes de participation, toutes campagnes confondues.
 *
 * Une campagne « à validation manuelle » laisse chaque demande en attente
 * jusqu'à ce qu'un membre de l'équipe tranche. Sans cette page, rien ne
 * permettait de le faire : le clippeur restait « En attente » indéfiniment.
 */
class ParticipationResource extends Resource
{
    protected static ?string $model = CampaignParticipation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserPlus;

    protected static ?string $navigationLabel = 'Participations';

    protected static ?string $modelLabel = 'participation';

    protected static ?string $pluralModelLabel = 'participations';

    protected static string|\UnitEnum|null $navigationGroup = 'Modération';

    protected static ?int $navigationSort = 0;

    public static function canCreate(): bool
    {
        return false;
    }

    /** Le nombre de demandes à trancher : c'est ce qui fait venir sur la page. */
    public static function getNavigationBadge(): ?string
    {
        $pending = CampaignParticipation::where('status', ParticipationStatus::Pending)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Demandes en attente de validation';
    }

    public static function table(Table $table): Table
    {
        return static::participationsTable($table, withCampaign: true);
    }

    /** Partagé avec la fiche d'une campagne, qui n'a pas besoin de la colonne campagne. */
    public static function participationsTable(Table $table, bool $withCampaign): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['campaign', 'user', 'socialAccount']))
            ->columns(array_values(array_filter([
                $withCampaign
                    ? TextColumn::make('campaign.title')
                        ->label('Campagne')
                        ->searchable()
                        ->weight('bold')
                    : null,

                TextColumn::make('user.name')
                    ->label('Clippeur')
                    ->searchable()
                    ->description(fn (CampaignParticipation $record) => $record->user?->email),

                TextColumn::make('socialAccount.handle')
                    ->label('Compte')
                    ->formatStateUsing(fn ($state, CampaignParticipation $record) => $record->socialAccount?->platform->label()
                        .($state ? ' · @'.$state : ''))
                    ->placeholder('—'),

                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn (ParticipationStatus $state) => $state->label())
                    ->color(fn (ParticipationStatus $state) => match ($state) {
                        ParticipationStatus::Approved => 'success',
                        ParticipationStatus::Pending => 'warning',
                        default => 'danger',
                    })
                    ->description(fn (CampaignParticipation $record) => $record->rejection_reason),

                TextColumn::make('applied_at')
                    ->label('Demandée')
                    ->since()
                    ->sortable(),
            ])))
            ->defaultSort('applied_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options(collect(ParticipationStatus::cases())
                        ->mapWithKeys(fn (ParticipationStatus $status) => [$status->value => $status->label()])
                        ->all())
                    // Par défaut, ce qu'il reste à faire.
                    ->default(ParticipationStatus::Pending->value),
            ])
            ->recordActions([
                ParticipationActions::approve(),
                ParticipationActions::reject(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ParticipationActions::approveMany(),
                ]),
            ])
            ->emptyStateHeading('Aucune demande')
            ->emptyStateDescription('Les demandes de participation aux campagnes à validation manuelle apparaissent ici.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListParticipations::route('/'),
        ];
    }
}
