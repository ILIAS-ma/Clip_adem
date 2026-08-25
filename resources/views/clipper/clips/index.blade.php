@php use App\Support\Money; @endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Mes clips</h2>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">

            @if ($clips->isEmpty())
                <div class="rounded-lg border border-dashed border-gray-300 bg-white p-12 text-center">
                    <h3 class="text-base font-semibold text-gray-900">Aucun clip soumis</h3>
                    <p class="mx-auto mt-2 max-w-md text-sm text-gray-500">
                        Rejoignez une campagne, publiez votre clip sur votre compte, puis collez le lien
                        de la publication.
                    </p>
                    <a href="{{ route('campaigns.index') }}" wire:navigate
                       class="mt-4 inline-flex rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                        Voir les campagnes
                    </a>
                </div>
            @else
                <div class="space-y-3">
                    @foreach ($clips as $clip)
                        <a href="{{ route('clips.show', $clip) }}"
                           class="flex flex-wrap items-center justify-between gap-4 rounded-lg bg-white p-5 shadow transition hover:shadow-md">
                            <div class="min-w-0">
                                <p class="font-semibold text-gray-900">{{ $clip->campaign?->title }}</p>
                                <p class="text-sm text-gray-500">
                                    {{ $clip->campaign?->artist?->name }} ·
                                    {{ $clip->platform->label() }}
                                    @if ($clip->socialAccount?->handle)
                                        · &#64;{{ $clip->socialAccount->handle }}
                                    @endif
                                </p>

                                <div class="mt-2 flex flex-wrap gap-2">
                                    <span @class([
                                        'rounded-full px-2 py-0.5 text-xs font-medium',
                                        'bg-emerald-100 text-emerald-800' => $clip->status->value === 'approved',
                                        'bg-amber-100 text-amber-800' => $clip->status->value === 'pending_review',
                                        'bg-gray-100 text-gray-700' => $clip->status->value === 'rejected',
                                        'bg-red-100 text-red-800' => $clip->status->value === 'invalidated',
                                    ])>{{ $clip->status->label() }}</span>

                                    @if ($clip->compliance_status === 'failed')
                                        <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800">
                                            Brief non respecté
                                        </span>
                                    @elseif ($clip->compliance_status === 'passed')
                                        <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">
                                            Conforme
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <div class="text-right">
                                <p class="text-lg font-semibold tabular-nums text-gray-900">
                                    {{ Money::euros($clip->earned_cents) }}
                                </p>
                                <p class="text-xs tabular-nums text-gray-500">
                                    {{ Money::views($clip->views_total) }} vues
                                </p>
                                @if (($pending[$clip->id] ?? 0) > 0)
                                    {{-- Estimation issue du service : plafonds et reliquat déjà appliqués. --}}
                                    <p class="mt-0.5 text-xs font-medium text-emerald-600">
                                        + {{ Money::euros($pending[$clip->id]) }} en attente
                                    </p>
                                @elseif ($clip->unpaidViews() > 0)
                                    <p class="mt-0.5 text-xs text-amber-600">
                                        {{ Money::views($clip->unpaidViews()) }} vues non rémunérées
                                    </p>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
