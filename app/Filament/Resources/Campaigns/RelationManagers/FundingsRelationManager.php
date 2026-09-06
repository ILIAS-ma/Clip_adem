<?php

namespace App\Filament\Resources\Campaigns\RelationManagers;

use App\Enums\ModerationAction;
use App\Models\CampaignFunding;
use App\Models\ModerationLog;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Les encaissements reçus du créateur pour financer la campagne.
 *
 * Réservé au super-administrateur : c'est ce qui débloque l'activation d'une
 * campagne, donc ce qui engage la plateforme à payer des clippeurs. Un
 * modérateur n'a pas à pouvoir déclarer qu'un virement est arrivé.
 */
class FundingsRelationManager extends RelationManager
{
    protected static string $relationship = 'fundings';

    protected static ?string $title = 'Encaissements';

    protected static ?string $modelLabel = 'encaissement';

    protected static ?string $pluralModelLabel = 'encaissements';

    public static function canViewForRecord($ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('amount_cents')
                ->label('Montant reçu')
                ->numeric()
                ->prefix('€')
                ->step(0.01)
                ->required()
                ->helperText('Négatif pour un remboursement au créateur.')
                // L'admin saisit des euros, la base stocke des centiemes
                // entiers : aucun flottant ne circule dans le domaine.
                ->formatStateUsing(fn (?int $state) => $state === null ? null : $state / 100)
                ->dehydrateStateUsing(fn ($state) => (int) round(((float) $state) * 100)),

            Select::make('method')
                ->label('Moyen')
                ->options(CampaignFunding::methods())
                ->default('transfer')
                ->required(),

            TextInput::make('reference')
                ->label('Référence')
                ->maxLength(255)
                ->placeholder('VIR-20260906-14 ou n° de facture')
                ->helperText('Ce qui permettra de retrouver la ligne sur un relevé.'),

            DateTimePicker::make('received_at')
                ->label('Reçu le')
                ->seconds(false)
                ->default(now())
                ->required(),

            Textarea::make('note')
                ->label('Note')
                ->rows(2)
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reference')
            ->defaultSort('received_at', 'desc')
            ->emptyStateHeading('Aucun encaissement')
            ->emptyStateDescription(
                'Tant que rien n’est encaissé, la campagne ne peut pas être activée : '
                .'elle promettrait à des clippeurs de l’argent que la plateforme n’a pas reçu.'
            )
            ->columns([
                TextColumn::make('received_at')
                    ->label('Reçu le')
                    ->dateTime('d/m/Y')
                    ->sortable(),

                TextColumn::make('amount_cents')
                    ->label('Montant')
                    ->alignEnd()
                    ->weight('bold')
                    ->color(fn (CampaignFunding $record) => $record->isRefund() ? 'danger' : 'success')
                    ->formatStateUsing(fn (int $state) => number_format($state / 100, 2, ',', ' ').' €')
                    ->summarize(
                        Sum::make()
                            ->label('Total encaissé')
                            ->formatStateUsing(fn ($state) => number_format(((int) $state) / 100, 2, ',', ' ').' €')
                    ),

                TextColumn::make('method')
                    ->label('Moyen')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => CampaignFunding::methods()[$state] ?? $state),

                TextColumn::make('reference')
                    ->label('Référence')
                    ->copyable()
                    ->placeholder('—'),

                TextColumn::make('recorder.name')
                    ->label('Saisi par')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Enregistrer un encaissement')
                    ->mutateDataUsing(function (array $data) {
                        // Qui a déclaré l'arrivée de l'argent : la question se
                        // pose forcément le jour d'un litige.
                        $data['recorded_by'] = auth()->id();

                        return $data;
                    })
                    ->after(function (CampaignFunding $record) {
                        ModerationLog::record(
                            ModerationAction::CampaignFunded,
                            $record->campaign,
                            auth()->user(),
                            sprintf(
                                '%s € encaissés%s',
                                number_format($record->amount_cents / 100, 2, ',', ' '),
                                $record->reference ? ' — '.$record->reference : '',
                            ),
                        );
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
