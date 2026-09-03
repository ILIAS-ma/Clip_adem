<div class="fi-modal-content flex flex-col gap-4 p-2">
    @if (empty($flags))
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Aucun signal anormal sur la courbe de vues de ce clip.
        </p>
    @else
        <p class="text-sm font-medium text-danger-600 dark:text-danger-400">
            {{ count($flags) }} signal{{ count($flags) > 1 ? 'ements' : 'ement' }} détecté{{ count($flags) > 1 ? 's' : '' }} :
        </p>

        <ul class="flex flex-col gap-2">
            @foreach ($flags as $flag)
                <li class="rounded-lg bg-danger-50 px-3 py-2 text-sm text-danger-700 dark:bg-danger-500/10 dark:text-danger-400">
                    {{ $flag }}
                </li>
            @endforeach
        </ul>

        <p class="text-xs text-gray-500 dark:text-gray-400">
            Ces signaux ne prouvent rien : ils indiquent une courbe inhabituelle.
            La décision reste manuelle.
        </p>
    @endif

    <div class="border-t border-gray-200 pt-3 text-sm dark:border-ink-700">
        <div class="flex justify-between py-1">
            <span class="text-gray-500 dark:text-gray-400">Relevés de vues</span>
            <span class="font-medium tabular-nums">{{ $clip->snapshots()->count() }}</span>
        </div>
        <div class="flex justify-between py-1">
            <span class="text-gray-500 dark:text-gray-400">Vues totales</span>
            <span class="font-medium tabular-nums">{{ number_format($clip->views_total, 0, ',', ' ') }}</span>
        </div>
        <div class="flex justify-between py-1">
            <span class="text-gray-500 dark:text-gray-400">Vues rémunérées</span>
            <span class="font-medium tabular-nums">{{ number_format($clip->paid_views, 0, ',', ' ') }}</span>
        </div>
        <div class="flex justify-between py-1">
            <span class="text-gray-500 dark:text-gray-400">Abonnés du compte lié</span>
            <span class="font-medium tabular-nums">
                {{ $clip->socialAccount?->followers_count !== null
                    ? number_format($clip->socialAccount->followers_count, 0, ',', ' ')
                    : '—' }}
            </span>
        </div>
    </div>
</div>
