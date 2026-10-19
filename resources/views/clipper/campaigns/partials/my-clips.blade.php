@php use App\Support\Money; @endphp

{{-- Ses clips en vignettes, comme dans l'application : on reconnaît une vidéo à son image, pas à son identifiant. --}}
@if ($clips->isEmpty())
    <div class="card p-8 text-center">
        <p class="font-display text-lg font-bold text-ink-50">Aucun clip pour l'instant</p>
        <p class="mt-1 text-sm text-ink-400">Publiez sur TikTok, puis choisissez vos vidéos : elles comptent tout de suite.</p>
        @if ($canPick)
            <button type="button" @click="$dispatch('open-picker')" class="btn-brand mt-5">Choisir mes vidéos</button>
        @endif
    </div>
@else
    <div class="flex items-baseline justify-between px-1 pb-3">
        <p class="text-sm text-ink-400">{{ $clips->count() }} clip{{ $clips->count() > 1 ? 's' : '' }}</p>
        <p class="text-sm font-semibold tabular text-ink-100">
            {{ Money::views($clips->sum('views_total')) }} vues
            @unless ($campaign->isContest())
                · {{ Money::euros($clips->sum('earned_cents')) }}
            @endunless
        </p>
    </div>

    <ul class="grid grid-cols-3 gap-1.5 sm:grid-cols-4">
        @foreach ($clips as $clip)
            <li>
                <a href="{{ route('clips.show', $clip) }}" wire:navigate
                   class="relative block aspect-[9/16] overflow-hidden rounded-xl bg-ink-900 ring-1 ring-ink-800 transition hover:ring-brand-500">
                    @if ($clip->thumbnail_url)
                        <img src="{{ $clip->thumbnail_url }}" alt="" loading="lazy" referrerpolicy="no-referrer"
                             onerror="this.remove()" class="absolute inset-0 h-full w-full object-cover">
                    @endif
                    <span aria-hidden="true" class="absolute inset-0 bg-gradient-to-t from-ink-950/90 via-transparent to-transparent"></span>

                    @unless ($clip->status->isPayable())
                        <span class="absolute left-1.5 top-1.5 chip-danger text-[10px]">{{ $clip->status->label() }}</span>
                    @endunless

                    <span class="absolute inset-x-1.5 bottom-1.5">
                        <span class="block text-sm font-bold tabular text-white">{{ Money::compactViews($clip->views_total) }} vues</span>
                        @unless ($campaign->isContest())
                            <span class="block text-[11px] font-semibold tabular text-brand-300">{{ Money::euros($clip->earned_cents) }}</span>
                        @endunless
                    </span>
                    <span class="sr-only">{{ $clip->platform->label() }} · {{ $clip->external_post_id }}</span>
                </a>
            </li>
        @endforeach
    </ul>
@endif
