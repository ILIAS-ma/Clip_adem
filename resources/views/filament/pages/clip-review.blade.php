<x-filament-panels::page>
    <style>
        .cr-layout { display: grid; grid-template-columns: minmax(240px, 320px) 1fr; gap: 1rem; align-items: start; }
        .cr-gallery { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: .75rem; }
        @media (max-width: 800px) { .cr-layout { grid-template-columns: 1fr; } }
    </style>

    {{--
        Mise en page en styles en ligne : le back-office n'a pas de thème compilé à part,
        une classe Tailwind absente de la feuille de Filament ne s'appliquerait pas.
    --}}
    @php
        $campaign = $this->campaign();
        $clippers = $this->clippers();
        $selected = $clippers->firstWhere('user_id', $this->userId);
        $clips = $this->clips();
        $card = 'border:1px solid rgba(128,128,128,.25);background:rgba(128,128,128,.07);border-radius:.85rem;';
        $muted = 'color:var(--gray-500);';
        $toCheck = $clippers->sum(fn ($c) => $c->clips - $c->reviewed);
    @endphp

    <div style="display:grid;gap:1.1rem;">

        <div style="max-width:460px;">
            <label class="text-sm text-gray-500 dark:text-gray-400" for="review-campaign">Campagne</label>
            <x-filament::input.wrapper>
                <x-filament::input.select id="review-campaign" wire:model.live="campaignId">
                    @foreach ($this->campaigns() as $id => $title)
                        <option value="{{ $id }}">#{{ $id }} · {{ $title }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>

        <p class="text-sm text-gray-500 dark:text-gray-400" style="margin:0;">
            Les clips sont validés automatiquement pendant la campagne. Ici, à la fin, tu ouvres chaque clippeur du classement,
            tu regardes ses clips en galerie, et tu confirmes qu'ils parlent bien du bon streamer.
            @if ($clippers->isNotEmpty())
                <strong>{{ $toCheck }} clip{{ $toCheck > 1 ? 's' : '' }} à vérifier</strong> sur {{ $clippers->sum('clips') }}.
            @endif
        </p>

        @if ($clippers->isEmpty())
            <div style="{{ $card }}padding:2rem;text-align:center;{{ $muted }}">Aucun clip validé sur cette campagne.</div>
        @else
            <div class="cr-layout">

                {{-- Le classement, avec l'avancement de la vérification --}}
                <div style="{{ $card }}padding:.5rem;display:grid;gap:.25rem;">
                    @foreach ($clippers as $row)
                        @php $done = $row->clips > 0 && $row->reviewed >= $row->clips; @endphp
                        <button type="button" wire:click="open({{ $row->user_id }})" wire:key="cl-{{ $row->user_id }}"
                                style="text-align:left;cursor:pointer;border-radius:.65rem;padding:.55rem .7rem;border:1px solid {{ $row->user_id === $this->userId ? 'var(--primary-600)' : 'transparent' }};background:{{ $row->user_id === $this->userId ? 'color-mix(in oklab, var(--primary-500) 12%, transparent)' : 'transparent' }};">
                            <div style="display:flex;justify-content:space-between;gap:.5rem;align-items:center;">
                                <span style="font-weight:700;word-break:break-word;">{{ $row->rank }}. {{ $row->name }}</span>
                                <span style="font-size:.75rem;font-weight:700;color:{{ $done ? 'var(--success-600)' : 'var(--warning-600)' }};white-space:nowrap;">
                                    {{ $done ? '✓ vérifié' : $row->reviewed.'/'.$row->clips }}
                                </span>
                            </div>
                            <div style="font-size:.75rem;{{ $muted }}">
                                {{ number_format($row->views, 0, ',', ' ') }} vues · {{ $row->clips }} clip{{ $row->clips > 1 ? 's' : '' }}
                                @if ($row->removed > 0)<span style="color:var(--danger-500);"> · {{ $row->removed }} refusé{{ $row->removed > 1 ? 's' : '' }}</span>@endif
                            </div>
                        </button>
                    @endforeach
                </div>

                {{-- La galerie du clippeur choisi --}}
                <div>
                    @if (! $selected)
                        <div style="{{ $card }}padding:2rem;text-align:center;{{ $muted }}">Choisis un clippeur dans la liste pour voir ses clips.</div>
                    @else
                        <div style="display:flex;flex-wrap:wrap;gap:.6rem;align-items:center;justify-content:space-between;margin-bottom:.75rem;">
                            <div>
                                <div style="font-size:1.15rem;font-weight:800;">{{ $selected->name }}</div>
                                <div style="font-size:.8rem;{{ $muted }}">{{ number_format($selected->views, 0, ',', ' ') }} vues · {{ $selected->reviewed }}/{{ $selected->clips }} vérifiés</div>
                            </div>
                            <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
                                <x-filament::button wire:click="verifyAll" color="success" size="sm">Tout valider pour ce clippeur</x-filament::button>
                                <x-filament::button wire:click="next" color="gray" size="sm">Clippeur suivant →</x-filament::button>
                            </div>
                        </div>

                        <div style="margin-bottom:.75rem;">
                            <label class="text-sm text-gray-500 dark:text-gray-400" for="review-reason">Motif envoyé au clippeur si tu refuses un clip</label>
                            <x-filament::input.wrapper>
                                <x-filament::input id="review-reason" type="text" wire:model.blur="reason" />
                            </x-filament::input.wrapper>
                        </div>

                        <div class="cr-gallery">
                            @foreach ($clips as $clip)
                                @php
                                    $removed = $clip->status->value === 'invalidated';
                                    $ok = ! $removed && $clip->reviewed_at !== null;
                                @endphp
                                <div wire:key="clip-{{ $clip->id }}" style="{{ $card }}overflow:hidden;{{ $ok ? 'border-color:var(--success-500);' : '' }}{{ $removed ? 'opacity:.55;border-color:var(--danger-500);' : '' }}">
                                    <a href="{{ $clip->url }}" target="_blank" rel="noopener noreferrer" style="display:block;position:relative;aspect-ratio:9/16;background:rgba(128,128,128,.2);">
                                        @if ($clip->thumbnail_url)
                                            <img src="{{ $clip->thumbnail_url }}" alt="" loading="lazy" referrerpolicy="no-referrer"
                                                 onerror="this.remove()" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;">
                                        @endif
                                        <span style="position:absolute;left:.4rem;bottom:.4rem;padding:.1rem .45rem;border-radius:.4rem;background:rgba(0,0,0,.65);color:#fff;font-size:.75rem;font-weight:700;">
                                            {{ number_format($clip->views_total, 0, ',', ' ') }} vues
                                        </span>
                                        @if ($ok)
                                            <span style="position:absolute;right:.4rem;top:.4rem;padding:.1rem .45rem;border-radius:.4rem;background:var(--success-600);color:#fff;font-size:.7rem;font-weight:700;">✓ Vérifié</span>
                                        @elseif ($removed)
                                            <span style="position:absolute;right:.4rem;top:.4rem;padding:.1rem .45rem;border-radius:.4rem;background:var(--danger-600);color:#fff;font-size:.7rem;font-weight:700;">Refusé</span>
                                        @endif
                                    </a>
                                    <div style="padding:.5rem .55rem;display:grid;gap:.4rem;">
                                        <div style="font-size:.72rem;{{ $muted }}">
                                            {{ $clip->posted_at?->timezone(config('app.timezone'))->format('d/m H:i') }} · {{ \Illuminate\Support\Str::limit((string) $clip->caption, 60) ?: 'sans légende' }}
                                        </div>
                                        @unless ($removed)
                                            <div style="display:flex;gap:.35rem;">
                                                @if ($ok)
                                                    <x-filament::button wire:click="unverify({{ $clip->id }})" color="gray" size="xs">Annuler</x-filament::button>
                                                @else
                                                    <x-filament::button wire:click="verify({{ $clip->id }})" color="success" size="xs">Valider</x-filament::button>
                                                @endif
                                                <x-filament::button wire:click="reject({{ $clip->id }})"
                                                    wire:confirm="Refuser ce clip ? Ses vues sortent du classement et ses gains sont retirés."
                                                    color="danger" size="xs">Refuser</x-filament::button>
                                            </div>
                                        @endunless
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
