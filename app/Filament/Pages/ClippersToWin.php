<?php

namespace App\Filament\Pages;

use App\Events\MessagePosted;
use App\Filament\Resources\Clippers\ClipperResource;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\Clippers\RetentionRadar;
use App\Support\Clippers\RetentionSignal;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Clippeurs à relancer avant qu'ils ne partent.
 *
 * La relance passe par la messagerie du site : elle arrive dans le fil du
 * clippeur et dans ses notifications, comme une réponse de l'équipe.
 */
class ClippersToWin extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    protected static ?string $navigationLabel = 'Clippeurs à relancer';

    protected static ?string $title = 'Clippeurs à relancer';

    protected static string|\UnitEnum|null $navigationGroup = 'Communication';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'clippeurs-a-relancer';

    protected string $view = 'filament.pages.clippers-to-win';

    /** @var Collection<int, RetentionSignal>|null */
    protected ?Collection $signals = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->isStaff() ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        // Affiché sur toutes les pages du panel : cinq minutes de cache
        // évitent de refaire le calcul à chaque clic.
        $atRisk = Cache::remember('retention:at-risk-count', now()->addMinutes(5), fn () => app(RetentionRadar::class)
            ->detect()
            ->where('level', RetentionSignal::AT_RISK)
            ->count());

        return $atRisk > 0 ? (string) $atRisk : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    /** @return Collection<int, RetentionSignal> */
    public function signals(): Collection
    {
        return $this->signals ??= app(RetentionRadar::class)->detect();
    }

    protected function signalFor(User $user): ?RetentionSignal
    {
        return $this->signals()->get($user->getKey());
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function () {
                $signals = $this->signals()->sortBy(fn (RetentionSignal $s) => $s->priority());
                $ids = $signals->keys()->all();

                return User::query()
                    ->whereIn('id', $ids ?: [0])
                    // L'ordre du radar, le plus urgent en tête.
                    ->when($ids, fn (Builder $q) => $q->orderByRaw('FIELD(id, '.implode(',', array_map('intval', $ids)).')'));
            })
            ->columns([
                TextColumn::make('name')
                    ->label('Clippeur')
                    ->weight('bold')
                    ->description(fn (User $record) => $record->email)
                    ->url(fn (User $record) => ClipperResource::getUrl('view', ['record' => $record]))
                    ->searchable(),

                TextColumn::make('signal')
                    ->label('Signal')
                    ->badge()
                    ->state(fn (User $record) => $this->signalFor($record)?->label())
                    ->color(fn (User $record) => $this->signalFor($record)?->color()),

                TextColumn::make('reasons')
                    ->label('Pourquoi')
                    ->state(fn (User $record) => implode(' · ', $this->signalFor($record)?->reasons ?? []))
                    ->wrap(),

                TextColumn::make('earned')
                    ->label('Gagné sur 30 j')
                    ->alignEnd()
                    ->state(fn (User $record) => number_format(max(0, $this->signalFor($record)?->earnedInWindowCents ?? 0) / 100, 2, ',', ' ').' €'),

                TextColumn::make('last_clip')
                    ->label('Dernier clip')
                    ->state(fn (User $record) => $this->signalFor($record)?->lastClipAt?->diffForHumans() ?? '—'),

                // Relancé récemment ? Évite d'écrire deux fois la même semaine.
                TextColumn::make('last_contact')
                    ->label('Dernier message de l’équipe')
                    ->state(fn (User $record) => Message::query()
                        ->where('from_staff', true)
                        ->whereHas('conversation', fn ($q) => $q->where('user_id', $record->getKey()))
                        ->latest()
                        ->value('created_at')?->diffForHumans() ?? 'Jamais'),
            ])
            ->filters([
                SelectFilter::make('level')
                    ->label('Signal')
                    ->options(RetentionSignal::levels())
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null)
                        ? $query->whereIn('id', $this->signals()->where('level', $data['value'])->keys()->all() ?: [0])
                        : $query),
            ])
            ->recordActions([
                Action::make('message')
                    ->label('Relancer')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->fillForm(fn (User $record) => [
                        'body' => $this->signalFor($record)?->draftMessage($record->displayName()),
                    ])
                    ->schema([
                        Textarea::make('body')
                            ->label('Message, envoyé dans sa messagerie')
                            ->required()
                            ->maxLength(Message::MAX_LENGTH)
                            ->rows(5),
                    ])
                    ->action(function (User $record, array $data) {
                        $this->post($record, $data['body']);

                        Notification::make()->success()->title('Message envoyé à '.$record->displayName())->send();
                    }),
            ])
            ->toolbarActions([
                BulkAction::make('messageMany')
                    ->label('Relancer la sélection')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->schema([
                        Textarea::make('body')
                            ->label('Message')
                            ->helperText('{prenom} est remplacé par le nom de chaque clippeur.')
                            ->default('Salut {prenom} ! De nouvelles campagnes sont ouvertes, et le budget part au premier arrivé.')
                            ->required()
                            ->maxLength(Message::MAX_LENGTH)
                            ->rows(5),
                    ])
                    ->action(function (Collection $records, array $data) {
                        foreach ($records as $record) {
                            $this->post($record, str_replace('{prenom}', $record->displayName(), $data['body']));
                        }

                        Notification::make()->success()->title($records->count().' clippeurs relancés')->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->emptyStateHeading('Personne à relancer')
            ->emptyStateDescription('Aucun clippeur ne montre de signe de décrochage en ce moment.');
    }

    protected function post(User $clipper, string $body): void
    {
        MessagePosted::dispatch(Conversation::for($clipper)->post(auth()->user(), trim($body)));
    }
}
