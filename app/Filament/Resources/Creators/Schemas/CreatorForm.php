<?php

namespace App\Filament\Resources\Creators\Schemas;

use App\Enums\CreatorKind;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CreatorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            /*
             * La nature se choisit d'abord, et commande le reste du
             * formulaire : demander son Spotify à un streamer ou ses
             * rediffusions à un chanteur ne produit que des champs vides, et
             * un champ vide dans un back-office se lit comme un oubli.
             */
            Section::make('Nature de la fiche')
                ->schema([
                    Select::make('kind')
                        ->label('Type de compte')
                        ->options(CreatorKind::options())
                        ->default(CreatorKind::Artist->value)
                        ->required()
                        ->selectablePlaceholder(false)
                        ->live()
                        ->helperText('Un streamer fournit des rediffusions, un créateur un extrait. '
                            .'Ce choix décide des champs ci-dessous et de l’espace que la personne verra.'),
                ]),

            Section::make('Identité')
                ->columns(2)
                ->schema([
                    // L'identifiant interne se fabrique tout seul depuis le nom
                    // (voir Creator::booted()) : aucune adresse ne l'affiche.
                    TextInput::make('name')
                        ->label(fn ($get) => static::kind($get)->nameLabel())
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    FileUpload::make('avatar_path')
                        ->label('Photo')
                        ->image()
                        ->imageEditor()
                        ->directory('creators')
                        ->columnSpanFull(),

                    Textarea::make('bio')
                        ->label('Biographie')
                        ->rows(4)
                        ->columnSpanFull(),
                ]),

            Section::make('Réseaux')
                ->columns(2)
                ->schema([
                    ...static::ownLinks(),

                    TextInput::make('tiktok_handle')
                        ->label('TikTok')
                        ->prefix('@'),

                    TextInput::make('instagram_handle')
                        ->label('Instagram')
                        ->prefix('@'),

                    TextInput::make('youtube_handle')
                        ->label('YouTube')
                        ->prefix('@'),
                ]),

            Section::make('Back-office')
                ->schema([
                    Toggle::make('is_active')
                        ->label(fn ($get) => static::kind($get)->label().' actif')
                        ->helperText('Une fiche inactive n\'apparaît plus dans le sélecteur de création de campagne.')
                        ->default(true),

                    Textarea::make('internal_notes')
                        ->label('Notes internes')
                        ->helperText('Jamais visible par les clippeurs.')
                        ->rows(3),
                ]),
        ]);
    }

    /**
     * Les champs propres aux natures, chacun visible sous la sienne.
     *
     * Un champ masqué n'est pas déshydraté : changer la nature d'une fiche
     * laisse donc intactes les valeurs de l'autre nature, au lieu de les
     * effacer au premier enregistrement. C'est ce qu'on veut pour un streamer
     * qui sort un morceau, et l'inverse serait une perte de données
     * silencieuse.
     *
     * @return list<TextInput>
     */
    protected static function ownLinks(): array
    {
        $champs = [];

        foreach (CreatorKind::cases() as $kind) {
            foreach ($kind->ownLinks() as $nom => $meta) {
                $champ = TextInput::make($nom)
                    ->label($meta['label'])
                    ->placeholder($meta['placeholder'])
                    ->visible(fn ($get) => static::kind($get) === $kind);

                $champs[] = $meta['url']
                    ? $champ->url()->maxLength(255)
                    : $champ->prefix('@')->maxLength(64);
            }
        }

        return $champs;
    }

    /** La nature en cours de saisie, avant même tout enregistrement. */
    protected static function kind(callable $get): CreatorKind
    {
        return CreatorKind::tryFrom((string) $get('kind')) ?? CreatorKind::Artist;
    }
}
