<?php

namespace App\Filament\Resources\Clippers\Tables;

use App\Models\User;
use App\Services\Clippers\ClipperProgressionService;
use App\Services\Moderation\ClipModerationService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ClippersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Clippeur')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (User $record) => $record->email),

                TextColumn::make('level')
                    ->label('Niveau')
                    ->badge()
                    ->state(fn (User $record) => app(ClipperProgressionService::class)->for($record)->level->label())
                    ->color(fn (User $record) => app(ClipperProgressionService::class)->for($record)->level->color())
                    // Un niveau élevé mais en pause signale un bon clippeur qui
                    // décroche : c'est exactement le moment de le relancer.
                    ->description(function (User $record) {
                        $progression = app(ClipperProgressionService::class)->for($record);

                        return $progression->level->hasPerks() && ! $progression->perksActive
                            ? 'Avantages en pause'
                            : null;
                    }),

                TextColumn::make('social_accounts_count')
                    ->label('Comptes liés')
                    ->counts('socialAccounts')
                    ->alignEnd(),

                TextColumn::make('clips_count')
                    ->label('Clips')
                    ->counts('clips')
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('earned')
                    ->label('Gagné')
                    ->alignEnd()
                    ->state(fn (User $record) => static::euros($record->earnedCents())),

                TextColumn::make('balance')
                    ->label('Solde à payer')
                    ->alignEnd()
                    ->state(fn (User $record) => static::euros($record->availableBalanceCents()))
                    ->color(fn (User $record) => $record->availableBalanceCents() > 0 ? 'warning' : 'gray'),

                TextColumn::make('is_banned')
                    ->label('Statut')
                    ->badge()
                    ->state(fn (User $record) => $record->is_banned ? 'Banni' : 'Actif')
                    ->color(fn (User $record) => $record->is_banned ? 'danger' : 'success')
                    ->description(fn (User $record) => $record->ban_reason),

                TextColumn::make('created_at')
                    ->label('Inscrit le')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TernaryFilter::make('is_banned')
                    ->label('Banni'),
            ])
            ->recordActions([
                ViewAction::make(),
                static::banAction(),
                static::unbanAction(),
            ]);
    }

    protected static function banAction(): Action
    {
        return Action::make('ban')
            ->label('Bannir')
            ->icon('heroicon-o-no-symbol')
            ->color('danger')
            ->visible(fn (User $record) => ! $record->is_banned)
            ->schema([
                Textarea::make('reason')
                    ->label('Motif')
                    ->required()
                    ->rows(3),

                Toggle::make('invalidate_clips')
                    ->label('Invalider aussi tous ses clips validés')
                    // Bannir pour non-respect du brief ne remet pas forcément
                    // en cause les vues déjà générées : le choix reste explicite.
                    ->helperText('Le budget consommé par ses clips sera rendu aux campagnes.')
                    ->default(false),
            ])
            ->modalDescription('Ses retraits en attente seront gelés dans tous les cas.')
            ->action(function (User $record, array $data) {
                app(ClipModerationService::class)->banClipper(
                    clipper: $record,
                    reason: $data['reason'],
                    by: auth()->user(),
                    invalidateClips: (bool) ($data['invalidate_clips'] ?? false),
                );

                Notification::make()->success()->title('Clippeur banni')->send();
            });
    }

    protected static function unbanAction(): Action
    {
        return Action::make('unban')
            ->label('Débannir')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('success')
            ->visible(fn (User $record) => $record->is_banned)
            ->requiresConfirmation()
            ->modalDescription('Les clips invalidés et les retraits annulés ne sont pas rétablis : ils se reprennent un par un.')
            ->action(function (User $record) {
                app(ClipModerationService::class)->unbanClipper($record, auth()->user());

                Notification::make()->success()->title('Clippeur réactivé')->send();
            });
    }

    protected static function euros(int $cents): string
    {
        return number_format($cents / 100, 2, ',', ' ').' €';
    }
}
