<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <h1 class="font-display text-2xl font-bold text-ink-50">Notifications</h1>

            @if ($notifications->isNotEmpty())
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    <button type="submit" class="btn-ghost">Tout marquer lu</button>
                </form>
            @endif
        </div>
    </x-slot>

    <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
        @if ($notifications->isEmpty())
            <div class="card px-6 py-16 text-center">
                <p class="font-display text-lg font-bold text-ink-50">Rien pour l'instant</p>
                <p class="mx-auto mt-2 max-w-sm text-sm text-ink-300">
                    Vous serez prévenu ici dès qu'un clip est validé ou refusé, ou qu'un retrait est versé.
                </p>
            </div>
        @else
            <div class="card overflow-hidden">
                <ul class="divide-y divide-ink-700">
                    @foreach ($notifications as $notification)
                        <li>
                            <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                                @csrf
                                <button type="submit" @class([
                                    'flex w-full items-start justify-between gap-4 px-6 py-4 text-left transition hover:bg-ink-800',
                                    'bg-brand-500/5' => is_null($notification->read_at),
                                ])>
                                    <div>
                                        <p class="font-semibold text-ink-50">{{ $notification->data['title'] ?? '' }}</p>
                                        <p class="mt-1 text-sm leading-relaxed text-ink-300">{{ $notification->data['body'] ?? '' }}</p>
                                    </div>
                                    <span class="flex-none text-xs text-ink-500">{{ $notification->created_at->diffForHumans() }}</span>
                                </button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="mt-6">
                {{ $notifications->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
