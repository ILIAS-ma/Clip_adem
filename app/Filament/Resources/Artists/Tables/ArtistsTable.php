<?php

namespace App\Filament\Resources\Artists\Tables;

use App\Models\Artist;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ArtistsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('avatar_path')
                    ->label('')
                    ->circular()
                    ->defaultImageUrl(fn () => 'https://ui-avatars.com/api/?name=?&background=random'),

                TextColumn::make('name')
                    ->label('Artiste')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Artist $record) => $record->tiktok_handle ? '@'.$record->tiktok_handle : null),

                TextColumn::make('campaigns_count')
                    ->label('Campagnes')
                    ->counts('campaigns')
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('budget_engaged')
                    ->label('Budget engagé')
                    ->alignEnd()
                    ->state(fn (Artist $record) => static::euros($record->budgetTotalCents())),

                TextColumn::make('budget_spent')
                    ->label('Dépensé')
                    ->alignEnd()
                    ->state(fn (Artist $record) => static::euros($record->spentCents())),

                TextColumn::make('real_cpm')
                    ->label('CPM réel')
                    ->alignEnd()
                    ->tooltip('Coût réel pour 1000 vues, tous clips confondus.')
                    ->state(fn (Artist $record) => ($cpm = $record->realCostPer1kCents()) === null
                        ? '—'
                        : static::euros($cpm)),

                TextColumn::make('is_active')
                    ->label('Statut')
                    ->badge()
                    ->state(fn (Artist $record) => $record->is_active ? 'Actif' : 'Inactif')
                    ->color(fn (Artist $record) => $record->is_active ? 'success' : 'gray'),

                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Actif'),
                TrashedFilter::make()
                    ->label('Supprimés'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    protected static function euros(int $cents): string
    {
        return number_format($cents / 100, 2, ',', ' ').' €';
    }
}
