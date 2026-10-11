<x-filament-panels::page>
    {{--
        Mise en page en styles en ligne : le back-office n'a pas de thème
        compilé à part, et une classe Tailwind absente de la feuille de
        Filament ne s'appliquerait tout simplement pas.

        Relevé lent en filet, si le WebSocket est coupé.
    --}}
    @php
        $conversations = $this->conversations();
        $nouveaux = $this->newRecipients();
        $ouverte = $this->openConversation();
    @endphp

    <div wire:poll.30s style="display: grid; grid-template-columns: minmax(240px, 340px) 1fr; gap: 1rem; align-items: start;">

        {{-- La liste des fils --}}
        <x-filament::section :compact="true">
            <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                <x-filament::input type="search" wire:model.live.debounce.300ms="search"
                                   placeholder="Chercher un utilisateur…" />
            </x-filament::input.wrapper>

            <div style="margin-top: .75rem; max-height: 65vh; overflow-y: auto; display: flex; flex-direction: column; gap: .25rem;">
                @foreach ($conversations as $c)
                    <button type="button" wire:key="conv-{{ $c->id }}" wire:click="open({{ $c->id }})"
                            style="text-align: left; padding: .6rem .7rem; border-radius: .6rem; border: 1px solid {{ $ouverte?->id === $c->id ? 'rgba(204,63,69,.6)' : 'transparent' }}; background: {{ $ouverte?->id === $c->id ? 'rgba(204,63,69,.12)' : 'transparent' }};">
                        <div style="display: flex; justify-content: space-between; gap: .5rem; align-items: center;">
                            <strong style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                {{ $c->user?->displayName() ?? 'Compte supprimé' }}
                            </strong>
                            @if ($c->unread_for_staff_count > 0)
                                <x-filament::badge color="danger" size="sm">{{ $c->unread_for_staff_count }}</x-filament::badge>
                            @endif
                        </div>
                        <div style="font-size: .75rem; opacity: .6;">
                            {{ $c->user?->role?->label() }} · {{ $c->last_message_at?->diffForHumans() }}
                        </div>
                        <div style="font-size: .8rem; opacity: .75; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                            @if ($c->latestMessage?->from_staff) <em>Vous :</em> @endif
                            {{ \Illuminate\Support\Str::limit($c->latestMessage?->body, 60) }}
                        </div>
                    </button>
                @endforeach

                @if ($nouveaux->isNotEmpty())
                    <div style="margin-top: .5rem; font-size: .75rem; opacity: .6; text-transform: uppercase; letter-spacing: .05em;">
                        Écrire à
                    </div>
                    @foreach ($nouveaux as $u)
                        <button type="button" wire:key="user-{{ $u->id }}" wire:click="startWith({{ $u->id }})"
                                style="text-align: left; padding: .5rem .7rem; border-radius: .6rem;">
                            <strong>{{ $u->displayName() }}</strong>
                            <div style="font-size: .75rem; opacity: .6;">{{ $u->role->label() }} · {{ $u->email }}</div>
                        </button>
                    @endforeach
                @endif

                @if ($conversations->isEmpty() && $nouveaux->isEmpty())
                    <p style="font-size: .875rem; opacity: .6; padding: .5rem;">
                        {{ mb_strlen(trim($search)) >= 2 ? 'Personne ne correspond.' : 'Aucun message pour l’instant. Cherchez quelqu’un pour lui écrire.' }}
                    </p>
                @endif
            </div>
        </x-filament::section>

        {{-- Le fil ouvert --}}
        <x-filament::section :compact="true">
            @if ($ouverte)
                <x-slot name="heading">
                    {{ $ouverte->user?->displayName() ?? 'Compte supprimé' }}
                </x-slot>
                <x-slot name="description">
                    {{ $ouverte->user?->role?->label() }} · {{ $ouverte->user?->email }}
                </x-slot>

                <div x-data
                     x-init="$el.scrollTop = $el.scrollHeight;
                             new MutationObserver(() => $el.scrollTop = $el.scrollHeight)
                                 .observe($el, { childList: true, subtree: true })"
                     style="max-height: 55vh; overflow-y: auto; display: flex; flex-direction: column; gap: .6rem; padding: .25rem;">
                    @forelse ($this->thread($ouverte) as $m)
                        <div wire:key="msg-{{ $m->id }}" style="display: flex; justify-content: {{ $m->from_staff ? 'flex-end' : 'flex-start' }};">
                            <div style="max-width: 75%; padding: .55rem .85rem; border-radius: 1rem; font-size: .875rem; line-height: 1.5;
                                        background: {{ $m->from_staff ? 'rgb(204,63,69)' : 'rgba(127,127,127,.15)' }};
                                        color: {{ $m->from_staff ? '#ffffff' : 'inherit' }};">
                                @if ($m->from_staff)
                                    <div style="font-size: .7rem; font-weight: 600; opacity: .7;">{{ $m->sender?->displayName() ?? 'Équipe' }}</div>
                                @endif
                                <div style="white-space: pre-line; overflow-wrap: anywhere;">{{ $m->body }}</div>
                                <div style="font-size: .68rem; opacity: .6; margin-top: .2rem;">
                                    {{ $m->created_at->format('d/m H:i') }}
                                    @if ($m->from_staff) · {{ $m->read_at ? 'lu' : 'non lu' }} @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <p style="font-size: .875rem; opacity: .6;">Pas encore de message. Écrivez le premier.</p>
                    @endforelse
                </div>

                <form wire:submit="sendReply" style="margin-top: 1rem; display: flex; gap: .5rem; align-items: flex-end;">
                    <div style="flex: 1;">
                        <x-filament::input.wrapper :valid="! $errors->has('reply')">
                            <textarea wire:model="reply" rows="2" maxlength="{{ \App\Models\Message::MAX_LENGTH }}"
                                      x-data
                                      x-on:keydown.enter="if (! $event.shiftKey) { $event.preventDefault(); $el.form.requestSubmit(); }"
                                      placeholder="Votre réponse… (Entrée pour envoyer, Maj+Entrée pour aller à la ligne)"
                                      style="width: 100%; resize: vertical; background: transparent; border: 0; padding: .5rem .75rem; font-size: .875rem; outline: none;"></textarea>
                        </x-filament::input.wrapper>
                        @error('reply')
                            <p style="color: rgb(239,68,68); font-size: .8rem; margin-top: .25rem;">{{ $message }}</p>
                        @enderror
                    </div>
                    <x-filament::button type="submit" wire:target="sendReply">Envoyer</x-filament::button>
                </form>
            @else
                <p style="font-size: .875rem; opacity: .6; padding: 2rem 0; text-align: center;">
                    Choisissez une conversation, ou cherchez un utilisateur pour lui écrire.
                </p>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
