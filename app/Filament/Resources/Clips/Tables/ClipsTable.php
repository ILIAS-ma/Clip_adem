<?php

namespace App\Filament\Resources\Clips\Tables;

use App\Enums\ClipStatus;
use App\Enums\Platform;
use App\Models\Clip;
use App\Services\Moderation\ClipModerationService;
use App\Services\Moderation\SuspiciousViewsDetector;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ClipsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('url')
                    ->label('Clip')
                    ->formatStateUsing(fn (Clip $record) => $record->platform->label().' · '.$record->external_post_id)
                    ->url(fn (Clip $record) => $record->url, shouldOpenInNewTab: true)
                    ->description(fn (Clip $record) => $record->campaign?->title)
                    ->searchable(['external_post_id', 'url'])
                    ->weight('bold'),

                TextColumn::make('user.name')
                    ->label('Clippeur')
                    ->searchable()
                    ->description(fn (Clip $record) => $record->user?->is_banned ? 'Banni' : null)
                    ->color(fn (Clip $record) => $record->user?->is_banned ? 'danger' : null),

                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn (ClipStatus $state) => $state->label())
                    ->color(fn (ClipStatus $state) => match ($state) {
                        ClipStatus::Approved => 'success',
                        ClipStatus::PendingReview => 'warning',
                        ClipStatus::Rejected => 'gray',
                        ClipStatus::Invalidated => 'danger',
                    }),

                TextColumn::make('views_total')
                    ->label('Vues')
                    ->alignEnd()
                    ->sortable()
                    ->formatStateUsing(fn (int $state) => number_format($state, 0, ',', ' '))
                    // Les vues non payées expliquent pourquoi les gains d'un
                    // clippeur cessent de monter : campagne épuisée ou plafond.
                    ->description(fn (Clip $record) => $record->unpaidViews() > 0
                        ? number_format($record->unpaidViews(), 0, ',', ' ').' non payées'
                        : null),

                TextColumn::make('earned_cents')
                    ->label('Coût')
                    ->alignEnd()
                    ->sortable()
                    ->formatStateUsing(fn (int $state) => number_format($state / 100, 2, ',', ' ').' €'),

                TextColumn::make('posted_at')
                    ->label('Publié')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('—'),
            ])
            ->defaultSort('posted_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Statut')
                    ->multiple()
                    ->options(collect(ClipStatus::cases())
                        ->mapWithKeys(fn (ClipStatus $status) => [$status->value => $status->label()])
                        ->all()),

                SelectFilter::make('platform')
                    ->label('Plateforme')
                    ->options(collect(Platform::cases())
                        ->mapWithKeys(fn (Platform $platform) => [$platform->value => $platform->label()])
                        ->all()),

                SelectFilter::make('campaign')
                    ->label('Campagne')
                    ->relationship('campaign', 'title')
                    ->searchable()
                    ->preload(),

                Filter::make('suspicious')
                    ->label('Vues suspectes')
                    ->query(fn ($query) => app(SuspiciousViewsDetector::class)->scopeSuspicious($query)),

                Filter::make('unpaid')
                    ->label('Vues non rémunérées')
                    ->query(fn ($query) => $query->whereColumn('views_total', '>', 'paid_views')),
            ])
            ->recordActions([
                ActionGroup::make([
                    static::approveAction(),
                    static::rejectAction(),
                    static::invalidateAction(),
                    static::inspectAction(),
                ]),
            ]);
    }

    protected static function approveAction(): Action
    {
        return Action::make('approve')
            ->label('Valider')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (Clip $record) => $record->status !== ClipStatus::Approved)
            ->requiresConfirmation()
            ->modalDescription('Le clip deviendra rémunérable dès la prochaine synchronisation des vues.')
            ->action(function (Clip $record) {
                app(ClipModerationService::class)->approve($record, auth()->user());

                Notification::make()->success()->title('Clip validé')->send();
            });
    }

    protected static function rejectAction(): Action
    {
        return Action::make('reject')
            ->label('Refuser')
            ->icon('heroicon-o-x-circle')
            ->color('warning')
            // Un clip déjà payé ne se « refuse » pas : il s'invalide, ce qui
            // rend le budget. Proposer les deux prêterait à confusion.
            ->visible(fn (Clip $record) => $record->earned_cents === 0
                && $record->status !== ClipStatus::Rejected)
            ->schema([
                Textarea::make('reason')
                    ->label('Motif communiqué au clippeur')
                    ->required()
                    ->rows(3),
            ])
            ->action(function (Clip $record, array $data) {
                app(ClipModerationService::class)->reject($record, $data['reason'], auth()->user());

                Notification::make()->success()->title('Clip refusé')->send();
            });
    }

    protected static function invalidateAction(): Action
    {
        return Action::make('invalidate')
            ->label('Invalider et rembourser')
            ->icon('heroicon-o-shield-exclamation')
            ->color('danger')
            ->visible(fn (Clip $record) => $record->status !== ClipStatus::Invalidated)
            ->schema([
                Textarea::make('reason')
                    ->label('Motif')
                    ->required()
                    ->rows(3)
                    ->placeholder('Vues achetées, brief non respecté, contenu réutilisé…'),
            ])
            ->modalDescription(fn (Clip $record) => sprintf(
                'Les %s € déjà versés seront repris au clippeur et rendus au budget de la campagne.',
                number_format($record->earned_cents / 100, 2, ',', ' '),
            ))
            ->action(function (Clip $record, array $data) {
                $refunded = $record->earned_cents;

                app(ClipModerationService::class)->invalidate($record, $data['reason'], auth()->user());

                Notification::make()
                    ->success()
                    ->title('Clip invalidé')
                    ->body(number_format($refunded / 100, 2, ',', ' ').' € rendus au budget.')
                    ->send();
            });
    }

    /** Détail des signaux qui ont fait remonter le clip comme suspect. */
    protected static function inspectAction(): Action
    {
        return Action::make('inspect')
            ->label('Analyser les vues')
            ->icon('heroicon-o-magnifying-glass')
            ->color('gray')
            ->modalSubmitAction(false)
            ->modalContent(function (Clip $record) {
                $flags = app(SuspiciousViewsDetector::class)->flags($record);

                return view('filament.moderation.suspicious-flags', [
                    'clip' => $record,
                    'flags' => $flags,
                ]);
            });
    }
}
