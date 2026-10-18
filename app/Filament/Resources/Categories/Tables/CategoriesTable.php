<?php

namespace App\Filament\Resources\Categories\Tables;

use App\Services\Reporting\ReportingService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Totaux calculés dans la requête elle-même : c'est ce qui permet
            // de trier la liste sur l'argent ou les vues d'un clic.
            ->modifyQueryUsing(fn (Builder $query) => $query->addSelect([
                'earned_cents_total' => ReportingService::categoryEarnedSubquery(),
                'views_total' => ReportingService::categoryViewsSubquery(),
            ]))
            ->columns([
                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('campaigns_count')
                    ->label('Campagnes')
                    ->counts('campaigns')
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('views_total')
                    ->label('Vues')
                    ->alignEnd()
                    ->sortable()
                    ->tooltip('Vues des clips validés, depuis toujours.')
                    ->formatStateUsing(fn ($state) => number_format((int) $state, 0, ',', ' ')),

                TextColumn::make('earned_cents_total')
                    ->label('Versé aux clippeurs')
                    ->alignEnd()
                    ->sortable()
                    ->formatStateUsing(fn ($state) => number_format(((int) $state) / 100, 2, ',', ' ').' €'),

                TextColumn::make('created_at')
                    ->label('Créée le')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            // Même ordre que les onglets du classement : ce qui rapporte le plus d'abord.
            ->defaultSort('earned_cents_total', 'desc')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
