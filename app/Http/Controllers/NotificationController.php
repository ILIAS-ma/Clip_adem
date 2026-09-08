<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Commun aux deux rôles : un clippeur et un créateur reçoivent chacun leurs
 * propres notifications, mais la mécanique (lister, marquer lu, rediriger
 * vers la cible) ne dépend d'aucun des deux.
 */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('notifications.index', [
            'notifications' => $request->user()->notifications()->paginate(20),
        ]);
    }

    /**
     * Marque une notification lue puis renvoie vers sa cible : le clic sur la
     * cloche ne doit pas être un aller-retour, juste ouvrir directement ce
     * qu'elle annonce.
     */
    public function read(Request $request, string $notification): RedirectResponse
    {
        $model = $request->user()->notifications()->findOrFail($notification);
        $model->markAsRead();

        return redirect($model->data['url'] ?? route('notifications.index'));
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }
}
