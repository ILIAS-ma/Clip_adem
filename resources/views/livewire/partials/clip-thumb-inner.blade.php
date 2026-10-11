{{--
    L'intérieur d'une vignette.

    Extrait parce que la vignette est tantôt un bouton — lecteur intégré —
    tantôt un lien vers Twitch, selon que le pilote de recherche est réel ou
    simulé. Deux copies du même contenu finiraient par diverger, et c'est le
    cas simulé, moins regardé, qui garderait l'ancienne version.

    Les trois informations qui décident d'un clip sont posées sur l'image, là
    où l'œil est déjà : combien il dure, combien il a été vu, et ce qu'il
    rapporte.
--}}
@if ($clip['thumbnail'])
    <img src="{{ $clip['thumbnail'] }}" alt="" loading="lazy">
@endif

<span class="badge dur">{{ $clip['duration_label'] }}</span>
<span class="badge views">{{ \App\Support\Money::views($clip['views']) }} vues</span>

@if (isset($cachet) && $cachet)
    <span class="badge rate">{{ \App\Support\Money::euros($cachet) }} / 1000</span>
@endif
