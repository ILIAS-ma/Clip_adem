<?php

namespace App\Filament\Resources\Creators\Tables;

use App\Models\Creator;
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

class CreatorsTable
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
                    ->label('Créateur')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Creator $record) => $record->tiktok_handle ? '@'.$record->tiktok_handle : null),

                TextColumn::make('campaigns_count')
                    ->label('Campagnes')
                    ->counts('campaigns')
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('budget_engaged')
                    ->label('Budget engagé')
                    ->alignEnd()
                    ->state(fn (Creator $record) => static::euros($record->budgetTotalCents())),

                TextColumn::make('budget_spent')
                    ->label('Dépensé')
                    ->alignEnd()
                    ->state(fn (Creator $record) => static::euros($record->spentCents())),

                TextColumn::make('real_cpm')
                    ->label('CPM réel')
                    ->alignEnd()
                    ->tooltip('Coût réel pour 1000 vues, tous clips confondus.')
                    ->state(fn (Creator $record) => ($cpm = $record->realCostPer1kCents()) === null
                        ? '—'
                        : static::euros($cpm)),

                TextColumn::make('is_active')
                    ->label('Statut')
                    ->badge()
                    // Distinguer « désactivé par un admin » de « inscrit et en
                    // attente » : ce sont deux situations sans rapport.
                    ->state(fn (Creator $record) => match (true) {
                        $record->is_active => 'Actif',
                        $record->user_id !== null => 'À valider',
                        default => 'Inactif',
                    })
                    ->color(fn (Creator $record) => match (true) {
                        $record->is_active => 'success',
                        $record->user_id !== null => 'warning',
                        default => 'gray',
                    })
                    ->description(fn (Creator $record) => $record->user_id ? 'Compte créateur lié' : null),

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
