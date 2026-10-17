<?php

namespace App\Filament\Resources\Clips\Tables;

use App\Enums\ClipStatus;
use App\Enums\Platform;
use App\Models\Clip;
use App\Services\Budget\BonusService;
use App\Services\Moderation\ClipModerationService;
use App\Services\Moderation\SuspiciousViewsDetector;
use App\Services\Reporting\LandingShowcase;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
                    // La disparition prime sur le statut à l'affichage : un
                    // clip « Validé » dont la publication n'existe plus est
                    // exactement ce qu'il faut voir en premier.
                    ->formatStateUsing(fn (ClipStatus $state, Clip $record) => $record->hasDisappeared()
                        ? 'Publication disparue'
                        : $state->label())
                    ->color(fn (ClipStatus $state, Clip $record) => $record->hasDisappeared()
                        ? 'danger'
                        : match ($state) {
                            ClipStatus::Approved => 'success',
                            ClipStatus::PendingReview => 'warning',
                            ClipStatus::Rejected => 'gray',
                            ClipStatus::Invalidated => 'danger',
                        })
                    ->description(fn (Clip $record) => $record->hasDisappeared()
                        ? 'Introuvable depuis le '.$record->missing_since?->format('d/m/Y')
                        : null)
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

                Filter::make('featured')
                    ->label('Mis en avant sur l’accueil')
                    ->query(fn ($query) => $query->whereNotNull('featured_at')),

                Filter::make('unpaid')
                    ->label('Vues non rémunérées')
                    ->query(fn ($query) => $query->whereColumn('views_total', '>', 'paid_views')),

                /*
                 * Publier, encaisser, effacer : le vecteur de fraude le moins
                 * coûteux contre la plateforme. Ce filtre le rend visible en
                 * un clic, en mettant devant ceux qui ont déjà été payés.
                 */
                Filter::make('disappeared')
                    ->label('Publication disparue')
                    ->query(fn ($query) => $query
                        ->where('missing_checks', '>=', Clip::MISSING_THRESHOLD)
                        ->orderByDesc('earned_cents')),
            ])
            ->recordActions([
                ActionGroup::make([
                    static::approveAction(),
                    static::rejectAction(),
                    static::invalidateAction(),
                    static::inspectAction(),
                    static::featureAction(),
                    static::unfeatureAction(),
                    static::bonusAction(),
                ]),
            ]);
    }

    /**
     * Prime fixe, prise sur le budget de la campagne. Réservée au super-admin :
     * c'est le seul geste de modération qui sort de l'argent sans vue en face.
     */
    protected static function bonusAction(): Action
    {
        return Action::make('bonus')
            ->label('Accorder une prime')
            ->icon('heroicon-o-gift')
            ->color('success')
            ->visible(fn (Clip $record) => $record->status === ClipStatus::Approved && auth()->user()?->isSuperAdmin())
            ->schema([
                TextInput::make('amount')
                    ->label('Montant')
                    ->numeric()
                    ->prefix('€')
                    ->step(0.01)
                    ->minValue(0.01)
                    ->required(),

                Textarea::make('reason')
                    ->label('Message au clippeur')
                    ->placeholder('Ex. : clip exemplaire, repris dans le brief')
                    ->required()
                    ->maxLength(255)
                    ->rows(2),
            ])
            ->modalDescription(fn (Clip $record) => sprintf(
                'Prise sur le budget de « %s » (%s restants), créditée tout de suite sur le solde du clippeur.',
                $record->campaign?->title,
                number_format(($record->campaign?->remainingCents() ?? 0) / 100, 2, ',', ' ').' €',
            ))
            ->action(function (Clip $record, array $data) {
                $cents = (int) round(((float) $data['amount']) * 100);
                $result = app(BonusService::class)->grantManual($record, $cents, $data['reason'], auth()->user());

                $result->creditedCents > 0
                    ? Notification::make()->success()->title('Prime accordée')
                        ->body(number_format($cents / 100, 2, ',', ' ').' € crédités au clippeur.')->send()
                    : Notification::make()->danger()->title('Prime non versée')
                        ->body($result->outcome->label())->send();
            });
    }

    /** Vitrine de la page d'accueil : un choix humain, clip par clip. */
    protected static function featureAction(): Action
    {
        return Action::make('feature')
            ->label('Mettre en avant sur l’accueil')
            ->icon('heroicon-o-star')
            ->color('success')
            ->visible(fn (Clip $record) => $record->featured_at === null && $record->status === ClipStatus::Approved)
            ->requiresConfirmation()
            ->modalDescription('Le clip apparaîtra dans la vitrine publique de la page d’accueil, avec ses vues. '
                .'Le nom du clippeur n’est pas affiché.')
            ->action(function (Clip $record) {
                app(LandingShowcase::class)->feature($record);

                Notification::make()->success()->title('Clip mis en avant')->send();
            });
    }

    protected static function unfeatureAction(): Action
    {
        return Action::make('unfeature')
            ->label('Retirer de l’accueil')
            ->icon('heroicon-o-star')
            ->color('gray')
            ->visible(fn (Clip $record) => $record->featured_at !== null)
            ->action(function (Clip $record) {
                app(LandingShowcase::class)->unfeature($record);

                Notification::make()->success()->title('Clip retiré de l’accueil')->send();
            });
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
