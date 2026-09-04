<?php

namespace App\Http\Controllers\Clipper;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompleteProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function update(CompleteProfileRequest $request): RedirectResponse
    {
        $request->user()->forceFill($request->profileAttributes())->save();

        return redirect()
            ->intended(route('dashboard'))
            ->with('status', 'profile-completed');
    }
}
