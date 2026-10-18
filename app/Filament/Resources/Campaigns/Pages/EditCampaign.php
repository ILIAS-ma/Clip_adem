<?php

namespace App\Filament\Resources\Campaigns\Pages;

use App\Enums\CampaignStatus;
use App\Exceptions\InvalidCampaignTransition;
use App\Filament\Resources\Campaigns\CampaignResource;
use App\Filament\Resources\Campaigns\Schemas\CampaignForm;
use App\Filament\Widgets\CampaignBudgetOverview;
use App\Models\Campaign;
use App\Services\Campaigns\ContestService;
use App\Support\Budget\CreditOutcome;
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

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return CampaignForm::withContestBudget($data, $this->getRecord());
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make($this->statusActions())
                ->label('Changer le statut')
                ->button()
                ->color('primary'),

            $this->standingsAction(),
            $this->payPrizesAction(),

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

    /** Le classement en cours (ou arrêté), tel que le voient les clippeurs. */
    protected function standingsAction(): Action
    {
        return Action::make('standings')
            ->label('Classement')
            ->icon('heroicon-o-trophy')
            ->color('gray')
            ->visible(fn () => $this->getRecord()->isContest())
            ->modalHeading(fn () => 'Classement · '.$this->getRecord()->title)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fermer')
            ->modalContent(fn () => view('filament.campaigns.contest-standings', [
                'campaign' => $this->getRecord(),
                'standings' => app(ContestService::class)->standings($this->getRecord(), 50),
            ]));
    }

    /**
     * Versement des prix, une fois le classement arrêté. Réservé au
     * super-admin : c'est de l'argent qui sort en une fois.
     */
    protected function payPrizesAction(): Action
    {
        return Action::make('payPrizes')
            ->label('Verser les prix')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->visible(fn () => $this->getRecord()->isContest()
                && $this->getRecord()->prizes_paid_at === null
                && auth()->user()?->isSuperAdmin())
            ->disabled(fn () => ! $this->getRecord()->contestIsOver())
            ->tooltip(fn () => $this->getRecord()->contestIsOver()
                ? null
                : 'Disponible après la date de fin, ou une fois la campagne terminée.')
            ->requiresConfirmation()
            ->modalHeading('Verser les prix du classement ?')
            ->modalDescription(function () {
                $campaign = $this->getRecord();
                $standings = app(ContestService::class)->standings($campaign);

                return collect($campaign->prizes())
                    ->map(fn (int $cents, int $index) => sprintf(
                        '%s : %s → %s',
                        $index === 0 ? '1re place' : ($index + 1).'e place',
                        number_format($cents / 100, 2, ',', ' ').' €',
                        $standings[$index]->name ?? 'personne (non versé)',
                    ))
                    ->implode(' · ').'. Les gains arrivent tout de suite sur leur solde.';
            })
            ->action(function () {
                try {
                    $paid = app(ContestService::class)->payPrizes($this->getRecord(), auth()->user());
                } catch (\InvalidArgumentException $exception) {
                    Notification::make()->danger()->title('Prix non versés')->body($exception->getMessage())->send();

                    return;
                }

                $failed = collect($paid)->reject(fn (array $line) => $line['result']->creditedCents > 0
                    || $line['result']->outcome === CreditOutcome::AlreadyProcessed);

                $failed->isEmpty()
                    ? Notification::make()->success()->title(count($paid).' prix versés')->send()
                    : Notification::make()->warning()->title('Certains prix n’ont pas pu être versés')
                        ->body($failed->map(fn (array $line) => $line['rank'].'e place : '.$line['result']->outcome->label())->implode(' · '))
                        ->send();

                $this->getRecord()->refresh();
            });
    }

    protected function transitionWarning(Campaign $campaign, CampaignStatus $target): ?string
    {
        return match ($target) {
            CampaignStatus::Active => match (true) {
                $campaign->status === CampaignStatus::Exhausted => 'Le budget doit avoir été augmenté, sinon la réactivation sera refusée.',
                $campaign->isContest() => 'Les clippeurs pourront rejoindre la campagne, et le classement commencera.',
                default => 'Les clippeurs pourront rejoindre la campagne et leurs vues commenceront à être payées.',
            },
            CampaignStatus::Paused => 'Les vues continueront d\'être comptées mais plus aucune ne sera payée.',
            CampaignStatus::Completed => 'Définitif : la campagne n\'accepte plus ni clips ni crédits.',
            default => null,
        };
    }
}
