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
     * Le formulaire de demande de lien.
     *
     * Fermé tant qu'aucun e-mail ne peut partir : l'écran n'aurait rien à
     * offrir, et le lien qui y menait est déjà masqué sur la page de
     * connexion. On renvoie à la connexion sans message — annoncer une panne
     * à quelqu'un qui cherchait simplement à entrer ne l'avance à rien.
     */
    public function create(): View|RedirectResponse
    {
        if (! MailDelivery::isActive()) {
            return redirect()->route('login');
        }

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

        // Même porte fermée que pour l'affichage : la route reste
        // atteignable à la main, elle ne doit pas pour autant faire croire à
        // un envoi.
        if (! MailDelivery::isActive()) {
            return redirect()->route('login');
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
