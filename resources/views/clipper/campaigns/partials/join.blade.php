<h2 class="font-display text-lg font-bold text-ink-50">Participer</h2>

@if ($isOpen)
    <p class="mt-1 text-sm text-ink-300">Choisissez le compte avec lequel vous publierez, et c'est parti.</p>
    <div class="mt-4">
        @livewire('join-campaign', ['campaign' => $campaign], key('join-'.$campaign->id.'-'.$placement))
    </div>
@else
    <p class="mt-2 rounded-xl bg-ink-900 p-3 text-sm text-ink-300">
        {{ ! $campaign->isContest() && $remainingCents <= 0
            ? "Le budget de cette campagne est épuisé : elle n'accepte plus de participants."
            : "Cette campagne n'accepte plus de nouveaux participants." }}
    </p>
@endif
