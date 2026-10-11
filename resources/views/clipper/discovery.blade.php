@php use App\Support\Money; @endphp

<x-studio-layout title="Découverte">

    {{--
        Le rail ne porte que ce qui ne bouge pas pendant la session.

        Les slots du gabarit vivent hors du composant Livewire : ils ne se
        rafraîchissent pas quand on met un clip de côté. Y afficher la
        sélection donnerait un compteur juste au chargement puis faux ensuite,
        ce qui est pire que de ne pas l'afficher. Elle reste donc dans la
        colonne principale, où Livewire la tient à jour.
    --}}
    <x-slot name="rail">
        <section class="side-block">
            <div class="side-head">
                <span class="side-title"><span class="live-pulse"></span> Chaînes qui paient</span>
            </div>

            <div class="side-list">
                @forelse ($chainesPayees as $rang => $ligne)
                    <a href="{{ route('campaigns.show', $ligne['campagne']) }}" class="side-item">
                        <span class="rank">{{ $rang + 1 }}</span>
                        <span class="who">
                            <span class="cname">{{ $ligne['login'] }}</span>
                            <span class="cmeta">{{ $ligne['campagne']->title }}</span>
                        </span>
                        <span class="count">{{ $ligne['cachet'] ? Money::euros($ligne['cachet']) : '—' }}</span>
                    </a>
                @empty
                    <span class="side-item empty-row">Aucune campagne de streamer ouverte.</span>
                @endforelse
            </div>

            {{-- Le cachet, pas le budget : c'est ce qu'on compare d'une chaîne
                 à l'autre pour décider où aller. --}}
            <p class="side-note">Classées sur le cachet aux 1000 vues.</p>
        </section>

        <details class="panel">
            <summary>
                Comment ça marche
                <span class="resume">3 étapes</span>
            </summary>

            <div class="panel-body">
                <ol class="side-list" style="gap: 8px">
                    <li class="side-item" style="cursor: default; align-items: flex-start">
                        <span class="rank">1</span>
                        <span class="who"><span class="cmeta" style="white-space: normal; color: var(--muted)">
                            Cherchez les moments forts d'une chaîne qui a une campagne ouverte.
                        </span></span>
                    </li>
                    <li class="side-item" style="cursor: default; align-items: flex-start">
                        <span class="rank">2</span>
                        <span class="who"><span class="cmeta" style="white-space: normal; color: var(--muted)">
                            Mettez-les de côté, puis montez-les en vertical sur votre machine.
                        </span></span>
                    </li>
                    <li class="side-item" style="cursor: default; align-items: flex-start">
                        <span class="rank">3</span>
                        <span class="who"><span class="cmeta" style="white-space: normal; color: var(--muted)">
                            Publiez, puis collez l'adresse de la publication dans la campagne.
                        </span></span>
                    </li>
                </ol>
            </div>
        </details>
    </x-slot>

    <x-slot name="status">
        <span class="pill {{ $simule ? 'bad' : 'ok' }}">
            {{ $simule ? 'Clips de démonstration' : 'Twitch relié' }}
        </span>
        <span class="pill">{{ count($chainesPayees) }} chaîne{{ count($chainesPayees) > 1 ? 's' : '' }} payée{{ count($chainesPayees) > 1 ? 's' : '' }}</span>
    </x-slot>

    @if (! $utilisable)
        <div class="banner warn">
            <strong>La recherche n'est pas branchée sur Twitch.</strong>
            Il manque les identifiants de l'application. Mieux vaut cet écran vide qu'une
            liste de clips inventés : vous perdriez du temps à chercher des moments qui
            n'existent pas.
        </div>
    @elseif ($simule)
        <div class="banner warn">
            <strong>Mode démonstration.</strong>
            Les clips affichés sont fictifs : titres, vues et durées sont crédibles mais
            faux. Les filtres et la sélection fonctionnent ; les liens mènent à la page de
            clips de la chaîne, pas au moment affiché.
        </div>
    @endif

    @livewire('clip-discovery')
</x-studio-layout>
