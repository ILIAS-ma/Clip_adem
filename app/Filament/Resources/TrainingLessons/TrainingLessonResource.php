<?php

namespace App\Filament\Resources\TrainingLessons;

use App\Filament\Resources\TrainingLessons\Pages\CreateTrainingLesson;
use App\Filament\Resources\TrainingLessons\Pages\EditTrainingLesson;
use App\Filament\Resources\TrainingLessons\Pages\ListTrainingLessons;
use App\Filament\Resources\TrainingLessons\Schemas\TrainingLessonForm;
use App\Filament\Resources\TrainingLessons\Tables\TrainingLessonsTable;
use App\Models\TrainingLesson;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/** Leçons de l'espace formation des clippeurs. */
class TrainingLessonResource extends Resource
{
    protected static ?string $model = TrainingLesson::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?string $navigationLabel = 'Formation';

    protected static ?string $modelLabel = 'leçon';

    protected static ?string $pluralModelLabel = 'leçons';

    protected static string|\UnitEnum|null $navigationGroup = 'Communication';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return TrainingLessonForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TrainingLessonsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTrainingLessons::route('/'),
            'create' => CreateTrainingLesson::route('/create'),
            'edit' => EditTrainingLesson::route('/{record}/edit'),
        ];
    }
}
