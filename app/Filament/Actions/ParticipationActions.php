<?php

namespace App\Filament\Actions;

use App\Enums\ParticipationStatus;
use App\Models\CampaignParticipation;
use App\Services\Clips\ParticipationService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Accepter ou refuser une demande de participation, depuis la liste globale
 * ou depuis la fiche d'une campagne. Le clippeur est prévenu dans les deux cas.
 */
class ParticipationActions
{
    public static function approve(): Action
    {
        return Action::make('approveParticipation')
            ->label('Accepter')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (CampaignParticipation $record) => $record->status !== ParticipationStatus::Approved)
            ->action(function (CampaignParticipation $record) {
                app(ParticipationService::class)->approve($record, auth()->user());

                Notification::make()->success()->title('Participation acceptée')->send();
            });
    }

    public static function reject(): Action
    {
        return Action::make('rejectParticipation')
            ->label('Refuser')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (CampaignParticipation $record) => $record->status === ParticipationStatus::Pending)
            ->schema([
                Textarea::make('reason')
                    ->label('Motif, envoyé au clippeur')
                    ->placeholder('Ex. : compte trop récent, contenu hors sujet…')
                    ->required()
                    ->maxLength(500)
                    ->rows(3),
            ])
            ->action(function (CampaignParticipation $record, array $data) {
                app(ParticipationService::class)->reject($record, $data['reason'], auth()->user());

                Notification::make()->success()->title('Participation refusée, clippeur prévenu')->send();
            });
    }

    public static function approveMany(): BulkAction
    {
        return BulkAction::make('approveParticipations')
            ->label('Accepter la sélection')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->action(function (Collection $records) {
                $service = app(ParticipationService::class);
                $pending = $records->filter(fn (CampaignParticipation $p) => $p->status !== ParticipationStatus::Approved);

                $pending->each(fn (CampaignParticipation $p) => $service->approve($p, auth()->user()));

                Notification::make()->success()->title($pending->count().' participation(s) acceptée(s)')->send();
            })
            ->deselectRecordsAfterCompletion();
    }
}
