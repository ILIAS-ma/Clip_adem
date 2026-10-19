<?php

use App\Models\Clip;
use App\Services\Moderation\ClipModerationService;
use Illuminate\Database\Migrations\Migration;

/**
 * Plus de validation des clips : tout clip soumis est bon.
 *
 * Ceux restés en attente sont validés par le service, et non par une simple
 * mise à jour : chacun garde sa trace au journal de modération et touche la
 * prime de lancement de sa campagne s'il y a droit. Leurs vues déjà relevées
 * seront payées au relevé suivant.
 */
return new class extends Migration
{
    public function up(): void
    {
        $moderation = app(ClipModerationService::class);

        Clip::where('status', 'pending_review')->each(fn (Clip $clip) => $moderation->approve($clip));
    }

    public function down(): void
    {
        // Rien à défaire : remettre ces clips en attente bloquerait des vues
        // déjà payées.
    }
};
