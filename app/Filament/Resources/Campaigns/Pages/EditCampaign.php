<?php

namespace App\Filament\Resources\Campaigns\Pages;

use App\Enums\CampaignStatus;
use App\Enums\ContestMetric;
use App\Exceptions\InvalidCampaignTransition;
use App\Filament\Resources\Campaigns\CampaignResource;
use App\Filament\Resources\Campaigns\Schemas\CampaignForm;
use App\Filament\Widgets\CampaignBudgetOverview;
use App\Enums\ModerationAction;
use App\Models\Campaign;
use App\Models\ModerationLog;
use App\Services\Campaigns\CampaignStats;
use App\Services\Campaigns\ContestReport;
use App\Services\Campaigns\ContestService;
use App\Services\Campaigns\ContestShadowStandings;
use App\Support\Budget\CreditOutcome;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Textarea;
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

            $this->activateOnCreditAction(),
            $this->standingsAction(),
            $this->exportStatsAction(),
            $this->awardRewardsAction(),

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

    /**
     * Ouvre une campagne payée aux vues avant que le créateur ait payé : le
     * super-admin avance l'argent. Motif obligatoire, trace dans le journal.
     */
    protected function activateOnCreditAction(): Action
    {
        return Action::make('activateOnCredit')
            ->label('Activer à crédit')
            ->icon('heroicon-o-bolt')
            ->color('warning')
            ->visible(function () {
                $campaign = $this->getRecord();

                return (auth()->user()?->isSuperAdmin() ?? false)
                    && ! $campaign->isContest()
                    && $campaign->status->canTransitionTo(CampaignStatus::Active)
                    && $campaign->fundedCents() < $campaign->budget_total_cents;
            })
            ->modalHeading('Activer sans encaissement ?')
            ->modalDescription('Le créateur n’a pas encore payé : les clippeurs seront payés sur ce budget alors que l’argent n’est pas reçu. '
                .'C’est vous qui portez le risque. Pensez à enregistrer l’encaissement dès réception.')
            ->schema([
                Textarea::make('reason')->label('Motif')->placeholder('Ex. : paiement du créateur promis en fin de campagne')->required(),
            ])
            ->action(function (array $data) {
                $campaign = $this->getRecord();

                try {
                    $campaign->transitionTo(CampaignStatus::Active, onCredit: true);
                } catch (InvalidCampaignTransition $exception) {
                    Notification::make()->danger()->title('Changement de statut refusé')->body($exception->getMessage())->send();

                    return;
                }

                ModerationLog::record(ModerationAction::SettingsChanged, $campaign, auth()->user(), $data['reason'], [
                    'activated_on_credit' => true,
                    'budget_cents' => $campaign->budget_total_cents,
                    'funded_cents' => $campaign->fundedCents(),
                ]);

                Notification::make()->success()->title('Campagne activée à crédit')
                    ->body('Enregistrez l’encaissement du créateur dès qu’il aura payé.')->send();

                $this->fillForm();
            });
    }

    /** Toutes les statistiques de la campagne dans un classeur Excel : classements, clippeurs, clips, modération. */
    protected function exportStatsAction(): Action
    {
        return Action::make('exportStats')
            ->label('Exporter (Excel)')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->action(function () {
                $stats = app(CampaignStats::class);
                $campaign = $this->getRecord();

                return response()->download($stats->export($campaign), $stats->filename($campaign))->deleteFileAfterSend(true);
            });
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
            ->modalWidth('5xl')
            ->modalContent(fn () => view('filament.campaigns.contest-standings', [
                'campaign' => $this->getRecord(),
                'report' => app(ContestReport::class)->for($this->getRecord()),
                // Le classement qui compterait aussi les vidéos non soumises : le super-admin seul.
                'showShadow' => auth()->user()?->isSuperAdmin() ?? false,
                'shadow' => (auth()->user()?->isSuperAdmin() ?? false) ? ContestShadowStandings::cached($this->getRecord()) : null,
            ]));
    }

    /**
     * Clôture du classement, une fois la date de fin passée : argent versé
     * sur les soldes, lots annoncés aux gagnants, podium conservé. Réservé au
     * super-admin : de l'argent peut sortir en une fois.
     */
    protected function awardRewardsAction(): Action
    {
        return Action::make('awardRewards')
            ->label('Clôturer et attribuer')
            ->icon('heroicon-o-trophy')
            ->color('success')
            ->visible(fn () => $this->getRecord()->isContest()
                && $this->getRecord()->prizes_paid_at === null
                && auth()->user()?->isSuperAdmin())
            ->disabled(fn () => ! $this->getRecord()->contestIsOver())
            ->tooltip(fn () => $this->getRecord()->contestIsOver()
                ? null
                : 'Disponible après la date de fin, ou une fois la campagne terminée.')
            ->requiresConfirmation()
            ->modalHeading('Clôturer le classement ?')
            ->modalDescription(function () {
                $campaign = $this->getRecord();

                if ($campaign->allRewards() === []) {
                    return 'Classements sans récompense : les podiums seront figés et affichés aux clippeurs.';
                }

                $lines = [];

                foreach (ContestMetric::cases() as $metric) {
                    $standings = app(ContestService::class)->standings($campaign, board: $metric);

                    foreach ($campaign->rewards($metric) as $index => $reward) {
                        $lines[] = sprintf(
                            '%s, %s : %s → %s',
                            $metric->title(),
                            $index === 0 ? '1re place' : ($index + 1).'e place',
                            $reward->display(),
                            $standings[$index]->name ?? 'personne (non attribué)',
                        );
                    }
                }

                // Un second regard avant de verser : les clips des gagnants ont-ils été vérifiés ?
                $winners = [];

                foreach (ContestMetric::cases() as $metric) {
                    $standings = app(ContestService::class)->standings($campaign, board: $metric);

                    foreach (array_keys($campaign->rewards($metric)) as $index) {
                        if (isset($standings[$index])) {
                            $winners[] = $standings[$index]->user_id;
                        }
                    }
                }

                $unchecked = $winners === [] ? 0 : \App\Models\Clip::where('campaign_id', $campaign->id)
                    ->whereIn('user_id', array_unique($winners))
                    ->where('status', \App\Enums\ClipStatus::Approved)
                    ->whereNull('reviewed_at')
                    ->count();

                $warning = $unchecked > 0
                    ? ' ⚠ '.$unchecked.' clip'.($unchecked > 1 ? 's' : '').' des gagnants ne sont pas encore vérifiés (page « Vérification finale »).'
                    : '';

                return implode(' · ', $lines).'. L’argent arrive tout de suite sur les soldes ; '
                    .'les gagnants d’un lot reçoivent un message pour organiser la remise.'.$warning;
            })
            ->action(function () {
                try {
                    $awarded = app(ContestService::class)->awardRewards($this->getRecord(), auth()->user());
                } catch (\InvalidArgumentException $exception) {
                    Notification::make()->danger()->title('Classement non clôturé')->body($exception->getMessage())->send();

                    return;
                }

                $failed = collect($awarded)->filter(fn (array $line) => $line['result'] !== null
                    && $line['result']->creditedCents === 0
                    && $line['result']->outcome !== CreditOutcome::AlreadyProcessed);

                $failed->isEmpty()
                    ? Notification::make()->success()->title('Classement clôturé')
                        ->body(count($awarded).' récompense(s) attribuée(s).')->send()
                    : Notification::make()->warning()->title('Certaines récompenses en argent n’ont pas pu être versées')
                        ->body($failed->map(fn (array $line) => $line['board']->title().', '.$line['rank'].'e place : '.$line['result']->outcome->label())->implode(' · ')
                            .'. Corrigez puis relancez : rien ne sera versé deux fois.')
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
