<?php

namespace App\Filament\Resources\Campaigns\Pages;

use App\Enums\CampaignStatus;
use App\Exceptions\InvalidCampaignTransition;
use App\Filament\Resources\Campaigns\CampaignResource;
use App\Filament\Widgets\CampaignBudgetOverview;
use App\Models\Campaign;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditCampaign extends EditRecord
{
    protected static string $resource = CampaignResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            CampaignBudgetOverview::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 4;
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make($this->statusActions())
                ->label('Changer le statut')
                ->button()
                ->color('primary'),

            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /**
     * Les actions proposées sont exactement les transitions autorisées par la
     * machine à états. Épuisée n'y figure jamais : seule la consommation réelle
     * du budget peut y faire basculer une campagne.
     *
     * @return array<int, Action>
     */
    protected function statusActions(): array
    {
        /** @var Campaign $campaign */
        $campaign = $this->getRecord();

        return collect($campaign->status->allowedTransitions())
            ->reject(fn (CampaignStatus $target) => $target === CampaignStatus::Exhausted)
            ->map(fn (CampaignStatus $target) => Action::make('status_'.$target->value)
                ->label($target->label())
                ->color($target->color())
                ->requiresConfirmation()
                ->modalHeading('Passer la campagne en « '.$target->label().' » ?')
                ->modalDescription($this->transitionWarning($campaign, $target))
                ->action(function () use ($campaign, $target) {
                    try {
                        $campaign->transitionTo($target);
                    } catch (InvalidCampaignTransition $exception) {
                        Notification::make()
                            ->danger()
                            ->title('Changement de statut refusé')
                            ->body($exception->getMessage())
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->success()
                        ->title('Campagne '.$target->label())
                        ->send();

                    $this->fillForm();
                }))
            ->values()
            ->all();
    }

    protected function transitionWarning(Campaign $campaign, CampaignStatus $target): ?string
    {
        return match ($target) {
            CampaignStatus::Active => $campaign->status === CampaignStatus::Exhausted
                ? 'Le budget doit avoir été augmenté, sinon la réactivation sera refusée.'
                : 'Les clippeurs pourront rejoindre la campagne et leurs vues commenceront à être payées.',
            CampaignStatus::Paused => 'Les vues continueront d\'être comptées mais plus aucune ne sera payée.',
            CampaignStatus::Completed => 'Définitif : la campagne n\'accepte plus ni clips ni crédits.',
            default => null,
        };
    }
}
