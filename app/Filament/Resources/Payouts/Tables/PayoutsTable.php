<?php

namespace App\Filament\Resources\Payouts\Tables;

use App\Enums\PayoutStatus;
use App\Exceptions\PayoutRefused;
use App\Models\Payout;
use App\Services\Accounting\AccountingExport;
use App\Services\Payouts\PayoutService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PayoutsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('Retrait')
                    ->formatStateUsing(fn ($state) => '#'.$state)
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('Clippeur')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (Payout $record) => $record->paypal_email),

                TextColumn::make('amount_cents')
                    ->label('Montant')
                    ->alignEnd()
                    ->sortable()
                    ->formatStateUsing(fn (int $state) => number_format($state / 100, 2, ',', ' ').' €'),

                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn (PayoutStatus $state) => $state->label())
                    ->color(fn (PayoutStatus $state) => match ($state) {
                        PayoutStatus::Paid => 'success',
                        PayoutStatus::Requested => 'warning',
                        PayoutStatus::Approved, PayoutStatus::Processing => 'info',
                        PayoutStatus::Failed => 'danger',
                        PayoutStatus::Cancelled => 'gray',
                    })
                    ->description(fn (Payout $record) => $record->failure_reason),

                TextColumn::make('requested_at')
                    ->label('Demandé')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('paypal_batch_id')
                    ->label('Lot PayPal')
                    ->placeholder('—')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('requested_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Statut')
                    ->multiple()
                    ->options(collect(PayoutStatus::cases())
                        ->mapWithKeys(fn (PayoutStatus $status) => [$status->value => $status->label()])
                        ->all()),
            ])
            ->headerActions([
                static::sendBatchAction(),
                static::exportAction(),
            ])
            ->recordActions([
                static::approveAction(),
                static::cancelAction(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    static::bulkApproveAction(),
                ]),
            ]);
    }

    protected static function approveAction(): Action
    {
        return Action::make('approve')
            ->label('Valider')
            ->icon('heroicon-o-check')
            ->color('success')
            ->visible(fn (Payout $record) => $record->status === PayoutStatus::Requested)
            ->requiresConfirmation()
            ->modalDescription('Le retrait partira au prochain envoi de lot PayPal.')
            ->action(function (Payout $record) {
                app(PayoutService::class)->approve($record, auth()->user());

                Notification::make()->success()->title('Retrait validé')->send();
            });
    }

    protected static function bulkApproveAction(): BulkAction
    {
        return BulkAction::make('approveMany')
            ->label('Valider les retraits')
            ->icon('heroicon-o-check')
            ->color('success')
            ->requiresConfirmation()
            ->action(function (Collection $records) {
                $service = app(PayoutService::class);
                $approved = 0;

                foreach ($records as $payout) {
                    if ($payout->status === PayoutStatus::Requested) {
                        $service->approve($payout, auth()->user());
                        $approved++;
                    }
                }

                Notification::make()
                    ->success()
                    ->title("{$approved} retrait(s) validé(s)")
                    ->send();
            });
    }

    protected static function cancelAction(): Action
    {
        return Action::make('cancel')
            ->label('Annuler')
            ->icon('heroicon-o-x-mark')
            ->color('danger')
            // Un virement en vol ou déjà parti ne s'annule pas côté plateforme.
            ->visible(fn (Payout $record) => in_array(
                $record->status,
                [PayoutStatus::Requested, PayoutStatus::Approved],
                true,
            ))
            ->schema([
                Textarea::make('reason')
                    ->label('Motif communiqué au clippeur')
                    ->required()
                    ->rows(2),
            ])
            ->action(function (Payout $record, array $data) {
                try {
                    app(PayoutService::class)->cancel($record, $data['reason'], auth()->user());
                } catch (PayoutRefused $exception) {
                    Notification::make()->danger()->title('Annulation refusée')->body($exception->getMessage())->send();

                    return;
                }

                Notification::make()->success()->title('Retrait annulé')->send();
            });
    }

    protected static function sendBatchAction(): Action
    {
        return Action::make('sendBatch')
            ->label('Envoyer le lot PayPal')
            ->icon('heroicon-o-paper-airplane')
            ->color('primary')
            ->requiresConfirmation()
            ->modalDescription(function () {
                $approved = Payout::where('status', PayoutStatus::Approved)->get();

                return sprintf(
                    '%d retrait(s) validé(s) pour %s € seront envoyés à PayPal.',
                    $approved->count(),
                    number_format($approved->sum('amount_cents') / 100, 2, ',', ' '),
                );
            })
            ->action(function () {
                try {
                    $result = app(PayoutService::class)->sendApproved();
                } catch (\Throwable $exception) {
                    Notification::make()
                        ->danger()
                        ->title('Envoi refusé par PayPal')
                        ->body($exception->getMessage())
                        ->persistent()
                        ->send();

                    return;
                }

                if (! $result) {
                    Notification::make()->warning()->title('Aucun retrait validé à envoyer')->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title('Lot envoyé')
                    ->body(sprintf(
                        '%d versement(s), %s €. Les statuts définitifs arriveront par webhook.',
                        $result['count'],
                        number_format($result['amount_cents'] / 100, 2, ',', ' '),
                    ))
                    ->send();
            });
    }

    protected static function exportAction(): Action
    {
        return Action::make('export')
            ->label('Export comptable')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->schema([
                DatePicker::make('from')->label('Du'),
                DatePicker::make('to')->label('Au'),
            ])
            ->action(fn (array $data) => app(AccountingExport::class)->download(
                AccountingExport::PAYOUTS,
                filled($data['from'] ?? null) ? Carbon::parse($data['from'])->startOfDay() : null,
                filled($data['to'] ?? null) ? Carbon::parse($data['to'])->endOfDay() : null,
            ));
    }
}
