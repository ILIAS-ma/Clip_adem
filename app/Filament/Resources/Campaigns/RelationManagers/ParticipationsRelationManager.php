<?php

namespace App\Filament\Resources\Campaigns\RelationManagers;

use App\Models\CampaignParticipation;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Qui participe à la campagne, et avec quel compte. En lecture seule : il n'y
 * a pas de demande à valider, un clippeur rejoint et poste.
 */
class ParticipationsRelationManager extends RelationManager
{
    protected static string $relationship = 'participations';

    protected static ?string $title = 'Participants';

    protected static ?string $modelLabel = 'participant';

    protected static ?string $pluralModelLabel = 'participants';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['user', 'socialAccount'])->withCount('clips'))
            ->columns([
                TextColumn::make('user.name')
                    ->label('Clippeur')
                    ->searchable()
                    ->description(fn (CampaignParticipation $record) => $record->user?->email),

                TextColumn::make('socialAccount.handle')
                    ->label('Compte')
                    ->formatStateUsing(fn ($state, CampaignParticipation $record) => $record->socialAccount?->platform->label()
                        .($state ? ' · @'.$state : ''))
                    ->placeholder('—'),

                TextColumn::make('clips_count')
                    ->label('Clips')
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('applied_at')
                    ->label('A rejoint')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('applied_at', 'desc')
            ->emptyStateHeading('Personne pour l’instant')
            ->emptyStateDescription('Les clippeurs qui rejoignent la campagne apparaissent ici.');
    }
}
