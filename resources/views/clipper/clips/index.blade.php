@php use App\Support\Money; @endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="font-display text-2xl font-bold text-ink-50">Mes clips</h1>
                <p class="mt-1 text-ink-300">
                    {{ $clips->count() }} clip{{ $clips->count() > 1 ? 's' : '' }} ·
                    {{ Money::euros($clips->sum('earned_cents')) }} gagnés
                </p>
            </div>
            <a href="{{ route('campaigns.index') }}" class="btn-ghost">Rejoindre une campagne</a>
        </div>
    </x-slot>

    <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">

        @if ($clips->isEmpty())
            <div class="card px-6 py-16 text-center">
                <p class="font-display text-lg font-bold text-ink-50">Aucun clip soumis</p>
                <p class="mx-auto mt-2 max-w-sm text-sm leading-relaxed text-ink-300">
                    Rejoignez une campagne, publiez votre clip sur votre compte, puis collez le lien
                    de la publication.
                </p>
                <a href="{{ route('campaigns.index') }}" class="btn-brand mt-6">Voir les campagnes</a>
            </div>
        @else
            <div class="space-y-3">
                @foreach ($clips as $clip)
                    <a href="{{ route('clips.show', $clip) }}"
                       class="flex flex-wrap items-center justify-between gap-4 card p-5 transition hover:-translate-y-0.5 hover:shadow-lifted">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-wide text-ink-400">
                                {{ $clip->campaign?->artist?->name }}
                            </p>
                            <p class="mt-0.5 font-display text-base font-bold text-ink-50">
                                {{ $clip->campaign?->title }}
                            </p>

                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <span class="chip-neutral">{{ $clip->platform->label() }}</span>

                                <span @class([
                                    'chip-ok' => $clip->status->value === 'approved',
                                    'chip-wait' => $clip->status->value === 'pending_review',
                                    'chip-neutral' => $clip->status->value === 'rejected',
                                    'chip-danger' => $clip->status->value === 'invalidated',
                                ])>{{ $clip->status->label() }}</span>

                                @if ($clip->compliance_status === 'failed')
                                    <span class="chip-danger">Brief non respecté</span>
                                @elseif ($clip->compliance_status === 'passed')
                                    <span class="chip-ok">Conforme</span>
                                @endif
                            </div>
                        </div>

                        <div class="text-right">
                            <p class="font-display text-xl font-bold tabular text-ink-50">
                                {{ Money::euros($clip->earned_cents) }}
                            </p>
                            <p class="text-xs tabular text-ink-400">{{ Money::views($clip->views_total) }} vues</p>

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
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
