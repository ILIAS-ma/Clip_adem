<?php

namespace App\Filament\Resources\Artists\RelationManagers;

use App\Enums\CampaignStatus;
use App\Filament\Resources\Campaigns\CampaignResource;
use App\Models\Campaign;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CampaignsRelationManager extends RelationManager
{
    protected static string $relationship = 'campaigns';

    protected static ?string $title = 'Historique des campagnes';

    public function form(Schema $schema): Schema
    {
        // La création d'une campagne passe par sa propre ressource : le
        // formulaire complet (budget, taux, brief) n'a pas sa place ici.
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('title')
                    ->label('Campagne')
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn (CampaignStatus $state) => $state->label())
                    ->color(fn (CampaignStatus $state) => $state->color()),

                TextColumn::make('budget_total_cents')
                    ->label('Budget')
                    ->alignEnd()
                    ->formatStateUsing(fn (int $state) => static::euros($state)),

                TextColumn::make('spent_cents')
                    ->label('Dépensé')
                    ->alignEnd()
                    ->formatStateUsing(fn (int $state) => static::euros($state))
                    ->description(fn (Campaign $record) => $record->consumedPercent().' %'),

                TextColumn::make('clips_count')
                    ->label('Clips')
                    ->counts('clips')
                    ->alignEnd(),

                TextColumn::make('starts_at')
                    ->label('Début')
                    ->date('d/m/Y')
                    ->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options(collect(CampaignStatus::cases())
                        ->mapWithKeys(fn (CampaignStatus $status) => [$status->value => $status->label()])
                        ->all()),
            ])
            ->headerActions([])
            ->recordActions([
                EditAction::make()
                    ->url(fn (Campaign $record) => CampaignResource::getUrl('edit', ['record' => $record])),
            ]);
    }

    protected static function euros(int $cents): string
    {
        return number_format($cents / 100, 2, ',', ' ').' €';
    }
}
