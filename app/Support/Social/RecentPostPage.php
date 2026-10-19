<?php

namespace App\Support\Social;

use Illuminate\Support\Collection;

/** Une page de publications récentes, et de quoi demander la suivante. */
final readonly class RecentPostPage
{
    /**
     * @param  Collection<int, RecentPost>  $posts  les plus récentes d'abord
     * @param  string|null  $nextCursor  null quand il n'y a plus rien de plus ancien
     */
    public function __construct(
        public Collection $posts,
        public ?string $nextCursor = null,
    ) {}
}
