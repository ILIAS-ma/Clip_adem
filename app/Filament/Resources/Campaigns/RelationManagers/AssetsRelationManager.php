<?php

namespace App\Filament\Resources\Campaigns\RelationManagers;

use App\Enums\AssetKind;
use App\Filament\Resources\Campaigns\Schemas\CampaignAssetForm;
use App\Models\CampaignAsset;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Gestion des pièces du brief, à part du formulaire de campagne.
 *
 * Ajouter un rush trois jours après le lancement ne devrait pas obliger à
 * rouvrir — et à re-soumettre — un formulaire qui porte le budget et les taux.
 * Une erreur de manipulation y coûterait beaucoup plus cher qu'une pièce
 * jointe.
 */
class AssetsRelationManager extends RelationManager
{
    protected static string $relationship = 'assets';

    protected static ?string $title = 'Matière première';

    protected static ?string $modelLabel = 'pièce';

    protected static ?string $pluralModelLabel = 'pièces';

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components(CampaignAssetForm::components());
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->reorderable('position')
            ->defaultSort('position')
            ->emptyStateHeading('Aucune matière première')
            ->emptyStateDescription(
                'Un brief sans son ni exemple oblige chaque clippeur à deviner. '
                .'Déposez au moins le son imposé.'
            )
            ->columns([
                TextColumn::make('kind')
                    ->label('Type')
                    ->badge()
                    ->icon(fn (AssetKind $state) => $state->icon())
                    ->formatStateUsing(fn (AssetKind $state) => $state->label())
                    ->color(fn (AssetKind $state) => match ($state) {
                        AssetKind::Audio => 'success',
                        AssetKind::Video => 'info',
                        AssetKind::Image => 'warning',
                        AssetKind::Document, AssetKind::Archive => 'gray',
                    }),

                TextColumn::make('label')
                    ->label('Intitulé')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (CampaignAsset $record) => $record->description)
                    ->wrap(),

                IconColumn::make('is_required')
                    ->label('Imposé')
                    ->boolean(),

                TextColumn::make('size_bytes')
                    ->label('Poids')
                    ->alignEnd()
                    // Un lien externe ne pèse rien chez nous : le tiret dit
                    // « hébergé ailleurs », pas « fichier vide ».
                    ->placeholder('lien')
                    ->formatStateUsing(fn ($state, CampaignAsset $record) => $record->humanSize() ?? 'lien'),

                TextColumn::make('path')
                    ->label('Source')
                    ->formatStateUsing(fn ($state, CampaignAsset $record) => $record->isHosted()
                        ? 'Fichier déposé'
                        : 'Lien externe')
                    ->url(fn (CampaignAsset $record) => $record->url(), shouldOpenInNewTab: true)
                    ->color('primary')
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('kind')
                    ->label('Type')
                    ->multiple()
                    ->options(AssetKind::options()),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Ajouter une pièce')
                    ->modalHeading('Nouvelle pièce du brief')
                    ->modalWidth('3xl'),
            ])
            ->recordActions([
                EditAction::make()->modalWidth('3xl'),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }
}
