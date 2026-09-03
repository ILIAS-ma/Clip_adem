<?php

namespace App\Http\Controllers\Artist;

use App\Http\Controllers\Controller;
use App\Models\Artist;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ArtistProfileController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if ($request->user()->artist) {
            return redirect()->route('artist.dashboard');
        }

        return view('artist.profile-create');
    }

    public function edit(Request $request): View
    {
        return view('artist.profile-edit', ['artist' => $request->user()->artist]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($request->user()->artist) {
            return redirect()->route('artist.dashboard');
        }

        $validated = $this->validated($request);

        Artist::create([
            ...$validated,
            'user_id' => $request->user()->getKey(),
            'slug' => $this->uniqueSlug($validated['name']),
            // Une fiche créée depuis l'inscription publique n'est pas encore
            // validée : l'administrateur l'active avant de lui associer des
            // campagnes, sinon n'importe qui apparaîtrait au catalogue.
            'is_active' => false,
        ]);

        return redirect()
            ->route('artist.dashboard')
            ->with('status', 'Profil créé. Un administrateur va le valider avant de lancer vos campagnes.');
    }

    public function update(Request $request): RedirectResponse
    {
        $artist = $request->user()->artist;

        $artist->update($this->validated($request, $artist));

        return redirect()->route('artist.profile.edit')->with('status', 'Profil mis à jour.');
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request, ?Artist $artist = null): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'min:2', 'max:255',
                Rule::unique('artists', 'name')->ignore($artist?->getKey()),
            ],
            'bio' => ['nullable', 'string', 'max:2000'],
            'spotify_url' => ['nullable', 'url', 'max:255'],
            'tiktok_handle' => ['nullable', 'string', 'max:64'],
            'instagram_handle' => ['nullable', 'string', 'max:64'],
            'youtube_handle' => ['nullable', 'string', 'max:64'],
        ], [
            'name.unique' => 'Un artiste porte déjà ce nom de scène.',
        ]);
    }

    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'artiste';
        $slug = $base;
        $suffix = 2;

        while (Artist::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
