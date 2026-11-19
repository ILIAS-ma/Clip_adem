<?php

namespace App\Filament\Pages;

use App\Services\Referrals\ReferralBonusService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * Les primes de parrainage du mois : qui les a gagnées, et le bouton qui les verse.
 *
 * Le 25, l'équipe ouvre cette page, regarde les filleuls qui ont posté assez de clips dans le mois,
 * puis verse les primes sur les soldes des parrains. Une prime ne se verse qu'une fois par filleul.
 */
class ReferralBonuses extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    protected static ?string $navigationLabel = 'Primes de parrainage';

    protected static ?string $title = 'Primes de parrainage';

    protected static ?string $slug = 'primes-parrainage';

    protected static string|\UnitEnum|null $navigationGroup = 'Catalogue';

    protected string $view = 'filament.pages.referral-bonuses';

    // Le mois affiché, « 2026-11 ». Par défaut le mois en cours : le 25, on paie ceux qui ont déjà atteint le seuil.
    #[Url]
    public ?string $month = null;

    public static function canAccess(): bool
    {
        return (config('clipping.referrals.enabled') && auth()->user()?->isSuperAdmin()) ?? false;
    }

    public function mount(): void
    {
        $this->month ??= now()->format('Y-m');
    }

    protected function monthDate(): Carbon
    {
        try {
            return Carbon::createFromFormat('Y-m-d', ($this->month ?: now()->format('Y-m')).'-01')->startOfMonth();
        } catch (\Throwable) {
            return now()->startOfMonth();
        }
    }

    /** @return array<string, string> les six derniers mois, du plus récent au plus ancien */
    public function months(): array
    {
        return collect(range(0, 5))->mapWithKeys(function (int $i) {
            $date = now()->startOfMonth()->subMonths($i);

            return [$date->format('Y-m') => ucfirst($date->translatedFormat('F Y'))];
        })->all();
    }

    public function amountCents(): int
    {
        return app(ReferralBonusService::class)->amountCents();
    }

    public function minClips(): int
    {
        return app(ReferralBonusService::class)->minClips();
    }

    /** @return Collection<int, object> */
    public function toPay(): Collection
    {
        return app(ReferralBonusService::class)->qualifying($this->monthDate());
    }

    public function paid(): Collection
    {
        return app(ReferralBonusService::class)->paidFor($this->monthDate());
    }

    public function pay(): void
    {
        $count = app(ReferralBonusService::class)->pay($this->monthDate());

        $count > 0
            ? Notification::make()->success()->title($count.' prime'.($count > 1 ? 's' : '').' versée'.($count > 1 ? 's' : ''))
                ->body('Les montants sont sur les soldes des parrains.')->send()
            : Notification::make()->warning()->title('Aucune prime à verser pour ce mois.')->send();
    }
}
