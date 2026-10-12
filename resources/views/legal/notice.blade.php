@php
    $editeur = config('legal.publisher');
    $hebergeur = config('legal.host');
@endphp

<x-legal.layout title="Mentions légales" :updated-at="$updatedAt" :contact-email="$contactEmail">

    {{--
        Chaque champ s'affiche toujours. Vide, il montre « [à compléter] » :
        jamais d'identité inventée, et ce qu'il reste à renseigner se voit
        d'un coup d'œil. Les valeurs viennent du .env (config/legal.php),
        posées sur le serveur avec `~/clipadem-set LEGAL_… "valeur"`.
    --}}
    @php
        $champ = fn (?string $valeur) => filled($valeur)
            ? e($valeur)
            : '<span style="color: rgb(217 96 104); font-weight: 600;">[à compléter]</span>';
    @endphp

    <section>
        <h2>Éditeur du site</h2>
        <p>
            Le site <strong>{{ config('app.name') }}</strong> ({{ preg_replace('#^https?://#', '', config('app.url')) }})
            est édité par :
        </p>
        <ul>
            <li>Nom ou raison sociale : <strong>{!! $champ($editeur['name']) !!}</strong></li>
            <li>Statut juridique : {!! $champ($editeur['status']) !!}</li>
            <li>SIRET : {!! $champ($editeur['siret']) !!}</li>
            @if (filled($editeur['vat']))
                <li>TVA intracommunautaire : {{ $editeur['vat'] }}</li>
            @endif
            <li>Adresse : {!! $champ($editeur['address']) !!}</li>
            <li>Téléphone : {!! $champ($editeur['phone']) !!}</li>
            <li>E-mail :
                @if (filled($editeur['email']))
                    <a href="mailto:{{ $editeur['email'] }}">{{ $editeur['email'] }}</a>
                @else
                    {!! $champ(null) !!}
                @endif
            </li>
        </ul>
        <p>Directeur de la publication : {!! $champ($editeur['director']) !!}</p>
    </section>

    <section>
        <h2>Hébergement</h2>
        <ul>
            <li><strong>{{ $hebergeur['name'] }}</strong>, {{ $hebergeur['details'] }}</li>
            <li>Adresse : {{ $hebergeur['address'] }}</li>
            <li>Téléphone : {{ $hebergeur['phone'] }}</li>
            <li>Site : <a href="{{ $hebergeur['website'] }}" rel="noopener">{{ preg_replace('#^https?://#', '', $hebergeur['website']) }}</a></li>
        </ul>
    </section>

    <section>
        <h2>Propriété intellectuelle</h2>
        <p>
            Le nom {{ config('app.name') }}, son logo et l'ensemble des éléments du site (textes,
            interface, code) sont protégés. Toute reproduction sans autorisation est interdite.
        </p>
        <p>
            Les clips publiés par les clippeurs restent soumis aux droits de leurs auteurs et des
            créateurs dont ils reprennent le contenu, dans le cadre défini par chaque campagne.
        </p>
    </section>

    <section>
        <h2>Données personnelles</h2>
        <p>
            Le traitement de vos données est décrit dans la
            <a href="{{ route('legal.privacy') }}">politique de confidentialité</a>. Vous pouvez
            exercer vos droits d'accès, de rectification et d'effacement en écrivant à
            <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>, et saisir la CNIL
            (cnil.fr) si vous estimez que vos droits ne sont pas respectés.
        </p>
    </section>

    <section>
        <h2>Conditions d'utilisation</h2>
        <p>
            L'usage du service est régi par les
            <a href="{{ route('legal.terms') }}">conditions d'utilisation</a>.
        </p>
    </section>

</x-legal.layout>
