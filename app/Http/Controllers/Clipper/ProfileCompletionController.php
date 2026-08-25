<?php

namespace App\Http\Controllers\Clipper;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileCompletionController extends Controller
{
    public function edit(Request $request): View
    {
        return view('clipper.profile-completion', [
            'user' => $request->user(),
            'missing' => $request->user()->missingProfileFields(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'pseudo' => [
                'required', 'string', 'min:3', 'max:48',
                // Le pseudo apparaît dans les classements publics : on le veut
                // lisible et sans usurpation d'identité par caractères exotiques.
                'regex:/^[\pL\pN._\- ]+$/u',
                Rule::unique('users', 'pseudo')->ignore($user->id),
            ],
            'country' => ['required', 'string', 'size:2'],
            'paypal_email' => ['required', 'email', 'max:255'],
        ], [
            'pseudo.regex' => 'Le pseudo ne peut contenir que des lettres, chiffres, espaces, points, tirets et underscores.',
        ]);

        $user->forceFill([
            ...$validated,
            'country' => strtoupper($validated['country']),
            'profile_completed_at' => now(),
        ])->save();

        return redirect()
            ->intended(route('dashboard'))
            ->with('status', 'profile-completed');
    }
}
