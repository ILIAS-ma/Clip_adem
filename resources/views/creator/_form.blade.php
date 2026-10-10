@props(['creator' => null])

@php
    use App\Enums\CreatorKind;

    /*
     * La nature vient du formulaire renvoyé en erreur, de la fiche sinon. Elle
     * décide des libellés et des liens demandés : un streamer n'a pas de « nom
     * de scène », et son Spotify n'intéresse personne.
     */
    $kind = CreatorKind::tryFrom((string) old('kind'))
        ?? $creator?->kind()
        ?? CreatorKind::Artist;

    $libellesNom = collect(CreatorKind::cases())
        ->mapWithKeys(fn (CreatorKind $c) => [$c->value => $c->nameLabel()]);
@endphp

{{--
    Déclaratif, et c'est volontaire : la nature n'ouvre aucun droit, elle ne
    décide que des champs demandés et des libellés. Un administrateur la
    corrige depuis le back-office au besoin.

    Le basculement se fait côté navigateur, sans renvoi : renvoyer le
    formulaire pour changer de type le ferait passer par la validation, et on
    répondrait « le nom est obligatoire » à quelqu'un qui n'a encore rien
    rempli. Les champs de l'autre type restent dans le document, simplement
    masqués — ce qui préserve aussi les valeurs déjà saisies pour un créateur
    qui se met à streamer.
--}}
<div x-data="{ kind: '{{ $kind->value }}' }" class="space-y-6">

    <div>
        <x-input-label value="Type de compte" />

        <div class="mt-2 grid gap-3 sm:grid-cols-2">
            @foreach (CreatorKind::cases() as $cas)
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition"
                       :class="kind === '{{ $cas->value }}'
                           ? 'border-white/40 bg-white/[0.04]'
                           : 'border-ink-700 hover:border-ink-600'">
                    <input type="radio" name="kind" value="{{ $cas->value }}" x-model="kind"
                           class="mt-0.5 border-ink-600 bg-ink-900 text-ink-50 focus:ring-ink-400">
                    <span>
                        <span class="block font-semibold text-ink-50">{{ $cas->label() }}</span>
                        <span class="mt-0.5 block text-sm leading-relaxed text-ink-400">
                            {{ $cas === CreatorKind::Streamer
                                ? 'Vos directs et rediffusions sont la matière à clipper.'
                                : 'Vos morceaux et extraits sont la matière à clipper.' }}
                        </span>
                    </span>
                </label>
            @endforeach
        </div>

        <x-input-error class="mt-2" :messages="$errors->get('kind')" />
    </div>

    <div>
        {{-- Le libellé juste est rendu par le serveur, puis suivi par Alpine :
             sans le premier, un streamer verrait « Nom de scène » le temps que
             le script s'initialise. --}}
        <label class="label" for="name"
               x-text="@js($libellesNom)[kind]">{{ $kind->nameLabel() }}</label>

        <x-text-input id="name" name="name" type="text" class="mt-1.5"
                      :value="old('name', $creator?->name)" required autofocus />
        <p class="hint">C'est ce nom que verront les clippeurs sur vos campagnes.</p>
        <x-input-error class="mt-2" :messages="$errors->get('name')" />
    </div>

    <div>
        <x-input-label for="bio" value="Présentation" />
        <textarea id="bio" name="bio" rows="4" class="field mt-1.5">{{ old('bio', $creator?->bio) }}</textarea>
        <p class="hint">
            <span x-show="kind === 'streamer'" x-cloak>
                Votre univers en quelques lignes : le genre de moments à clipper. Facultatif.
            </span>
            <span x-show="kind !== 'streamer'">Quelques lignes sur votre univers. Facultatif.</span>
        </p>
        <x-input-error class="mt-2" :messages="$errors->get('bio')" />
    </div>

    <div class="grid gap-5 sm:grid-cols-2">
        @foreach (CreatorKind::cases() as $cas)
            @foreach ($cas->ownLinks() as $nom => $meta)
                <div x-show="kind === '{{ $cas->value }}'" x-cloak
                     @class(['sm:col-span-2' => $nom === 'vod_url'])>
                    <x-input-label :for="$nom" :value="$meta['label']" />
                    <x-text-input :id="$nom" :name="$nom" :type="$meta['url'] ? 'url' : 'text'" class="mt-1.5"
                                  :value="old($nom, $creator?->{$nom})" :placeholder="$meta['placeholder']" />
                    @if ($nom === 'vod_url')
                        <p class="hint">
                            Sans accès à vos rediffusions, un clippeur n'a rien à clipper :
                            c'est le champ qui fait tourner vos campagnes.
                        </p>
                    @endif
                    <x-input-error class="mt-2" :messages="$errors->get($nom)" />
                </div>
            @endforeach
        @endforeach

        <div>
            <x-input-label for="tiktok_handle" value="TikTok" />
            <x-text-input id="tiktok_handle" name="tiktok_handle" type="text" class="mt-1.5"
                          :value="old('tiktok_handle', $creator?->tiktok_handle)" placeholder="votrepseudo" />
            <x-input-error class="mt-2" :messages="$errors->get('tiktok_handle')" />
        </div>

        <div>
            <x-input-label for="instagram_handle" value="Instagram" />
            <x-text-input id="instagram_handle" name="instagram_handle" type="text" class="mt-1.5"
                          :value="old('instagram_handle', $creator?->instagram_handle)" placeholder="votrepseudo" />
            <x-input-error class="mt-2" :messages="$errors->get('instagram_handle')" />
        </div>

        <div>
            <x-input-label for="youtube_handle" value="YouTube" />
            <x-text-input id="youtube_handle" name="youtube_handle" type="text" class="mt-1.5"
                          :value="old('youtube_handle', $creator?->youtube_handle)" placeholder="votrechaine" />
            <x-input-error class="mt-2" :messages="$errors->get('youtube_handle')" />
        </div>
    </div>
</div>
