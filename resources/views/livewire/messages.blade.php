{{-- Relevé lent : le filet si le WebSocket est coupé. Livewire le suspend
     de lui-même quand l'onglet n'est pas visible. --}}
<div wire:poll.30s class="card flex flex-col overflow-hidden" style="height: min(70vh, 720px)">
    {{-- Toujours collé au dernier message, y compris quand Livewire en
         ajoute un : on observe le fil plutôt que chaque événement qui
         pourrait le modifier. --}}
    <div x-data
         x-init="$el.scrollTop = $el.scrollHeight;
                 new MutationObserver(() => $el.scrollTop = $el.scrollHeight)
                     .observe($el, { childList: true, subtree: true })"
         class="flex-1 space-y-3 overflow-y-auto px-4 py-5 sm:px-6">
        @forelse ($messages as $message)
            <div wire:key="message-{{ $message->id }}"
                 @class(['flex', 'justify-end' => ! $message->from_staff, 'justify-start' => $message->from_staff])>
                <div @class([
                    'max-w-[85%] rounded-2xl px-4 py-2.5 text-sm leading-relaxed sm:max-w-[70%]',
                    'bg-brand-500 text-white' => ! $message->from_staff,
                    'bg-ink-800 text-ink-100' => $message->from_staff,
                ])>
                    @if ($message->from_staff)
                        <p class="mb-0.5 text-xs font-semibold text-brand-400">Équipe {{ config('app.name') }}</p>
                    @endif
                    <p class="whitespace-pre-line break-words">{{ $message->body }}</p>
                    <p @class([
                        'mt-1 text-[11px]',
                        'text-white/70' => ! $message->from_staff,
                        'text-ink-500' => $message->from_staff,
                    ])>{{ $message->created_at->diffForHumans() }}</p>
                </div>
            </div>
        @empty
            <div class="flex h-full flex-col items-center justify-center text-center">
                <p class="font-display text-lg font-bold text-ink-50">Une question ?</p>
                <p class="mx-auto mt-2 max-w-sm text-sm text-ink-300">
                    Un versement, un clip refusé, un compte qui ne se relie pas : écrivez ici,
                    l'équipe vous répond dans ce fil.
                </p>
            </div>
        @endforelse
    </div>

    <form wire:submit="send" class="border-t border-ink-700 p-3 sm:p-4">
        <div class="flex items-end gap-2">
            <label for="message-body" class="sr-only">Votre message</label>
            {{-- Entrée envoie, Maj+Entrée revient à la ligne : le réflexe de
                 toutes les messageries. --}}
            <textarea id="message-body" wire:model="body" rows="1" maxlength="{{ \App\Models\Message::MAX_LENGTH }}"
                      x-data
                      x-on:keydown.enter="if (! $event.shiftKey) { $event.preventDefault(); $el.form.requestSubmit(); }"
                      placeholder="Écrivez votre message…"
                      class="field min-h-[44px] flex-1 resize-none text-sm"></textarea>
            <button type="submit" class="btn-brand flex-none" wire:loading.attr="disabled" wire:target="send">
                Envoyer
            </button>
        </div>
        <x-input-error class="mt-2" :messages="$errors->get('body')" />
    </form>
</div>
