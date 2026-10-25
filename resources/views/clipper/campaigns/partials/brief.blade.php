@php use App\Support\Money; @endphp

<div class="card p-5 sm:p-6">
    @if ($campaign->required_hashtags)
        {{-- Les hashtags d'abord, et d'un toucher dans le presse-papiers : c'est ce qu'on recopie en publiant. --}}
        <div x-data="{ copied: false }" class="mb-5">
            <div class="flex items-center justify-between gap-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-ink-400">Hashtags obligatoires</p>
                <button type="button" class="text-xs font-semibold text-brand-400"
                        @click="navigator.clipboard?.writeText(@js(implode(' ', $campaign->required_hashtags))); copied = true; setTimeout(() => copied = false, 1500)"
                        x-text="copied ? 'Copiés ✓' : 'Copier'">Copier</button>
            </div>
            <div class="mt-2 flex flex-wrap gap-1.5">
                @foreach ($campaign->required_hashtags as $hashtag)
                    <span class="chip bg-brand-500/15 text-brand-300">{{ $hashtag }}</span>
                @endforeach
            </div>
        </div>
    @endif

    <h2 class="font-display text-lg font-bold text-ink-50">Le brief</h2>
    <p class="mt-3 whitespace-pre-line leading-relaxed text-ink-200">{{ $campaign->brief }}</p>
</div>

{{-- Un streamer ne joint pas d'extrait : il produit des heures de direct. Sans ce lien, le brief parle de moments introuvables. --}}
@if ($campaign->creator?->isStreamer() && $campaign->creator->vod_url)
    <div class="card p-5 sm:p-6">
        <h2 class="font-display text-lg font-bold text-ink-50">Où trouver la matière</h2>
        <p class="mt-1 text-sm text-ink-400">Les rediffusions de {{ $campaign->creator->name }}.</p>

        <div class="mt-4 flex flex-wrap items-center gap-2">
            <a href="{{ $campaign->creator->vod_url }}" target="_blank" rel="noopener" class="btn-ghost">Voir les rediffusions ↗</a>
            @if ($campaign->creator->twitch_handle)
                <a href="https://twitch.tv/{{ $campaign->creator->twitch_handle }}" target="_blank" rel="noopener" class="btn-ghost">Twitch ↗</a>
            @endif
            @if ($campaign->creator->kick_handle)
                <a href="https://kick.com/{{ $campaign->creator->kick_handle }}" target="_blank" rel="noopener" class="btn-ghost">Kick ↗</a>
            @endif
        </div>
    </div>
@endif

@if ($campaign->assets->isNotEmpty())
    <div class="card p-5 sm:p-6">
        <h2 class="font-display text-lg font-bold text-ink-50">Matière première</h2>
        <p class="mt-1 text-sm text-ink-400">Le son, les rushes et les visuels fournis par le créateur.</p>

        <div class="mt-4 space-y-4">
            @foreach ($campaign->assets as $asset)
                <x-campaign-asset :asset="$asset" />
            @endforeach
        </div>
    </div>
@endif

{{-- Les règles chiffrées, en une liste courte. --}}
<div class="card p-5 sm:p-6">
    <h2 class="font-display text-base font-bold text-ink-50">Infos</h2>

    @unless ($campaign->isContest())
        <div class="mt-4">
            <x-budget-bar :campaign="$campaign" :remaining="$remainingCents" />
        </div>
    @endunless

    <dl class="mt-4 space-y-2.5 text-sm">
        @foreach ($platforms as $rate)
            <div class="flex items-baseline justify-between gap-2">
                <dt class="text-ink-400">{{ $rate->platform->label() }}</dt>
                <dd class="font-semibold tabular text-ink-50">
                    {{ $campaign->isContest() ? 'acceptée' : Money::rate($rate->rate_per_1k_cents).' / 1000' }}
                </dd>
            </div>
        @endforeach

        @if ($campaign->min_views_per_clip > 0)
            <div class="flex justify-between gap-2">
                <dt class="text-ink-400">Vues minimum</dt>
                <dd class="tabular text-ink-100">{{ Money::views($campaign->min_views_per_clip) }}</dd>
            </div>
        @endif

        @if ($minClips > 0)
            <div class="flex justify-between gap-2">
                <dt class="text-ink-400">Clips minimum</dt>
                <dd class="tabular {{ $valides >= $minClips ? 'text-ink-100' : 'text-amber-300' }}">{{ $valides }} / {{ $minClips }}</dd>
            </div>
        @endif

        @if ($campaign->max_payout_per_clip_cents)
            <div class="flex justify-between gap-2">
                <dt class="text-ink-400">Plafond par clip</dt>
                <dd class="tabular text-ink-100">{{ Money::euros($campaign->max_payout_per_clip_cents) }}</dd>
            </div>
        @endif

        @if (($slotsLeft = $campaign->launchBonusSlotsLeft()) !== null)
            <div class="flex justify-between gap-2">
                <dt class="text-ink-400">Prime de lancement</dt>
                <dd class="text-right">
                    <span class="tabular font-semibold text-brand-400">{{ Money::euros($campaign->launch_bonus_cents) }}</span>
                    <span class="block text-xs text-ink-400">
                        {{ $slotsLeft > 0
                            ? $slotsLeft.' place'.($slotsLeft > 1 ? 's' : '').' restante'.($slotsLeft > 1 ? 's' : '')
                            : 'Toutes les places sont prises' }}
                    </span>
                </dd>
            </div>
        @endif

        @if ($campaign->starts_at)
            <div class="flex justify-between gap-2 border-t border-ink-700 pt-2.5">
                <dt class="text-ink-400">Début</dt>
                <dd class="text-ink-100">{{ $campaign->starts_at->format('d/m/Y à H\hi') }}</dd>
            </div>
        @endif

        @if ($campaign->enforcesPostingWindow())
            {{-- La règle, dite avant qu'on ne publie : seules les vidéos faites pendant la campagne comptent. --}}
            <div class="rounded-xl bg-amber-500/10 p-3 text-xs leading-relaxed text-amber-200 ring-1 ring-amber-500/30">
                Seules les vidéos <strong>publiées à partir du {{ $campaign->starts_at->format('d/m à H\hi') }}</strong>
                @if ($campaign->ends_at) et jusqu'au {{ $campaign->ends_at->format('d/m à H\hi') }} @endif
                comptent. Une vidéo plus ancienne est refusée.
            </div>
        @endif

        @if ($campaign->ends_at)
            <div class="flex justify-between gap-2">
                <dt class="text-ink-400">Fin</dt>
                <dd class="text-ink-100">{{ $campaign->ends_at->format('d/m/Y à H\hi') }}</dd>
            </div>
        @endif
    </dl>

    @if ($joined && $isOpen)
        {{-- Engager un compte de plus, s'il en reste un. --}}
        <details class="mt-4 border-t border-ink-700 pt-3 text-sm">
            <summary class="cursor-pointer text-ink-400 hover:text-ink-100">Ajouter un autre compte</summary>
            <div class="mt-3">@livewire('join-campaign', ['campaign' => $campaign], key('join-extra-'.$campaign->id))</div>
        </details>
    @endif
</div>
