<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\MailDelivery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class EmailVerificationNotificationController extends Controller
{
    /**
     * Renvoie le lien de confirmation.
     *
     * Les deux issues possibles sont dites franchement. Sans elles, cette
     * route rendait une page 500 — SMTP local inexistant sur le serveur — à
     * quelqu'un qui venait simplement de cliquer sur « renvoyer ».
     */
    public function store(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended($request->user()->role->homeRoute());
        }

        if (! MailDelivery::isActive()) {
            return back()->withErrors(['email' => MailDelivery::inactiveMessage()]);
        }

        try {
            $request->user()->sendEmailVerificationNotification();
        } catch (TransportExceptionInterface $e) {
            // Signalé pour nous, expliqué pour la personne : le détail du
            // transport ne lui apprend rien et l'inquiète.
            report($e);

            return back()->withErrors(['email' => MailDelivery::failedMessage()]);
        }

        return back()->with('status', 'verification-link-sent');
    }
}
