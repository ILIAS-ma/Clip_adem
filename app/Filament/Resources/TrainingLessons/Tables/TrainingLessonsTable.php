<?php

namespace App\Filament\Resources\TrainingLessons\Tables;

use App\Models\TrainingLesson;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class TrainingLessonsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('position')
                    ->label('#')
                    ->sortable(),

                TextColumn::make('title')
                    ->label('Leçon')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (TrainingLesson $record) => $record->summary),

                IconColumn::make('has_video')
                    ->label('Vidéo')
                    ->boolean()
                    ->state(fn (TrainingLesson $record) => $record->hasVideo()),

                // Combien de clippeurs sont allés au bout : une leçon que
                // personne ne termine est une leçon à réécrire.
                TextColumn::make('completions_count')
                    ->label('Terminée par')
                    ->counts('completions')
                    ->alignEnd()
                    ->sortable(),

                IconColumn::make('is_published')
                    ->label('Publiée')
                    ->boolean(),
            ])
            ->defaultSort('position')
            ->reorderable('position')
            // Par défaut, la liste montre ce que voient les clippeurs ; les
            // leçons masquées restent à un clic, dans le filtre.
            ->filters([
                TernaryFilter::make('is_published')
                    ->label('Publiée')
                    ->trueLabel('Publiées seulement')
                    ->falseLabel('Masquées seulement')
                    ->placeholder('Toutes')
                    ->default(true),
            ])
            ->recordActions([
                // Réservé au personnel (c'est le back-office) : la vidéo avec son
                // son, en mp4, pour la donner à quelqu'un d'autre.
                Action::make('download')
                    ->label('Télécharger')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (TrainingLesson $record) => $record->downloadableVideoPath() !== null)
                    ->action(function (TrainingLesson $record) {
                        $path = $record->downloadableVideoPath();

                        return response()->download($path, Str::slug($record->title).'.'.pathinfo($path, PATHINFO_EXTENSION));
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
