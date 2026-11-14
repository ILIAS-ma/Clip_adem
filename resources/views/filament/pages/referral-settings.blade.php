<x-filament-panels::page>
    @php($figures = $this->figures())

    {{-- Où en est le parrainage : de quoi régler les conditions en connaissance de cause. --}}
    <div class="ad-tiles">
        <div class="ad-tile">
            <div class="n">{{ $figures['referrals'] }}</div>
            <div class="l">filleuls inscrits</div>
        </div>
        <div class="ad-tile">
            <div class="n">{{ $figures['commissions'] }}</div>
            <div class="l">commissions versées</div>
        </div>
        <div class="ad-tile">
            <div class="n">{{ $figures['clippers'] }}</div>
            <div class="l">clippeurs au total</div>
        </div>
        <div class="ad-tile">
            <div class="l" style="margin:0;">Un filleul actif, aujourd'hui</div>
            <div class="n" style="font-size:1rem;margin-top:.35rem;">{{ $figures['rule'] }}</div>
        </div>
    </div>

    <form wire:submit.prevent="save" class="space-y-6">
        {{ $this->form }}

        <div class="flex flex-wrap items-center gap-3">
            <x-filament::button type="submit" icon="heroicon-o-check">Enregistrer les conditions</x-filament::button>

            <x-filament::button type="button" color="gray" wire:click="resetToDefaults"
                                wire:confirm="Remettre toutes les conditions du parrainage à leur valeur d'origine ?">
                Remettre par défaut
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
