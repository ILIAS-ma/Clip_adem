<?php

namespace App\Filament\Pages;

use App\Models\ReferralCommission;
use App\Models\User;
use App\Services\Referrals\ReferrerLeaderboard;
use App\Services\Settings\SiteSettings;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Les conditions du parrainage, choisies par l'équipe.
 *
 * Commission du parrain, ce qui rend un filleul « actif » au classement, récompense annoncée : tout se règle
 * ici, sans toucher au serveur. Réservé au super-administrateur, parce que la commission est de l'argent que
 * la plateforme verse sur sa marge. Chaque changement est daté et journalisé.
 */
class ReferralSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Parrainage';

    protected static ?string $title = 'Parrainage';

    protected static ?string $slug = 'parrainage';

    protected static string|\UnitEnum|null $navigationGroup = 'Finances';

    protected string $view = 'filament.pages.referral-settings';

    /** @var array<string, mixed> */
    public array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public function mount(): void
    {
        $this->form->fill($this->current());
    }

    /** Les valeurs en vigueur, telles que le site les applique en ce moment. @return array<string, mixed> */
    protected function current(): array
    {
        return [
            'enabled' => (bool) config('clipping.referrals.enabled'),
            'commission_percent' => ((int) config('clipping.referrals.rate_bp')) / 100,
            'active_min_clips' => (int) config('clipping.referrals.active_min_clips'),
            'active_min_views' => (int) config('clipping.referrals.active_min_views'),
            'active_min_days' => (int) config('clipping.referrals.active_min_days'),
            'leaderboard_prize' => config('clipping.referrals.leaderboard_prize'),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Le parrainage')
                    ->schema([
                        Toggle::make('enabled')
                            ->label('Parrainage actif')
                            ->helperText('Éteint : plus de commissions versées, et l’onglet disparaît du menu des clippeurs.'),

                        TextInput::make('commission_percent')
                            ->label('Commission du parrain')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(20)
                            ->step(0.5)
                            ->suffix('%')
                            ->required()
                            ->helperText('Part des gains du filleul versée à son parrain. 0 = pas de commission. Payée par la plateforme sur sa marge, jamais sur le budget des créateurs, et le filleul ne perd rien. Ne s’applique qu’aux gains à venir.'),
                    ]),

                Section::make('Un filleul « actif » (classement des parrains)')
                    ->description('Un filleul ne compte au classement que lorsqu’il remplit les trois conditions à la fois. Plus c’est exigeant, moins un faux compte passe.')
                    ->columns(3)
                    ->schema([
                        TextInput::make('active_min_clips')
                            ->label('Clips validés au moins')
                            ->numeric()->integer()->minValue(1)->maxValue(200)->required(),

                        TextInput::make('active_min_views')
                            ->label('Vues cumulées au moins')
                            ->numeric()->integer()->minValue(0)->maxValue(10_000_000)->required(),

                        TextInput::make('active_min_days')
                            ->label('Jours d’activité différents au moins')
                            ->numeric()->integer()->minValue(1)->maxValue(60)->required()
                            ->helperText('Nombre de jours différents où il a soumis un clip.'),
                    ]),

                Section::make('Récompense annoncée')
                    ->schema([
                        Textarea::make('leaderboard_prize')
                            ->label('Ce que gagne le meilleur parrain')
                            ->rows(2)
                            ->maxLength(200)
                            ->placeholder('20 € au meilleur parrain du mois')
                            ->helperText('S’affiche en haut du classement des parrains. Vide : rien n’est annoncé. Le classement ne verse rien : vous payez le gagnant vous-mêmes.'),
                    ]),
            ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();

        app(SiteSettings::class)->save([
            'referrals.enabled' => (bool) $state['enabled'],
            'referrals.rate_bp' => (int) round(((float) $state['commission_percent']) * 100),
            'referrals.active_min_clips' => (int) $state['active_min_clips'],
            'referrals.active_min_views' => (int) $state['active_min_views'],
            'referrals.active_min_days' => (int) $state['active_min_days'],
            'referrals.leaderboard_prize' => $state['leaderboard_prize'],
        ], auth()->user());

        Notification::make()->success()->title('Conditions du parrainage enregistrées')->send();
    }

    /** Remet toutes les conditions du parrainage à leur valeur d'origine. */
    public function resetToDefaults(): void
    {
        app(SiteSettings::class)->reset(array_keys(array_filter(
            SiteSettings::KEYS,
            fn (array $_, string $key) => str_starts_with($key, 'referrals.'),
            ARRAY_FILTER_USE_BOTH,
        )), auth()->user());

        $this->form->fill($this->current());

        Notification::make()->success()->title('Conditions remises par défaut')->send();
    }

    /**
     * Où en est le parrainage : de quoi régler les conditions en connaissance de cause.
     *
     * @return array<string, int|string>
     */
    public function figures(): array
    {
        $rule = ReferrerLeaderboard::rule();
        $referrals = User::whereNotNull('referred_by')->count();

        return [
            'referrals' => $referrals,
            'commissions' => number_format(ReferralCommission::sum('amount_cents') / 100, 2, ',', ' ').' €',
            'clippers' => User::where('role', 'clipper')->count(),
            'rule' => $rule['clips'].' clips · '.number_format($rule['views'], 0, ',', ' ').' vues · '.$rule['days'].' jours',
        ];
    }
}
