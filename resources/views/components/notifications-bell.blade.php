@php
    // Chargées ici plutôt que passées par chaque contrôleur : la cloche
    // apparaît sur toutes les pages de l'espace connecté, un contrôleur par
    // page n'a pas à savoir qu'elle existe.
    $unreadCount = auth()->user()->unreadNotifications()->count();
    $recentNotifications = auth()->user()->notifications()->latest()->limit(8)->get();
@endphp

<div x-data="{ open: false }" class="relative" @click.outside="open = false">
    <button @click="open = ! open"
            class="relative flex h-9 w-9 items-center justify-center rounded-full text-ink-300 transition hover:bg-ink-800 hover:text-ink-50"
            aria-label="Notifications">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
        </svg>

        @if ($unreadCount > 0)
            <span class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-brand-500 px-1 text-[10px] font-bold text-ink-950">
                {{ $unreadCount > 9 ? '9+' : $unreadCount }}
            </span>
        @endif
    </button>

    <div x-show="open" x-cloak
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         class="absolute right-0 z-50 mt-2 w-80 origin-top-right rounded-xl border border-ink-700 bg-ink-900 shadow-lifted">
        <div class="flex items-center justify-between border-b border-ink-700 px-4 py-3">
            <span class="text-sm font-semibold text-ink-50">Notifications</span>
            @if ($unreadCount > 0)
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    <button type="submit" class="text-xs font-medium text-brand-400 hover:underline">Tout marquer lu</button>
                </form>
            @endif
        </div>

        @if ($recentNotifications->isEmpty())
            <p class="px-4 py-8 text-center text-sm text-ink-300">Rien pour l'instant.</p>
        @else
            <ul class="max-h-96 divide-y divide-ink-700 overflow-y-auto">
                @foreach ($recentNotifications as $notification)
                    <li>
                        <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                            @csrf
                            <button type="submit" @class([
                                'block w-full px-4 py-3 text-left transition hover:bg-ink-800',
                                'bg-brand-500/5' => is_null($notification->read_at),
                            ])>
                                <p class="text-sm font-medium text-ink-50">{{ $notification->data['title'] ?? '' }}</p>
                                <p class="mt-0.5 line-clamp-2 text-xs leading-relaxed text-ink-300">{{ $notification->data['body'] ?? '' }}</p>
                                <p class="mt-1 text-[11px] text-ink-500">{{ $notification->created_at->diffForHumans() }}</p>
                            </button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif

        <a href="{{ route('notifications.index') }}" class="block border-t border-ink-700 px-4 py-3 text-center text-sm font-medium text-ink-300 hover:text-ink-50">
            Tout voir
        </a>
    </div>
</div>
