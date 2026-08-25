<?php

namespace App\Contracts;

use App\Enums\Platform;
use App\Models\SocialAccount;
use App\Support\Social\ConnectedAccount;
use App\Support\Social\PostMetrics;
use Illuminate\Support\Collection;

/**
 * Contrat commun aux trois réseaux.
 *
 * Tout ce qui dépend d'une API externe passe par ici. C'est ce qui permet de
 * construire et de tester la conformité, la synchronisation et le tableau de
 * bord sans posséder la moindre clé : un fournisseur simulé suffit.
 */
interface SocialProvider
{
    public function platform(): Platform;

    /** Des identifiants d'application sont-ils configurés ? */
    public function isConfigured(): bool;

    /** URL de consentement, avec le jeton anti-CSRF de la session. */
    public function redirectUrl(string $state): string;

    public function connect(string $code): ConnectedAccount;

    public function refresh(SocialAccount $account): ConnectedAccount;

    /**
     * Relevés d'un lot de publications.
     *
     * Le lot est ce qui économise le quota : YouTube facture une unité pour
     * cinquante identifiants. Les publications introuvables sont simplement
     * absentes du résultat.
     *
     * @param  array<int, string>  $externalIds
     * @return Collection<string, PostMetrics> Indexée par identifiant de post.
     */
    public function fetchPosts(SocialAccount $account, array $externalIds): Collection;

    /** Nombre maximum d'identifiants par appel. */
    public function batchSize(): int;

    /** Unités de quota consommées par un appel. */
    public function quotaCostPerCall(): int;

    /** Plafond quotidien, ou null si la plateforme n'en publie pas. */
    public function dailyQuota(): ?int;
}
