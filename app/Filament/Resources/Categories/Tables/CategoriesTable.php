<?php

namespace App\Filament\Resources\Categories\Tables;

use App\Models\Category;
use App\Services\Reporting\ReportingService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Category $record) => $record->slug),

                TextColumn::make('campaigns_count')
                    ->label('Campagnes')
                    ->counts('campaigns')
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('views')
                    ->label('Vues')
                    ->alignEnd()
                    ->tooltip('Vues des clips validés, depuis toujours.')
                    ->state(fn (Category $record) => number_format(
                        app(ReportingService::class)->categoryTotals($record)['views'], 0, ',', ' ',
                    )),

                TextColumn::make('earned')
                    ->label('Versé aux clippeurs')
                    ->alignEnd()
                    ->state(fn (Category $record) => number_format(
                        app(ReportingService::class)->categoryTotals($record)['earned_cents'] / 100, 2, ',', ' ',
                    ).' €'),

                TextColumn::make('position')
                    ->label('Ordre')
                    ->alignEnd()
                    ->sortable(),
            ])
            ->defaultSort('position')
            ->reorderable('position')
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
