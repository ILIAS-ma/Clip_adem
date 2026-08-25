<?php

namespace App\Filament\Resources\Clippers\RelationManagers;

use App\Enums\ClipStatus;
use App\Enums\Platform;
use App\Models\Clip;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ClipsRelationManager extends RelationManager
{
    protected static string $relationship = 'clips';

    protected static ?string $title = 'Historique de participation';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('external_post_id')
            ->columns([
                TextColumn::make('campaign.title')
                    ->label('Campagne')
                    ->weight('bold')
                    ->description(fn (Clip $record) => $record->campaign?->artist?->name),

                TextColumn::make('platform')
                    ->label('Plateforme')
                    ->badge()
                    ->formatStateUsing(fn (Platform $state) => $state->label()),

                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn (ClipStatus $state) => $state->label())
                    ->color(fn (ClipStatus $state) => match ($state) {
                        ClipStatus::Approved => 'success',
                        ClipStatus::PendingReview => 'warning',
                        ClipStatus::Rejected => 'gray',
                        ClipStatus::Invalidated => 'danger',
                    }),

                TextColumn::make('views_total')
                    ->label('Vues')
                    ->alignEnd()
                    ->formatStateUsing(fn (int $state) => number_format($state, 0, ',', ' ')),

                TextColumn::make('earned_cents')
                    ->label('Gagné')
                    ->alignEnd()
                    ->formatStateUsing(fn (int $state) => number_format($state / 100, 2, ',', ' ').' €'),

                TextColumn::make('posted_at')
                    ->label('Publié')
                    ->date('d/m/Y')
                    ->placeholder('—'),
            ])
            ->defaultSort('posted_at', 'desc')
            ->headerActions([])
            ->recordActions([])
            ->recordUrl(fn (Clip $record) => null);
    }
}
