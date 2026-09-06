<x-filament-panels::page>
    {{-- Le nombre de destinataires, en gros, au-dessus du formulaire : c'est
         la seule information qui empêche de se tromper de groupe. --}}
    <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-white/10 dark:bg-white/5">
        <p class="text-sm text-gray-500 dark:text-gray-400">Destinataires visés</p>
        <p class="mt-1 text-3xl font-bold tracking-tight text-gray-950 dark:text-white">
            {{ number_format($this->recipientCount(), 0, ',', ' ') }}
        </p>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            {{ \App\Filament\Pages\SendBroadcast::audiences()[$this->data['audience'] ?? ''] ?? '—' }}
        </p>
    </div>

    <form wire:submit.prevent="send">
        {{ $this->form }}
    </form>
</x-filament-panels::page>
