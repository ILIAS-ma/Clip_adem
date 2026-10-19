<?php

namespace App\Contracts;

use App\Models\SocialAccount;
use App\Support\Social\RecentPostPage;

/**
 * Plateforme capable de lister les dernières publications d'un compte lié.
 *
 * C'est ce qui permet au clippeur, sur téléphone, de toucher sa vidéo dans une
 * liste plutôt que de copier puis coller un lien. Une plateforme qui ne le sait
 * pas faire laisse simplement le collage du lien comme seul chemin.
 */
interface ListsRecentPosts
{
    /**
     * Une page de publications, les plus récentes d'abord. `$cursor` vient de
     * la page précédente (RecentPostPage::$nextCursor) pour remonter plus loin.
     */
    public function recentPosts(SocialAccount $account, int $limit = 20, ?string $cursor = null): RecentPostPage;
}
