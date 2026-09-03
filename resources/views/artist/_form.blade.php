@props(['artist' => null])

<div>
    <x-input-label for="name" value="Nom de scène" />
    <x-text-input id="name" name="name" type="text" class="mt-1.5"
                  :value="old('name', $artist?->name)" required autofocus />
    <p class="hint">C'est ce nom que verront les clippeurs sur vos campagnes.</p>
    <x-input-error class="mt-2" :messages="$errors->get('name')" />
</div>

<div>
    <x-input-label for="bio" value="Présentation" />
    <textarea id="bio" name="bio" rows="4" class="field mt-1.5">{{ old('bio', $artist?->bio) }}</textarea>
    <p class="hint">Quelques lignes sur votre univers. Facultatif.</p>
    <x-input-error class="mt-2" :messages="$errors->get('bio')" />
</div>

<div class="grid gap-5 sm:grid-cols-2">
    <div>
        <x-input-label for="spotify_url" value="Spotify" />
        <x-text-input id="spotify_url" name="spotify_url" type="url" class="mt-1.5"
                      :value="old('spotify_url', $artist?->spotify_url)" placeholder="https://open.spotify.com/…" />
        <x-input-error class="mt-2" :messages="$errors->get('spotify_url')" />
    </div>

    <div>
        <x-input-label for="tiktok_handle" value="TikTok" />
        <x-text-input id="tiktok_handle" name="tiktok_handle" type="text" class="mt-1.5"
                      :value="old('tiktok_handle', $artist?->tiktok_handle)" placeholder="votrepseudo" />
        <x-input-error class="mt-2" :messages="$errors->get('tiktok_handle')" />
    </div>

    <div>
        <x-input-label for="instagram_handle" value="Instagram" />
        <x-text-input id="instagram_handle" name="instagram_handle" type="text" class="mt-1.5"
                      :value="old('instagram_handle', $artist?->instagram_handle)" placeholder="votrepseudo" />
        <x-input-error class="mt-2" :messages="$errors->get('instagram_handle')" />
    </div>

    <div>
        <x-input-label for="youtube_handle" value="YouTube" />
        <x-text-input id="youtube_handle" name="youtube_handle" type="text" class="mt-1.5"
                      :value="old('youtube_handle', $artist?->youtube_handle)" placeholder="votrechaine" />
        <x-input-error class="mt-2" :messages="$errors->get('youtube_handle')" />
    </div>
</div>
