<?php

namespace App\Filament\Resources\Campaigns\Schemas;

use App\Enums\AssetKind;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

/**
 * Les champs d'une pièce du brief.
 *
 * Écrits une fois et réutilisés par le formulaire de campagne et par l'onglet
 * « Matière première » de la page d'édition : deux formulaires distincts pour
 * la même table finiraient par accepter des choses différentes.
 */
class CampaignAssetForm
{
    /** @return array<int, mixed> */
    public static function components(): array
    {
        return [
            Select::make('kind')
                ->label('Type de fichier')
                ->options(AssetKind::options())
                ->default(AssetKind::Audio->value)
                ->required()
                ->selectablePlaceholder(false)
                // Le type commande les extensions acceptées et le poids
                // maximum : il doit être relu à chaque changement.
                ->live()
                ->helperText(fn ($get) => AssetKind::fromValue($get('kind'))->hint()),

            TextInput::make('label')
                ->label('Intitulé')
                ->placeholder('Son officiel — refrain')
                ->required()
                ->maxLength(120)
                ->helperText('Ce que le clippeur lira dans la liste.'),

            Textarea::make('description')
                ->label('Comment s’en servir')
                ->rows(2)
                ->columnSpanFull()
                ->maxLength(500)
                ->placeholder('Caler le drop à 0:12, ne pas couper avant la fin du refrain.')
                ->helperText('Facultatif, mais c’est ce qui évite les clips à refaire.'),

            FileUpload::make('path')
                ->label('Fichier')
                ->disk('public')
                ->directory('campagnes/pieces')
                ->visibility('public')
                ->columnSpanFull()
                ->downloadable()
                ->openable()
                // Le nom d'origine est conservé à l'affichage : « Son
                // officiel.mp3 » se reconnaît, pas un identifiant aléatoire.
                ->previewable()
                ->acceptedFileTypes(fn ($get) => AssetKind::fromValue($get('kind'))->acceptedMimeTypes())
                ->maxSize(fn ($get) => AssetKind::fromValue($get('kind'))->maxSizeKb())
                ->helperText(fn ($get) => static::uploadHint(AssetKind::fromValue($get('kind')))),

            TextInput::make('external_url')
                ->label('Ou lien externe')
                ->url()
                ->maxLength(2048)
                ->columnSpanFull()
                ->placeholder('https://drive.google.com/…')
                ->helperText(
                    'Pour ce qui est déjà hébergé ailleurs — un Drive, un son TikTok. '
                    .'Laissez vide si vous avez déposé un fichier : le fichier l’emporte.'
                ),

            Toggle::make('is_required')
                ->label('Élément imposé')
                ->helperText('Affiché « Imposé » au clippeur, et vérifié en modération.')
                ->inline(false),
        ];
    }

    /** Formats et poids acceptés, en clair sous le sélecteur de fichier. */
    protected static function uploadHint(AssetKind $kind): string
    {
        return sprintf(
            'Formats : %s. Poids maximum : %d Mo.',
            implode(', ', $kind->acceptedExtensions()),
            $kind->maxSizeMb(),
        );
    }
}
