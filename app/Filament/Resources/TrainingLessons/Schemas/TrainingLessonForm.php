<?php

namespace App\Filament\Resources\TrainingLessons\Schemas;

use App\Models\TrainingLesson;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class TrainingLessonForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Leçon')
                ->columns(2)
                ->schema([
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
                        ->unique(ignoreRecord: true)
                        ->maxLength(255)
                        ->dehydrateStateUsing(fn ($state) => Str::slug((string) $state))
                        ->helperText('Adresse de la leçon : /formation/mon-identifiant. '
                            .'C’est aussi le nom du fichier que cherche le script d’enregistrement.'),

                    Textarea::make('summary')
                        ->label('Résumé')
                        ->rows(2)
                        ->maxLength(500)
                        ->columnSpanFull(),

                    TextInput::make('duration_minutes')
                        ->label('Durée (minutes)')
                        ->numeric()
                        ->integer()
                        ->minValue(1),

                    TextInput::make('position')
                        ->label('Ordre')
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->default(0),

                    Toggle::make('is_published')
                        ->label('Publiée')
                        ->default(true)
                        ->helperText('Une leçon non publiée reste invisible des clippeurs.'),
                ]),

            Section::make('Vidéo')
                ->description('Un fichier déposé ici est prioritaire. Sans fichier, la vidéo enregistrée par le script '
                    .'(storage/app/public/'.TrainingLesson::RECORDED_DIRECTORY.'/{identifiant}.webm) est utilisée, '
                    .'puis le lien externe.')
                ->columns(2)
                ->schema([
                    FileUpload::make('video_path')
                        ->label('Fichier vidéo')
                        ->disk('public')
                        ->directory(TrainingLesson::RECORDED_DIRECTORY)
                        ->acceptedFileTypes(['video/mp4', 'video/webm'])
                        ->maxSize(200 * 1024)
                        ->columnSpanFull(),

                    TextInput::make('video_url')
                        ->label('Ou lien YouTube / Vimeo')
                        ->url()
                        ->maxLength(255)
                        ->columnSpanFull(),
                ]),

            Section::make('Contenu')
                ->schema([
                    MarkdownEditor::make('body')
                        ->label('Texte de la leçon')
                        ->toolbarButtons([
                            'heading', 'bold', 'italic', 'link', 'bulletList', 'orderedList', 'blockquote', 'undo', 'redo',
                        ]),
                ]),
        ]);
    }
}
