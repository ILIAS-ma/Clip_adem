{{--
    Une carte de clip.

    Extraite parce qu'elle sert deux fois — résultats et sélection — et que
    deux copies du même gabarit divergent à la première correction. Les trois
    informations qui décident sont posées sur l'image, là où l'œil est déjà :
    la langue, les vues, la durée.
--}}
@php $lecture = $lecturePossible ?? false; @endphp

<article @class(['card', 'selected' => $garde ?? false])>
    @if ($lecture)
        <button type="button" class="thumb" wire:click="play('{{ $clip['id'] }}')"
                aria-label="Lire « {{ $clip['title'] }} »">
            @include('livewire.partials.clip-thumb-inner')
        </button>
    @else
        <a href="{{ $clip['url'] }}" target="_blank" rel="noopener" class="thumb"
           aria-label="Ouvrir « {{ $clip['title'] }} » sur Twitch">
            @include('livewire.partials.clip-thumb-inner')
        </a>
    @endif

    <div class="body">
        <h3 class="title">{{ $clip['title'] }}</h3>

        <p class="card-meta">
            <button type="button" wire:click="pick('{{ $clip['login'] }}')" class="who"
                    style="border: 0; background: none; padding: 0; cursor: pointer; font: inherit; font-weight: 600"
                    title="Voir tous les clips de cette chaîne">
                {{ $clip['broadcaster'] }}
            </button>

            @if ($clip['game'])
                <span>{{ $clip['game'] }}</span>
            @endif

            @if ($clip['creator'])
                <span>clip de {{ $clip['creator'] }}</span>
            @endif
        </p>

        <div class="card-actions">
            @if ($garde ?? false)
                <span class="btn ghost" aria-disabled="true">De côté</span>
            @else
                <button type="button" wire:click="keep('{{ $clip['id'] }}')" class="btn ghost">
                    Mettre de côté
                </button>
            @endif

            <a href="{{ $clip['url'] }}" target="_blank" rel="noopener"
               class="btn ghost" style="flex: 0 0 auto; padding: 6px 9px"
               aria-label="Ouvrir sur Twitch">
                <svg class="ico" viewBox="0 0 16 16" aria-hidden="true">
                    <path d="M6.5 3.5H3.5v9h9v-3M10 2.5h3.5V6M13 3l-5.5 5.5" />
                </svg>
            </a>
        </div>
    </div>
</article>
