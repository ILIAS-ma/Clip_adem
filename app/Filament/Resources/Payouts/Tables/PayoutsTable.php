<?php

namespace App\Filament\Resources\Payouts\Tables;

use App\Enums\PayoutMethod;
use App\Enums\PayoutStatus;
use App\Exceptions\PayoutRefused;
use App\Models\Payout;
use App\Services\Accounting\AccountingExport;
use App\Services\Payouts\BankTransferExport;
use App\Services\Payouts\PayoutService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
                    ->description(fn (Payout $record) => $record->destinationLabel()),

                TextColumn::make('method')
                    ->label('Mode')
                    ->badge()
                    ->formatStateUsing(fn (?PayoutMethod $state) => ($state ?? PayoutMethod::PayPal)->label())
                    ->color(fn (?PayoutMethod $state) => ($state ?? PayoutMethod::PayPal) === PayoutMethod::PayPal
                        ? 'info'
                        : 'warning'),

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

                SelectFilter::make('method')
                    ->label('Mode')
                    ->options(collect(PayoutMethod::cases())
                        ->mapWithKeys(fn (PayoutMethod $method) => [$method->value => $method->label()])
                        ->all()),
            ])
            ->headerActions([
                static::sendBatchAction(),
                static::bankTransferFileAction(),
                static::exportAction(),
            ])
            ->recordActions([
                static::approveAction(),
                static::markPaidAction(),
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
            ->modalDescription(fn (Payout $record) => $record->isManual()
                ? 'Le virement sera à exécuter depuis la banque, puis à pointer ici.'
                : 'Le retrait partira au prochain envoi de lot PayPal.')
            ->action(function (Payout $record) {
                app(PayoutService::class)->approve($record, auth()->user());

                Notification::make()->success()->title('Retrait validé')->send();
            });
    }

    /**
     * Pointage d'un virement bancaire exécuté depuis la banque.
     *
     * Rien ne peut le faire à notre place : aucune API ne nous dit qu'un SEPA
     * est parti. La référence saisie est ce qui permettra le rapprochement
     * avec le relevé bancaire.
     */
    protected static function markPaidAction(): Action
    {
        return Action::make('markPaid')
            ->label('Virement effectué')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->visible(fn (Payout $record) => $record->isManual()
                && $record->status === PayoutStatus::Approved)
            ->schema([
                TextInput::make('reference')
                    ->label('Référence du virement')
                    ->helperText('Celle du relevé bancaire. Facultative, mais elle vous sauvera un jour.')
                    ->maxLength(64),
            ])
            ->action(function (Payout $record, array $data) {
                try {
                    app(PayoutService::class)->markPaid($record, auth()->user(), $data['reference'] ?? null);
                } catch (PayoutRefused $exception) {
                    Notification::make()->danger()->title('Pointage refusé')->body($exception->getMessage())->send();

                    return;
                }

                Notification::make()->success()->title('Virement pointé comme versé')->send();
            });
    }

    /**
     * Le fichier des virements à saisir en banque.
     *
     * Réservé au super administrateur : il contient des IBAN en clair, ce qui
     * est précisément son intérêt et sa dangerosité.
     */
    protected static function bankTransferFileAction(): Action
    {
        return Action::make('bankTransferFile')
            ->label('Fichier des virements')
            ->icon('heroicon-o-building-library')
            ->color('gray')
            ->visible(fn () => auth()->user()?->isSuperAdmin())
            ->requiresConfirmation()
            ->modalHeading('Télécharger le fichier des virements')
            ->modalDescription(function () {
                $pending = app(BankTransferExport::class)->pending();

                return sprintf(
                    '%d virement(s) validé(s) pour %s €. Le fichier contient les IBAN en clair.',
                    $pending->count(),
                    number_format($pending->sum('amount_cents') / 100, 2, ',', ' '),
                );
            })
            ->action(fn () => app(BankTransferExport::class)->download());
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
                // Seuls les PayPal partent en lot : compter les virements
                // bancaires ici annoncerait un envoi qui n'aura pas lieu.
                $approved = Payout::where('status', PayoutStatus::Approved)
                    ->where('method', PayoutMethod::PayPal->value)
                    ->get();

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
