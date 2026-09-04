<?php

namespace App\Livewire;

use App\Contracts\CampaignBudgetService;
use App\Enums\CampaignStatus;
use App\Enums\Platform;
use App\Models\Campaign;
use App\Models\Creator;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Catalogue des campagnes ouvertes, avec ses filtres.
 *
 * Lecture stricte : ce composant n'écrit rien et ne calcule aucun budget
 * lui-même. Le reliquat vient de CampaignBudgetService, pour que la valeur
 * affichée soit exactement celle du back-office.
 */
class CampaignCatalogue extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'plateforme', except: '')]
    public string $platform = '';

    #[Url(as: 'créateur', except: '')]
    public string $creator = '';

    /** Cachet minimum, en euros pour 1000 vues. */
    #[Url(as: 'cachet', except: '')]
    public string $minRate = '';

    #[Url(as: 'ouvertes', except: true)]
    public bool $onlyOpen = true;

    public function updated(): void
    {
        // Filtrer sans revenir page 1 laisserait l'utilisateur sur une page
        // vide dès que le nombre de résultats diminue.
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'platform', 'creator', 'minRate']);
        $this->onlyOpen = true;
        $this->resetPage();
    }

    public function render()
    {
        $budget = app(CampaignBudgetService::class);

        $campaigns = Campaign::query()
            ->visibleToClippers()
            ->with(['creator', 'rates'])
            ->when($this->onlyOpen, fn ($q) => $q->where('status', CampaignStatus::Active))
            ->when($this->search, fn ($q) => $q->where(function ($query) {
                $query->where('title', 'like', "%{$this->search}%")
                    ->orWhereHas('creator', fn ($a) => $a->where('name', 'like', "%{$this->search}%"));
            }))
            ->when($this->creator, fn ($q) => $q->where('creator_id', $this->creator))
            ->when($this->platform, fn ($q) => $q->whereHas(
                'rates',
                fn ($r) => $r->where('platform', $this->platform)->where('is_enabled', true),
            ))
            ->when($this->minRate !== '', fn ($q) => $q->whereHas(
                'rates',
                fn ($r) => $r->where('is_enabled', true)
                    ->where('rate_per_1k_cents', '>=', (int) round((float) $this->minRate * 100)),
            ))
            // Les campagnes actives d'abord : une campagne épuisée reste
            // consultable mais n'a plus rien à proposer.
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [CampaignStatus::Active->value])
            ->orderByDesc('created_at')
            ->paginate(12);

        return view('livewire.campaign-catalogue', [
            'campaigns' => $campaigns,
            'remaining' => $campaigns->mapWithKeys(fn (Campaign $campaign) => [
                $campaign->getKey() => $budget->remaining($campaign),
            ]),
            'creators' => Creator::whereHas('campaigns', fn ($q) => $q->visibleToClippers())
                ->orderBy('name')
                ->pluck('name', 'id'),
            'platforms' => collect(Platform::cases())
                ->mapWithKeys(fn (Platform $p) => [$p->value => $p->label()]),
        ]);
    }
}
