@php
    $editeur = config('legal.publisher');
    $hebergeur = config('legal.host');
@endphp

<x-legal.layout title="Mentions légales" :updated-at="$updatedAt" :contact-email="$contactEmail">

    <section>
        <h2>Éditeur du site</h2>
        @if (filled($editeur['name']))
            <p>
                Le site <strong>{{ config('app.name') }}</strong> ({{ preg_replace('#^https?://#', '', config('app.url')) }})
                est édité par :
            </p>
            <ul>
                <li><strong>{{ $editeur['name'] }}</strong>@if (filled($editeur['status'])), {{ $editeur['status'] }}@endif</li>
                @if (filled($editeur['siret']))
                    <li>SIRET : {{ $editeur['siret'] }}</li>
                @endif
                @if (filled($editeur['vat']))
                    <li>TVA intracommunautaire : {{ $editeur['vat'] }}</li>
                @endif
                @if (filled($editeur['address']))
                    <li>Adresse : {{ $editeur['address'] }}</li>
                @endif
                @if (filled($editeur['phone']))
                    <li>Téléphone : {{ $editeur['phone'] }}</li>
                @endif
                @if (filled($editeur['email']))
                    <li>E-mail : <a href="mailto:{{ $editeur['email'] }}">{{ $editeur['email'] }}</a></li>
                @endif
            </ul>
            @if (filled($editeur['director']))
                <p>Directeur de la publication : {{ $editeur['director'] }}.</p>
            @endif
        @else
            {{-- Jamais d'identité inventée : tant qu'elle n'est pas renseignée,
                 la page le dit et donne le moyen de joindre l'éditeur. --}}
            <p>
                Les informations relatives à l'éditeur sont en cours de mise à jour. Pour toute
                demande, écrivez à
                <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>.
            </p>
        @endif
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
