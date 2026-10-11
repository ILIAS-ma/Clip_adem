<?php

namespace App\Http\Controllers\Clipper;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Services\Media\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Remet un fichier à son propriétaire.
 *
 * Le disque est privé : rien de ce dossier n'est servi par le serveur web.
 * C'est la seule façon d'empêcher qu'une adresse devinée donne accès au
 * travail de quelqu'un d'autre — un dossier public sous `storage/` est
 * lisible par quiconque connaît le nom du fichier, et ces noms contiennent
 * le titre du clip.
 */
class MediaController extends Controller
{
    public function download(Request $request, MediaAsset $asset): StreamedResponse
    {
        // Portée par l'utilisateur, pas seulement par l'identifiant : c'est
        // ce contrôle, et lui seul, qui sépare les espaces.
        abort_unless($asset->user_id === $request->user()->getKey(), 404);
        abort_unless($asset->estPret() && $asset->path, 404);

        $disque = Storage::disk(Workspace::DISQUE);

        abort_unless($disque->exists($asset->path), 404);

        return $disque->download($asset->path, basename((string) $asset->path));
    }

    public function destroy(Request $request, MediaAsset $asset)
    {
        abort_unless($asset->user_id === $request->user()->getKey(), 404);

        $asset->supprimerAvecFichier();

        return back()->with('status', 'Fichier supprimé.');
    }
}
