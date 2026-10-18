<?php

namespace App\Filament\Resources\Campaigns\RelationManagers;

use App\Filament\Resources\Participations\ParticipationResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

/** Les demandes de participation à cette campagne, avec Accepter / Refuser. */
class ParticipationsRelationManager extends RelationManager
{
    protected static string $relationship = 'participations';

    protected static ?string $title = 'Participations';

    protected static ?string $modelLabel = 'participation';

    protected static ?string $pluralModelLabel = 'participations';

    public function table(Table $table): Table
    {
        return ParticipationResource::participationsTable($table, withCampaign: false);
    }
}
