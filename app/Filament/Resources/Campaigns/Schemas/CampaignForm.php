<?php

namespace App\Filament\Resources\Campaigns\Schemas;

use App\Enums\AssetKind;
use App\Enums\CampaignMode;
use App\Enums\CampaignStatus;
use App\Enums\ContestMetric;
use App\Enums\Platform;
use App\Filament\Resources\Categories\Schemas\CategoryForm;
use App\Models\Campaign;
use App\Models\Category;
use App\Models\Creator;
use App\Support\Campaigns\Reward;
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
                    Select::make('creator_id')
                        ->label('Créateur')
                        ->relationship('creator', 'name', fn ($query) => $query->where('is_active', true))
                        ->searchable()
                        ->preload()
                        ->required()
                        ->createOptionForm([
                            TextInput::make('name')->label('Nom')->required(),
                        ])
                        ->createOptionUsing(fn (array $data) => Creator::create($data)->getKey()),

                    Select::make('category_id')
                        ->label('Catégorie')
                        ->relationship('category', 'name', fn ($query) => $query->ordered())
                        ->preload()
                        ->placeholder('Aucune')
                        ->createOptionForm(CategoryForm::components())
                        ->createOptionUsing(fn (array $data) => Category::create($data)->getKey())
                        ->helperText('Range la campagne dans un classement par catégorie.'),

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

                    /*
                     * Cette valeur est la clé de route : `/campagnes/{slug}`.
                     * Un caractère invalide — le cas vécu était une URL entière
                     * collée ici — ne produit pas d'erreur de validation mais un
                     * 404 chez le clippeur, à un endroit où personne ne pense à
                     * regarder le slug. On normalise donc à la saisie, pour que
                     * la correction soit visible avant l'enregistrement, et de
                     * nouveau à l'écriture, pour les chemins qui ne passent pas
                     * par le formulaire.
                     */
                    TextInput::make('slug')
                        ->label('Identifiant URL')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn ($state, $set) => $set('slug', Str::slug((string) $state)))
                        ->dehydrateStateUsing(fn ($state) => Str::slug((string) $state))
                        ->helperText('Apparaît dans l’adresse de la campagne : /campagnes/mon-identifiant'),

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
                    /*
                     * Le mode se choisit au brouillon et ne bouge plus ensuite :
                     * basculer une campagne lancée d'un mode à l'autre
                     * changerait les règles de paiement de clips déjà publiés.
                     */
                    Select::make('mode')
                        ->label('Type de campagne')
                        ->options(CampaignMode::options())
                        ->default(CampaignMode::Cpm->value)
                        ->required()
                        ->selectablePlaceholder(false)
                        ->live()
                        ->disabled(fn (?Campaign $record) => $record !== null && $record->status !== CampaignStatus::Draft)
                        ->dehydrated(fn (?Campaign $record) => $record === null || $record->status === CampaignStatus::Draft)
                        ->helperText(fn ($get) => static::isContest($get)
                            ? 'Aucune vue n’est payée au fil de l’eau : les clippeurs sont classés sur leurs vues, '
                                .'et les prix ci-dessous récompensent les premières places à la fin.'
                            : 'Chaque tranche de 1 000 vues est payée au taux de la plateforme, jusqu’à épuisement du budget.')
                        ->columnSpanFull(),

                    // Le critère se fixe au brouillon, comme le mode : le
                    // changer en cours de route changerait le podium.
                    Select::make('contest_metric')
                        ->label('Classement sur')
                        ->options(ContestMetric::options())
                        ->default(ContestMetric::Views->value)
                        ->required()
                        ->selectablePlaceholder(false)
                        ->visible(fn ($get) => static::isContest($get))
                        ->disabled(fn (?Campaign $record) => $record !== null && $record->status !== CampaignStatus::Draft)
                        ->dehydrated(fn (?Campaign $record) => $record === null || $record->status === CampaignStatus::Draft)
                        ->helperText('Vues : le plus vu gagne. Nombre de clips postés : le plus régulier gagne '
                            .'(seuls les clips validés comptent ; à égalité, les vues départagent).')
                        ->columnSpanFull(),

                    /*
                     * Une ligne par place récompensée, dans l'ordre. Chaque
                     * place reçoit de l'argent (versé sur le solde) ou un
                     * objet (remis par l'équipe). Aucune ligne : classement
                     * pour la gloire, sans budget.
                     */
                    Repeater::make('contest_prizes')
                        ->label('Récompenses par place')
                        ->schema([
                            Select::make('type')
                                ->label('Récompense')
                                ->options([
                                    Reward::MONEY => 'Argent',
                                    Reward::ITEM => 'Objet / lot',
                                ])
                                ->default(Reward::MONEY)
                                ->selectablePlaceholder(false)
                                ->required()
                                ->live(),

                            static::money('amount_cents')
                                ->label('Montant')
                                ->required(fn ($get) => $get('type') === Reward::MONEY)
                                ->minValue(0.01)
                                ->visible(fn ($get) => $get('type') === Reward::MONEY),

                            TextInput::make('label')
                                ->label('Objet')
                                ->placeholder('PS5, maillot dédicacé, place de concert…')
                                ->maxLength(120)
                                ->required(fn ($get) => $get('type') === Reward::ITEM)
                                ->visible(fn ($get) => $get('type') === Reward::ITEM),
                        ])
                        ->columns(2)
                        ->itemNumbers()
                        ->itemLabel(fn (array $state) => match ($state['type'] ?? null) {
                            Reward::ITEM => filled($state['label'] ?? null) ? $state['label'] : 'Objet',
                            default => 'Argent',
                        })
                        ->reorderable()
                        ->defaultItems(0)
                        ->addActionLabel('Ajouter une place récompensée')
                        ->visible(fn ($get) => static::isContest($get))
                        ->helperText('Dans l’ordre : la 1re ligne récompense la 1re place, la 2e la 2e place, etc. '
                            .'Sans aucune ligne, le classement se joue pour la gloire.')
                        ->columnSpanFull(),

                    // En classement, le budget est exactement la somme des prix,
                    // recalculée à l'enregistrement (voir withContestBudget()) :
                    // le saisir à part permettrait de promettre plus que ce qui
                    // est financé, ou l'inverse.
                    static::money('budget_total_cents')
                        ->label('Budget total')
                        ->required(fn ($get) => ! static::isContest($get))
                        ->minValue(fn ($get) => static::isContest($get) ? 0 : 0.01)
                        ->disabled(fn ($get) => static::isContest($get))
                        ->dehydrated()
                        ->helperText(fn ($get) => static::isContest($get)
                            ? 'Calculé à l’enregistrement : la somme des récompenses en argent (0 € si objets seulement, ou rien).'
                            : 'Plafond absolu : la campagne bascule en « Épuisée » dès qu\'il est atteint.'),

                    Repeater::make('rates')
                        ->label(fn ($get) => static::isContest($get) ? 'Plateformes acceptées' : 'Taux par plateforme')
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

                            /*
                             * En classement, le taux est forcé à zéro : la
                             * plateforme reste ouverte à la participation, et
                             * le moteur de budget ne paie rien aux vues sans
                             * qu'aucune de ses règles ait à connaître ce mode.
                             */
                            static::money('rate_per_1k_cents')
                                ->label('Rémunération / 1000 vues')
                                ->required(fn ($get) => ! static::isContest($get, '../../'))
                                ->minValue(fn ($get) => static::isContest($get, '../../') ? 0 : 0.01)
                                ->hidden(fn ($get) => static::isContest($get, '../../'))
                                ->dehydratedWhenHidden()
                                ->dehydrateStateUsing(fn ($state, $get) => static::isContest($get, '../../')
                                    ? 0
                                    : ($state === null || $state === '' ? null : (int) round(((float) $state) * 100))),

                            Toggle::make('is_enabled')
                                ->label('Actif')
                                ->default(true)
                                ->inline(false),
                        ]),
                ]),

            // Sans objet en classement : rien n'est payé aux vues, il n'y a
            // donc rien à plafonner ni de minimum à atteindre pour être payé.
            Section::make('Garde-fous')
                ->visible(fn ($get) => ! static::isContest($get))
                ->description('Optionnels. Ils limitent ce qu\'un clip ou un clippeur peut capter du budget.')
                ->columns(3)
                ->schema([
                    TextInput::make('min_views_per_clip')
                        ->label('Vues minimum par clip')
                        ->numeric()
                        ->default(0)
                        ->minValue(0),

                    TextInput::make('min_clips_per_clipper')
                        ->label('Clips minimum par clippeur')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(100)
                        ->placeholder('Aucun')
                        ->helperText("En dessous, les vues sont comptées mais rien n'est crédité. "
                            ."L'arriéré est rattrapé dès que le compte est atteint : rien n'est perdu."),

                    static::money('max_payout_per_clip_cents')
                        ->label('Plafond par clip')
                        ->placeholder('Aucun'),

                ]),

            Section::make('Prime de lancement')
                ->visible(fn ($get) => ! static::isContest($get))
                ->description('Optionnelle. Un montant garanti versé au premier clip validé de chaque clippeur, '
                    .'pour les premiers arrivés : de quoi lancer une campagne sans attendre que les vues montent. '
                    .'Prise sur le budget, une seule par clippeur.')
                ->columns(2)
                ->collapsible()
                ->schema([
                    static::money('launch_bonus_cents')
                        ->label('Montant de la prime')
                        ->placeholder('Aucune')
                        ->minValue(0.01),

                    TextInput::make('launch_bonus_slots')
                        ->label('Nombre de clippeurs concernés')
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->maxValue(1000)
                        ->placeholder('Aucun')
                        ->requiredWith('launch_bonus_cents')
                        ->helperText('Les N premiers clippeurs à faire valider un clip. Si la campagne exige un minimum '
                            .'de clips, la prime attend qu’il soit atteint.'),
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

            Section::make('Matière première du brief')
                ->description(
                    'Sons, rushes, images, chartes, packs : tout ce dont un clippeur a besoin '
                    .'pour tourner sans avoir à demander. Déposez un fichier, ou pointez vers '
                    .'ce qui est déjà hébergé ailleurs.'
                )
                ->collapsible()
                ->schema([
                    Repeater::make('assets')
                        ->label('Pièces jointes')
                        ->relationship()
                        ->reorderable()
                        ->orderColumn('position')
                        ->collapsible()
                        ->collapsed()
                        ->cloneable()
                        ->itemLabel(fn (array $state) => filled($state['label'] ?? null)
                            ? AssetKind::fromValue($state['kind'] ?? null)->label().' · '.$state['label']
                            : 'Nouvelle pièce')
                        ->addActionLabel('Ajouter une pièce')
                        ->defaultItems(0)
                        ->columns(2)
                        ->schema(CampaignAssetForm::components()),
                ]),
        ]);
    }

    /** Le formulaire décrit-il un classement ? `$prefix` remonte depuis un élément de liste. */
    protected static function isContest(callable $get, string $prefix = ''): bool
    {
        return $get($prefix.'mode') === CampaignMode::Contest->value
            || $get($prefix.'mode') === CampaignMode::Contest;
    }

    /**
     * En classement, le budget est la somme des prix. Appelé par les pages de
     * création et d'édition, une fois les montants convertis en centimes.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function withContestBudget(array $data, ?Campaign $record = null): array
    {
        $mode = $data['mode'] ?? $record?->mode?->value ?? CampaignMode::Cpm->value;

        if ($mode instanceof CampaignMode) {
            $mode = $mode->value;
        }

        if ($mode === CampaignMode::Contest->value) {
            $rewards = collect((array) ($data['contest_prizes'] ?? []))
                ->map(fn ($raw) => Reward::from($raw))
                ->filter()
                ->values();

            $data['contest_prizes'] = $rewards->map->toArray()->all();
            // Seul l'argent pèse sur le budget : les objets sont remis par
            // l'équipe, hors de la caisse de la campagne.
            $data['budget_total_cents'] = $rewards->filter->isMoney()->sum('amountCents');
        } else {
            $data['contest_prizes'] = null;
        }

        return $data;
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
