<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\MailDelivery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        /*
         * Dit avant d'essayer : sans prestataire, le lien n'est écrit que
         * dans un journal du serveur. Annoncer « lien envoyé » laisserait
         * quelqu'un rafraîchir sa boîte indéfiniment, persuadé d'avoir mal
         * tapé son adresse.
         */
        if (! MailDelivery::isActive()) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => MailDelivery::inactiveMessage()]);
        }

        try {
            $status = Password::sendResetLink($request->only('email'));
        } catch (TransportExceptionInterface $e) {
            // Le cas vécu sur la production : un SMTP configuré sur un hôte
            // qui n'écoute pas. L'exception remontait jusqu'à une page 500,
            // à l'endroit exact où l'on vient parce qu'on est déjà bloqué.
            report($e);

            return back()->withInput($request->only('email'))
                ->withErrors(['email' => MailDelivery::failedMessage()]);
        }

        return $status == Password::RESET_LINK_SENT
                    ? back()->with('status', __($status))
                    : back()->withInput($request->only('email'))
                        ->withErrors(['email' => __($status)]);
    }
}
