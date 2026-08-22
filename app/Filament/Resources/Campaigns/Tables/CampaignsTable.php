<?php

namespace App\Filament\Resources\Campaigns\Tables;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class CampaignsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Campagne')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Campaign $record) => $record->artist?->name),

                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn (CampaignStatus $state) => $state->label())
                    ->color(fn (CampaignStatus $state) => $state->color())
                    ->sortable(),

                TextColumn::make('budget_total_cents')
                    ->label('Budget')
                    ->alignEnd()
                    ->sortable()
                    ->formatStateUsing(fn (int $state) => static::euros($state)),

                TextColumn::make('spent_cents')
                    ->label('Consommé')
                    ->alignEnd()
                    ->sortable()
                    ->formatStateUsing(fn (int $state, Campaign $record) => sprintf(
                        '%s · %s %%',
                        static::euros($state),
                        $record->consumedPercent(),
                    ))
                    // La couleur porte l'alerte : à 90 % il est temps de
                    // décider si on recharge le budget ou si on laisse finir.
                    ->color(fn (Campaign $record) => match (true) {
                        $record->consumedPercent() >= 100 => 'danger',
                        $record->consumedPercent() >= 90 => 'warning',
                        default => 'success',
                    }),

                TextColumn::make('remaining')
                    ->label('Restant')
                    ->alignEnd()
                    ->state(fn (Campaign $record) => static::euros($record->remainingCents())),

                TextColumn::make('clips_count')
                    ->label('Clips')
                    ->counts('clips')
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('ends_at')
                    ->label('Fin')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Statut')
                    ->multiple()
                    ->options(collect(CampaignStatus::cases())
                        ->mapWithKeys(fn (CampaignStatus $status) => [$status->value => $status->label()])
                        ->all()),

                SelectFilter::make('artist')
                    ->label('Artiste')
                    ->relationship('artist', 'name')
                    ->searchable()
                    ->preload(),

                TrashedFilter::make()
                    ->label('Supprimées'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    protected static function euros(int $cents): string
    {
        return number_format($cents / 100, 2, ',', ' ').' €';
    }
}
