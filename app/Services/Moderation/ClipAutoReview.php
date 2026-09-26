<?php

namespace App\Services\Moderation;

use App\Enums\ClipStatus;
use App\Models\Clip;
use App\Services\Clips\ClipComplianceChecker;
use Illuminate\Support\Facades\Log;

/**
 * Validation automatique d'un clip.
 *
 * Un clip soumis attendait qu'un humain clique. Sur une plateforme tenue par
 * une personne, cette file devient le goulot de tout le produit : le clippeur
 * publie, ses vues montent, et il n'est pas payé parce que personne n'a ouvert
 * l'administration. Il conclut que le site ne compte rien — et il a raison de
 * son point de vue.
 *
 * La validation automatique déplace donc le travail humain là où il sert : au
 * lieu d'approuver la majorité des clips conformes, on n'examine que ceux qui
 * posent une question.
 *
 * Le principe est volontairement asymétrique : **on n'approuve que ce qui est
 * certain**. Tout doute, toute donnée manquante, toute alerte renvoie à un
 * humain. Se tromper ici ne coûte pas un mauvais affichage, ça paie quelqu'un
 * pour un travail qu'il n'a pas fait — et l'argent parti ne revient qu'au prix
 * d'une invalidation, c'est-à-dire d'un conflit.
 */
class ClipAutoReview
{
    public function __construct(
        protected ClipModerationService $moderation,
        protected SuspiciousViewsDetector $suspicious,
    ) {}

    /**
     * Examine un clip et l'approuve s'il ne pose aucune question.
     *
     * Rend la raison du refus d'automatiser, ou null si le clip a été approuvé.
     * Cette raison n'est pas décorative : elle sert à comprendre, plus tard,
     * pourquoi la file d'attente humaine se remplit.
     */
    public function review(Clip $clip): ?string
    {
        if (! config('clipping.moderation.auto_approve')) {
            return 'automatisation désactivée';
        }

        // Ne concerne que ce qui attend. Un clip déjà refusé ou invalidé ne
        // doit jamais remonter tout seul : c'est une décision humaine, et la
        // défaire automatiquement serait la pire des trahisons.
        if ($clip->status !== ClipStatus::PendingReview) {
            return 'statut non concerné';
        }

        if ($clip->user?->is_banned) {
            return 'clippeur banni';
        }

        /*
         * La conformité doit avoir été vérifiée, et avoir réussi.
         *
         * `pending` — le premier relevé n'a pas encore eu lieu — n'est pas une
         * réussite : c'est une absence d'information. Les confondre
         * approuverait tout clip jamais relevé, c'est-à-dire l'inverse du
         * contrôle.
         */
        if ($clip->compliance_status !== ClipComplianceChecker::PASSED) {
            return 'conformité non établie';
        }

        // Ceinture et bretelles : `PASSED` couvre déjà la propriété, mais
        // c'est le seul contrôle dont l'échec signifie payer quelqu'un pour la
        // vidéo d'un inconnu. On le redemande explicitement.
        if (ClipComplianceChecker::ownershipFailed($clip)) {
            return 'propriété non établie';
        }

        if ($this->suspicious->isSuspicious($clip)) {
            return 'vues suspectes';
        }

        // `by: null` : le journal de modération enregistrera une décision sans
        // auteur humain, ce qui est exactement ce qui s'est passé.
        $this->moderation->approve($clip);

        Log::info('Clip validé automatiquement', [
            'clip_id' => $clip->getKey(),
            'campaign_id' => $clip->campaign_id,
        ]);

        return null;
    }
}
