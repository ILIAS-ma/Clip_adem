@php
    use App\Enums\ClipStatus;
    use App\Support\Money;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="font-display text-2xl font-bold text-ink-50">Mes clips</h1>
                <p class="mt-1 text-sm text-ink-300 sm:text-base">
                    {{ $clips->count() }} clip{{ $clips->count() > 1 ? 's' : '' }} ·
                    {{ Money::euros($clips->sum('earned_cents')) }} gagnés
                </p>
            </div>
            <a href="{{ route('campaigns.index') }}" wire:navigate class="btn-ghost max-sm:hidden">Rejoindre une campagne</a>
        </div>
    </x-slot>

    <div class="mx-auto max-w-5xl px-4 py-4 sm:px-6 sm:py-8 lg:px-8">

        {{-- Filtres en GET plutôt qu'en Livewire : l'URL reste partageable et
             le bouton retour du navigateur fonctionne, pour un simple filtre
             qui n'a pas besoin de réactivité. --}}
        <form method="GET" class="card mb-4 grid grid-cols-2 items-end gap-2 p-3 sm:mb-6 sm:flex sm:flex-wrap sm:gap-4 sm:p-4">
            <div class="col-span-2 min-w-0 sm:col-span-1">
                <label for="campagne" class="label">Campagne</label>
                <x-select id="campagne" name="campagne" class="w-full text-sm sm:w-auto" onchange="this.form.submit()">
                    <option value="">Toutes</option>
                    @foreach ($campaignOptions as $campaign)
                        <option value="{{ $campaign->id }}" @selected(($filters['campagne'] ?? '') == $campaign->id)>
                            {{ $campaign->title }}
                        </option>
                    @endforeach
                </x-select>
            </div>

            <div class="min-w-0">
                <label for="plateforme" class="label">Plateforme</label>
                <x-select id="plateforme" name="plateforme" class="w-full text-sm sm:w-auto" onchange="this.form.submit()">
                    <option value="">Toutes</option>
                    @foreach ($platformOptions as $platform)
                        <option value="{{ $platform->value }}" @selected(($filters['plateforme'] ?? '') === $platform->value)>
                            {{ $platform->label() }}
                        </option>
                    @endforeach
                </x-select>
            </div>

            <div class="min-w-0">
                <label for="statut" class="label">Statut</label>
                <x-select id="statut" name="statut" class="w-full text-sm sm:w-auto" onchange="this.form.submit()">
                    <option value="">Tous</option>
                    @foreach (ClipStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected(($filters['statut'] ?? '') === $status->value)>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </x-select>
            </div>

            @if (array_filter($filters))
                <a href="{{ route('clips.index') }}" wire:navigate class="col-span-2 text-sm font-medium text-ink-300 underline-offset-2 hover:text-ink-50 hover:underline">
                    Réinitialiser
                </a>
            @endif
        </form>

        @if ($clips->isEmpty() && array_filter($filters))
            <div class="card px-4 py-12 sm:px-6 sm:py-16 text-center">
                <p class="font-display text-lg font-bold text-ink-50">Aucun clip pour ces filtres</p>
                <a href="{{ route('clips.index') }}" wire:navigate class="btn-ghost mt-6">Réinitialiser les filtres</a>
            </div>
        @elseif ($clips->isEmpty())
            <div class="card px-4 py-12 sm:px-6 sm:py-16 text-center">
                <p class="font-display text-lg font-bold text-ink-50">Aucun clip soumis</p>
                <p class="mx-auto mt-2 max-w-sm text-sm leading-relaxed text-ink-300">
                    Rejoignez une campagne, publiez votre clip sur TikTok, puis choisissez-le
                    depuis la page de la campagne.
                </p>
                <a href="{{ route('campaigns.index') }}" wire:navigate class="btn-brand mt-6">Voir les campagnes</a>
            </div>
        @else
            <div class="space-y-3">
                @foreach ($clips as $clip)
                    <a href="{{ route('clips.show', $clip) }}" wire:navigate
                       class="card flex flex-wrap items-center gap-x-3 gap-y-2 p-3 transition hover:-translate-y-0.5 hover:shadow-lifted sm:flex-nowrap sm:gap-4 sm:p-5">
                        <div class="h-16 w-12 flex-none overflow-hidden rounded-lg bg-ink-700">
                            @if ($clip->thumbnail_url)
                                <img src="{{ $clip->thumbnail_url }}" alt="" loading="lazy" referrerpolicy="no-referrer" class="h-full w-full object-cover">
                            @else
                                <div class="flex h-full w-full items-center justify-center text-[10px] font-semibold uppercase text-ink-400">
                                    {{ $clip->platform->label() }}
                                </div>
                            @endif
                        </div>

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-xs font-semibold uppercase tracking-wide text-ink-400">
                                {{ $clip->campaign?->creator?->name }}
                            </p>
                            <p class="mt-0.5 line-clamp-2 font-display text-base font-bold leading-snug text-ink-50 sm:truncate">
                                {{ $clip->campaign?->title }}
                            </p>

                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <span class="chip-neutral">{{ $clip->platform->label() }}</span>

                                <span @class([
                                    'chip-ok' => $clip->status->value === 'approved',
                                    'chip-wait' => $clip->status->value === 'pending_review',
                                    'chip-danger' => $clip->status->value === 'rejected',
                                    'chip-danger' => $clip->status->value === 'invalidated',
                                ])>{{ $clip->status->label() }}</span>

                                {{-- Un clip refusé a déjà son badge « Refusé » : un second badge rouge brouillerait le message. --}}
                                @if ($clip->status->value === 'rejected')
                                    <span class="chip-neutral">Ne compte pas</span>
                                @elseif ($clip->compliance_status === 'failed')
                                    @php
                                        $failedLabels = collect($clip->compliance['checks'] ?? [])->where('passed', false)->pluck('label')->implode(' ');
                                    @endphp
                                    <span class="chip-wait">{{ str_contains($failedLabels, 'Hashtags') ? 'Hashtag manquant · vidéo comptée' : 'Brief à vérifier · vidéo comptée' }}</span>
                                @elseif ($clip->compliance_status === 'passed')
                                    <span class="chip-ok">Conforme</span>
                                @endif
                            </div>
                        </div>

                        <div class="flex w-full shrink-0 items-baseline justify-between gap-3 border-t border-ink-700 pt-2 text-right sm:block sm:w-auto sm:border-0 sm:pt-0">
                            {{-- Sur un classement, rien n'est payé aux vues : le chiffre qui compte, ce sont les vues. --}}
                            @if ($clip->campaign?->isContest())
                                <p class="font-display text-lg font-bold tabular text-ink-50 sm:text-xl">{{ Money::compactViews($clip->views_total) }}<span class="text-sm font-semibold sm:hidden"> vues</span></p>
                            @else
                                <p class="font-display text-lg font-bold tabular text-ink-50 sm:text-xl">
                                    {{ Money::euros($clip->earned_cents) }}
                                </p>
                            @endif

                            {{-- Les deux lignes de détail restent groupées à droite du montant sur
                                 téléphone ; `sm:contents` efface le groupe plus haut, où elles
                                 reprennent leur empilement sous le chiffre. --}}
                            <div class="text-right sm:contents">
                                @if ($clip->campaign?->isContest())
                                    <p class="hidden text-xs text-ink-400 sm:block">vues</p>
                                @else
                                    <p class="text-xs tabular text-ink-400">{{ Money::views($clip->views_total) }} vues</p>
                                @endif

                                @if (($pending[$clip->id] ?? 0) > 0)
                                    {{-- Estimation issue du moteur : plafonds et reliquat déjà appliqués. --}}
                                    <p class="mt-1 text-xs font-semibold tabular text-brand-400">
                                        + {{ Money::euros($pending[$clip->id]) }} en attente
                                    </p>
                                @elseif ($clip->unpaidViews() > 0)
                                    <p class="mt-1 text-xs tabular text-brand-300">
                                        {{ Money::views($clip->unpaidViews()) }} vues non payées
                                    </p>
                                @endif
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
