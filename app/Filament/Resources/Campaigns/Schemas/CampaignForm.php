<?php

namespace App\Filament\Resources\Campaigns\Schemas;

use App\Enums\CampaignStatus;
use App\Enums\Platform;
use App\Models\Artist;
use App\Models\Campaign;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CampaignForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identité')
                ->columns(2)
                ->schema([
                    Select::make('artist_id')
                        ->label('Artiste')
                        ->relationship('artist', 'name', fn ($query) => $query->where('is_active', true))
                        ->searchable()
                        ->preload()
                        ->required()
                        ->createOptionForm([
                            TextInput::make('name')->label('Nom')->required(),
                            TextInput::make('slug')->required(),
                        ])
                        ->createOptionUsing(fn (array $data) => Artist::create($data)->getKey()),

                    Select::make('status')
                        ->label('Statut')
                        ->options(collect(CampaignStatus::cases())
                            ->mapWithKeys(fn (CampaignStatus $status) => [$status->value => $status->label()])
                            ->all())
                        ->default(CampaignStatus::Draft->value)
                        // Le statut ne se change pas au clavier : les bascules
                        // passent par les actions dédiées, qui appliquent la
                        // machine à états et ses garde-fous.
                        ->disabled(fn (?Campaign $record) => $record !== null)
                        ->dehydrated(fn (?Campaign $record) => $record === null)
                        ->helperText(fn (?Campaign $record) => $record
                            ? 'Utilisez les actions en haut de page pour changer de statut.'
                            : null),

                    TextInput::make('title')
                        ->label('Titre')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn ($state, $set, $context) => $context === 'create'
                            ? $set('slug', Str::slug((string) $state))
                            : null),

                    TextInput::make('slug')
                        ->label('Identifiant URL')
                        ->required()
                        ->unique(ignoreRecord: true),

                    DateTimePicker::make('starts_at')
                        ->label('Début de diffusion')
                        ->seconds(false),

                    DateTimePicker::make('ends_at')
                        ->label('Fin de diffusion')
                        ->seconds(false)
                        ->after('starts_at'),
                ]),

            Section::make('Budget et rémunération')
                ->description('Tous les montants sont saisis en euros et stockés en centimes entiers.')
                ->columns(2)
                ->schema([
                    static::money('budget_total_cents')
                        ->label('Budget total')
                        ->required()
                        ->minValue(0.01)
                        ->helperText('Plafond absolu : la campagne bascule en « Épuisée » dès qu\'il est atteint.'),

                    TextInput::make('target_views')
                        ->label('Objectif de vues')
                        ->numeric()
                        ->minValue(0)
                        ->helperText('Indicatif, sert au reporting.'),

                    Repeater::make('rates')
                        ->label('Taux par plateforme')
                        ->relationship()
                        ->columns(3)
                        ->minItems(1)
                        ->defaultItems(1)
                        ->columnSpanFull()
                        ->schema([
                            Select::make('platform')
                                ->label('Plateforme')
                                ->options(collect(Platform::cases())
                                    ->mapWithKeys(fn (Platform $platform) => [$platform->value => $platform->label()])
                                    ->all())
                                ->required()
                                ->distinct(),

                            static::money('rate_per_1k_cents')
                                ->label('Rémunération / 1000 vues')
                                ->required()
                                ->minValue(0.01),

                            Toggle::make('is_enabled')
                                ->label('Actif')
                                ->default(true)
                                ->inline(false),
                        ]),
                ]),

            Section::make('Garde-fous')
                ->description('Optionnels. Ils limitent ce qu\'un clip ou un clippeur peut capter du budget.')
                ->columns(3)
                ->schema([
                    TextInput::make('min_views_per_clip')
                        ->label('Vues minimum par clip')
                        ->numeric()
                        ->default(0)
                        ->minValue(0),

                    static::money('max_payout_per_clip_cents')
                        ->label('Plafond par clip')
                        ->placeholder('Aucun'),

                    static::money('max_payout_per_clipper_cents')
                        ->label('Plafond par clippeur')
                        ->placeholder('Aucun'),
                ]),

            Section::make('Brief')
                ->schema([
                    Textarea::make('brief')
                        ->label('Consignes aux clippeurs')
                        ->rows(6)
                        ->required()
                        ->helperText('Obligatoire pour activer la campagne.'),

                    TextInput::make('audio_url')
                        ->label('Son à utiliser')
                        ->url(),

                    TextInput::make('assets_url')
                        ->label('Pack visuel')
                        ->url(),

                    Toggle::make('requires_approval')
                        ->label('Valider manuellement les participations')
                        ->default(true),
                ]),
        ]);
    }

    /**
     * Champ monétaire : l'admin saisit des euros, la base stocke des centimes.
     * La conversion est faite ici et nulle part ailleurs, pour qu'aucun
     * flottant ne circule dans le domaine.
     */
    protected static function money(string $name): TextInput
    {
        return TextInput::make($name)
            ->numeric()
            ->prefix('€')
            ->step(0.01)
            ->formatStateUsing(fn (?int $state) => $state === null ? null : $state / 100)
            ->dehydrateStateUsing(fn ($state) => $state === null || $state === ''
                ? null
                : (int) round(((float) $state) * 100));
    }
}
