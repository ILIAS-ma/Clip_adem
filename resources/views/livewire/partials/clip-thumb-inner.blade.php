{{--
    L'intérieur d'une vignette.

    Extrait parce que la vignette est tantôt un bouton — lecteur intégré —
    tantôt un lien vers Twitch, selon que le pilote de recherche est réel ou
    simulé. Deux copies du même contenu finiraient par diverger, et c'est le
    cas simulé, moins regardé, qui garderait l'ancienne version.
--}}
@if ($clip['thumbnail'])
    <img src="{{ $clip['thumbnail'] }}" alt="" loading="lazy">
@endif

@if ($clip['language'])
    <span class="badge flag">{{ mb_strtoupper($clip['language']) }}</span>
@endif

<span class="badge views">{{ \App\Support\Money::views($clip['views']) }} vues</span>
<span class="badge dur">{{ $clip['duration_label'] }}</span>
