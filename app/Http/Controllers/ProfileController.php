<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Format;
use Intervention\Image\ImageManager;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Dépôt d'un avatar personnalisé.
     *
     * SVG volontairement absent de la liste : servi depuis notre propre
     * domaine, il peut embarquer du script et s'exécuter dans le contexte
     * du site — même exclusion que pour les pièces jointes de campagne.
     *
     * L'original envoyé (jusqu'à 2 Mo) n'est jamais stocké tel quel : on le
     * recadre en carré et on le recompresse en WebP, pour que chaque avatar
     * pèse quelques ko sur le disque et dans chaque page qui l'affiche.
     */
    public function updateAvatar(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $user = $request->user();
        $previous = $user->avatar_url;

        $image = ImageManager::usingDriver(GdDriver::class)
            ->decode($request->file('avatar')->get())
            ->cover(256, 256);

        $encoded = $image->encodeUsingFormat(Format::WEBP, quality: 80);

        $path = 'avatars/'.Str::uuid().'.webp';
        Storage::disk('public')->put($path, (string) $encoded);

        $user->forceFill(['avatar_url' => Storage::disk('public')->url($path)])->save();

        // Après avoir écrit le nouveau, jamais avant : un envoi qui échoue en
        // cours de route ne doit pas laisser le compte sans aucun avatar.
        if ($previous && Str::startsWith($previous, Storage::disk('public')->url(''))) {
            Storage::disk('public')->delete(Str::after($previous, Storage::disk('public')->url('')));
        }

        return Redirect::route('profile.edit')->with('status', 'avatar-updated');
    }

    public function destroyAvatar(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->avatar_url && Str::startsWith($user->avatar_url, Storage::disk('public')->url(''))) {
            Storage::disk('public')->delete(Str::after($user->avatar_url, Storage::disk('public')->url('')));
        }

        $user->forceFill(['avatar_url' => null])->save();

        return Redirect::route('profile.edit')->with('status', 'avatar-removed');
    }

    /**
     * Suppression du compte.
     *
     * Soft delete, jamais de suppression dure : les clips, les versements et le
     * grand livre référencent ce compte. L'effacer réellement rendrait la
     * comptabilité incohérente et empêcherait de justifier un paiement passé.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
