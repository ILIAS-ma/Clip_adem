<?php

namespace App\Http\Controllers\Creator;

use App\Http\Controllers\Controller;
use App\Models\Creator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if ($request->user()->creator) {
            return redirect()->route('creator.dashboard');
        }

        return view('creator.profile-create');
    }

    public function edit(Request $request): View
    {
        return view('creator.profile-edit', ['creator' => $request->user()->creator]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($request->user()->creator) {
            return redirect()->route('creator.dashboard');
        }

        $validated = $this->validated($request);

        // Une fiche créée depuis l'inscription publique n'est normalement pas
        // validée : l'administrateur l'active avant de lui associer des
        // campagnes, sinon n'importe qui apparaîtrait au catalogue sous le nom
        // de scène qu'il veut. Le contrôle est suspendable en développement.
        $needsValidation = (bool) config('clipping.onboarding.require_creator_validation');

        Creator::create([
            ...$validated,
            'user_id' => $request->user()->getKey(),
            'slug' => $this->uniqueSlug($validated['name']),
            'is_active' => ! $needsValidation,
        ]);

        return redirect()
            ->route('creator.dashboard')
            ->with('status', $needsValidation
                ? 'Profil créé. Un administrateur va le valider avant de lancer vos campagnes.'
                : 'Profil créé. Vous pouvez suivre vos campagnes dès maintenant.');
    }

    public function update(Request $request): RedirectResponse
    {
        $creator = $request->user()->creator;

        $creator->update($this->validated($request, $creator));

        return redirect()->route('creator.profile.edit')->with('status', 'Profil mis à jour.');
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request, ?Creator $creator = null): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'min:2', 'max:255',
                Rule::unique('creators', 'name')->ignore($creator?->getKey()),
            ],
            'bio' => ['nullable', 'string', 'max:2000'],
            'spotify_url' => ['nullable', 'url', 'max:255'],
            'tiktok_handle' => ['nullable', 'string', 'max:64'],
            'instagram_handle' => ['nullable', 'string', 'max:64'],
            'youtube_handle' => ['nullable', 'string', 'max:64'],
        ], [
            'name.unique' => 'Un créateur porte déjà ce nom de scène.',
        ]);
    }

    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'créateur';
        $slug = $base;
        $suffix = 2;

        while (Creator::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
