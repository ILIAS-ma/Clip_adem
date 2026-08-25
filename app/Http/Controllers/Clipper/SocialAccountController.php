<?php

namespace App\Http\Controllers\Clipper;

use App\Enums\Platform;
use App\Exceptions\SocialProviderFailed;
use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Services\Social\SocialAccountLinker;
use App\Services\Social\SocialProviderManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SocialAccountController extends Controller
{
    public function __construct(
        protected SocialProviderManager $providers,
        protected SocialAccountLinker $linker,
    ) {}

    public function index(Request $request): View
    {
        return view('clipper.accounts', [
            'accounts' => $request->user()->socialAccounts()->withCount('clips')->get(),
            'platforms' => Platform::cases(),
            'simulated' => collect(Platform::cases())
                ->mapWithKeys(fn (Platform $p) => [$p->value => $this->providers->isSimulated($p)]),
        ]);
    }

    public function redirect(Request $request, string $platform): RedirectResponse
    {
        $platform = Platform::tryFrom($platform);

        abort_unless($platform, 404);

        // Jeton anti-CSRF du parcours OAuth : sans lui, un tiers pourrait faire
        // rattacher son propre compte au profil de la victime.
        $state = Str::random(40);
        $request->session()->put('social.state', $state);
        $request->session()->put('social.platform', $platform->value);

        return redirect()->away($this->providers->for($platform)->redirectUrl($state));
    }

    public function callback(Request $request, string $platform): RedirectResponse
    {
        $platform = Platform::tryFrom($platform);

        abort_unless($platform, 404);

        if ($error = $request->query('error')) {
            return redirect()
                ->route('accounts.index')
                ->withErrors(['social' => 'Connexion annulée ('.$error.').']);
        }

        $expected = $request->session()->pull('social.state');
        $request->session()->forget('social.platform');

        if (! $expected || ! hash_equals($expected, (string) $request->query('state'))) {
            return redirect()
                ->route('accounts.index')
                ->withErrors(['social' => SocialProviderFailed::invalidState()->getMessage()]);
        }

        try {
            $connected = $this->providers->for($platform)->connect((string) $request->query('code'));
            $account = $this->linker->link($request->user(), $connected);
        } catch (SocialProviderFailed $exception) {
            return redirect()->route('accounts.index')->withErrors(['social' => $exception->getMessage()]);
        }

        return redirect()
            ->route('accounts.index')
            ->with('status', sprintf(
                'Compte %s lié : @%s.',
                $platform->label(),
                $account->handle,
            ));
    }

    public function destroy(Request $request, SocialAccount $account): RedirectResponse
    {
        abort_unless($account->user_id === $request->user()->getKey(), 403);

        $this->linker->unlink($account);

        return redirect()
            ->route('accounts.index')
            ->with('status', 'Compte délié. Vos clips déjà soumis restent visibles.');
    }
}
