<?php

namespace App\Filament\Resources\Clippers\RelationManagers;

use App\Enums\ClipStatus;
use App\Enums\Platform;
use App\Models\Clip;
use App\Services\Moderation\SuspiciousViewsDetector;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * L'historique complet des clips d'un clippeur : tout ce qu'il a soumis, avec
 * ses vues et ses gains, et de quoi retrouver un clip précis — recherche,
 * filtres, tri, et totaux qui suivent les filtres.
 */
class ClipsRelationManager extends RelationManager
{
    protected static string $relationship = 'clips';

    protected static ?string $title = 'Historique des clips';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return (string) $ownerRecord->clips()->count();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('external_post_id')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('campaign.creator'))
            ->columns([
                ImageColumn::make('thumbnail_url')
                    ->label('')
                    ->height(56)
                    ->width(32)
                    ->extraImgAttributes(['loading' => 'lazy', 'referrerpolicy' => 'no-referrer', 'class' => 'rounded object-cover']),

                TextColumn::make('campaign.title')
                    ->label('Campagne')
                    ->weight('bold')
                    ->searchable()
                    ->wrap()
                    ->description(fn (Clip $record) => $record->campaign?->creator?->name),

                TextColumn::make('external_post_id')
                    ->label('Publication')
                    // L'identifiant complet est long et ne dit rien : la fin suffit pour
                    // s'y retrouver, l'entier est dans l'infobulle et dans la recherche.
                    ->formatStateUsing(fn (Clip $record) => $record->platform->label().' · …'.substr($record->external_post_id, -6))
                    ->tooltip(fn (Clip $record) => $record->external_post_id)
                    ->url(fn (Clip $record) => $record->url, shouldOpenInNewTab: true)
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->iconPosition('after')
                    ->color('primary')
                    ->searchable(['external_post_id', 'url', 'caption'])
                    ->wrap()
                    ->description(fn (Clip $record) => $record->caption ? str($record->caption)->limit(36)->toString() : null),

                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    // Une publication disparue prime sur le statut : c'est ce
                    // qu'il faut voir en premier dans l'historique d'un clippeur.
                    ->formatStateUsing(fn (ClipStatus $state, Clip $record) => $record->hasDisappeared() ? 'Publication disparue' : $state->label())
                    ->color(fn (ClipStatus $state, Clip $record) => $record->hasDisappeared() ? 'danger' : match ($state) {
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
                    ->description(fn (Clip $record) => $record->unpaidViews() > 0
                        ? number_format($record->unpaidViews(), 0, ',', ' ').' non payées'
                        : null)
                    ->summarize(Sum::make()->label('Total')->formatStateUsing(fn ($state) => number_format((int) $state, 0, ',', ' '))),

                TextColumn::make('earned_cents')
                    ->label('Gagné')
                    ->alignEnd()
                    ->sortable()
                    ->formatStateUsing(fn (int $state) => number_format($state / 100, 2, ',', ' ').' €')
                    ->summarize(Sum::make()->label('Total')->formatStateUsing(fn ($state) => number_format(((int) $state) / 100, 2, ',', ' ').' €')),

                TextColumn::make('posted_at')
                    ->label('Publié')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('submitted_at')
                    ->label('Soumis')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('submitted_at', 'desc')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->striped()
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(4)
            ->persistFiltersInSession()
            ->filters([
                SelectFilter::make('campaign_id')
                    ->label('Campagne')
                    ->options(fn () => $this->getOwnerRecord()->clips()
                        ->join('campaigns', 'campaigns.id', '=', 'clips.campaign_id')
                        ->orderBy('campaigns.title')
                        ->pluck('campaigns.title', 'campaigns.id')
                        ->all())
                    ->multiple()
                    ->searchable(),

                SelectFilter::make('platform')
                    ->label('Plateforme')
                    ->options(collect(Platform::cases())->mapWithKeys(fn (Platform $p) => [$p->value => $p->label()])->all()),

                SelectFilter::make('status')
                    ->label('Statut')
                    ->multiple()
                    ->options(collect(ClipStatus::cases())->mapWithKeys(fn (ClipStatus $s) => [$s->value => $s->label()])->all()),

                SelectFilter::make('compliance_status')
                    ->label('Conformité')
                    ->options(['passed' => 'Conforme', 'failed' => 'Brief non respecté', 'pending' => 'Pas encore vérifié']),

                Filter::make('posted_period')
                    ->label('Période de publication')
                    ->schema([
                        DatePicker::make('from')->label('Publié depuis le')->native(false)->displayFormat('d/m/Y'),
                        DatePicker::make('until')->label('Publié jusqu’au')->native(false)->displayFormat('d/m/Y'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('posted_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('posted_at', '<=', $date)))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['from'] ?? null) {
                            $indicators[] = 'Publié depuis le '.date('d/m/Y', strtotime($data['from']));
                        }

                        if ($data['until'] ?? null) {
                            $indicators[] = 'Publié jusqu’au '.date('d/m/Y', strtotime($data['until']));
                        }

                        return $indicators;
                    }),

                Filter::make('views_range')
                    ->label('Vues')
                    ->schema([
                        TextInput::make('min')->label('Vues minimum')->numeric()->minValue(0),
                        TextInput::make('max')->label('Vues maximum')->numeric()->minValue(0),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when(filled($data['min'] ?? null), fn (Builder $q) => $q->where('views_total', '>=', (int) $data['min']))
                        ->when(filled($data['max'] ?? null), fn (Builder $q) => $q->where('views_total', '<=', (int) $data['max'])))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if (filled($data['min'] ?? null)) {
                            $indicators[] = 'Au moins '.number_format((int) $data['min'], 0, ',', ' ').' vues';
                        }

                        if (filled($data['max'] ?? null)) {
                            $indicators[] = 'Au plus '.number_format((int) $data['max'], 0, ',', ' ').' vues';
                        }

                        return $indicators;
                    }),

                Filter::make('earned')
                    ->label('Avec des gains')
                    ->query(fn (Builder $query) => $query->where('earned_cents', '>', 0)),

                Filter::make('unpaid')
                    ->label('Vues non rémunérées')
                    ->query(fn (Builder $query) => $query->whereColumn('views_total', '>', 'paid_views')),

                Filter::make('disappeared')
                    ->label('Publication disparue')
                    ->query(fn (Builder $query) => $query->where('missing_checks', '>=', Clip::MISSING_THRESHOLD)),

                Filter::make('suspicious')
                    ->label('Vues suspectes')
                    ->query(fn (Builder $query) => app(SuspiciousViewsDetector::class)->scopeSuspicious($query)),
            ])
            ->headerActions([])
            ->recordActions([
                Action::make('open')
                    ->label('Ouvrir')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url(fn (Clip $record) => $record->url, shouldOpenInNewTab: true),
            ])
            ->emptyStateHeading('Aucun clip')
            ->emptyStateDescription('Aucun clip ne correspond à ces filtres.')
            ->recordUrl(fn (Clip $record) => null);
    }
}
