<?php

namespace App\Filament\Resources\Campaigns\Schemas;

use App\Enums\AssetKind;
use App\Enums\CampaignStatus;
use App\Enums\Platform;
use App\Models\Campaign;
use App\Models\Creator;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
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
                    Select::make('creator_id')
                        ->label('Créateur')
                        ->relationship('creator', 'name', fn ($query) => $query->where('is_active', true))
                        ->searchable()
                        ->preload()
                        ->required()
                        ->createOptionForm([
                            TextInput::make('name')->label('Nom')->required(),
                            TextInput::make('slug')->required(),
                        ])
                        ->createOptionUsing(fn (array $data) => Creator::create($data)->getKey()),

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

                    Toggle::make('requires_approval')
                        ->label('Valider manuellement les participations')
                        ->default(true),
                ]),

            Section::make('Matière première')
                ->description(
                    'Sons, rushes, images, chartes : tout ce dont un clippeur a besoin pour '
                    .'tourner sans avoir à demander. Déposez un fichier, ou pointez vers ce qui '
                    .'est déjà hébergé ailleurs.'
                )
                ->schema([
                    Repeater::make('assets')
                        ->label('Pièces jointes')
                        ->relationship()
                        ->reorderable()
                        ->orderColumn('position')
                        ->collapsible()
                        ->itemLabel(fn (array $state) => $state['label'] ?? 'Nouvelle pièce')
                        ->addActionLabel('Ajouter une pièce')
                        ->defaultItems(0)
                        ->columns(2)
                        ->schema([
                            Select::make('kind')
                                ->label('Type')
                                ->options(collect(AssetKind::cases())
                                    ->mapWithKeys(fn (AssetKind $kind) => [$kind->value => $kind->label()])
                                    ->all())
                                ->default(AssetKind::Audio->value)
                                ->required()
                                // Le type conditionne les extensions acceptées
                                // et le poids maximum : il doit être relu à
                                // chaque changement.
                                ->live(),

                            TextInput::make('label')
                                ->label('Intitulé')
                                ->placeholder('Son officiel du refrain')
                                ->required()
                                ->maxLength(120),

                            Textarea::make('description')
                                ->label('Comment s’en servir')
                                ->rows(2)
                                ->columnSpanFull()
                                ->placeholder('Caler le drop à 0:12, ne pas couper avant la fin du refrain.'),

                            FileUpload::make('path')
                                ->label('Fichier')
                                ->disk('public')
                                ->directory('campagnes/pieces')
                                ->visibility('public')
                                ->columnSpanFull()
                                ->acceptedFileTypes(fn ($get) => static::mimesFor($get('kind')))
                                ->maxSize(fn ($get) => static::kind($get('kind'))->maxSizeKb())
                                ->helperText(fn ($get) => 'Formats acceptés : '
                                    .implode(', ', static::kind($get('kind'))->acceptedExtensions())
                                    .'. Poids maximum : '
                                    .round(static::kind($get('kind'))->maxSizeKb() / 1024).' Mo.'),

                            TextInput::make('external_url')
                                ->label('Ou lien externe')
                                ->url()
                                ->columnSpanFull()
                                ->placeholder('https://drive.google.com/…')
                                ->helperText('Pour ce qui est déjà hébergé ailleurs. Laissez vide si vous avez déposé un fichier.'),

                            Toggle::make('is_required')
                                ->label('Élément imposé')
                                ->helperText('Signalé comme obligatoire au clippeur, et vérifié en modération.')
                                ->inline(false),
                        ]),
                ]),
        ]);
    }

    /**
     * Champ monétaire : l'admin saisit des euros, la base stocke des centimes.
     * La conversion est faite ici et nulle part ailleurs, pour qu'aucun
     * flottant ne circule dans le domaine.
     */
    /** Le type sélectionné, avec un repli sûr tant que rien n'est choisi. */
    protected static function kind(mixed $value): AssetKind
    {
        return AssetKind::tryFrom((string) $value) ?? AssetKind::Document;
    }

    /**
     * Types MIME acceptés, dérivés des extensions du type choisi.
     *
     * Les extensions restent la source unique : les lister deux fois, c'est
     * garantir qu'un jour l'une des deux listes acceptera ce que l'autre refuse.
     *
     * @return list<string>
     */
    protected static function mimesFor(mixed $value): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn (string $extension) => match ($extension) {
                'mp3' => 'audio/mpeg',
                'wav' => 'audio/wav',
                'm4a' => 'audio/mp4',
                'aac' => 'audio/aac',
                'ogg' => 'audio/ogg',
                'mp4' => 'video/mp4',
                'mov' => 'video/quicktime',
                'webm' => 'video/webm',
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'webp' => 'image/webp',
                'gif' => 'image/gif',
                'pdf' => 'application/pdf',
                'txt', 'md' => 'text/plain',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                default => null,
            },
            static::kind($value)->acceptedExtensions(),
        ))));
    }

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
