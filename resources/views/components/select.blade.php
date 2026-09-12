@props(['id' => null])

{{--
    Remplace le <select> natif : sur macOS (Safari et Chrome), la liste
    ouverte est dessinée par le système d'exploitation et aucun CSS ne peut
    la teinter — seul le bouton fermé est stylable. Le <select> d'origine
    reste présent mais caché du rendu visuel et des lecteurs d'écran
    (`aria-hidden`, non focusable) : il ne sert plus qu'à porter `name` /
    `wire:model` pour que les formulaires et Livewire continuent de
    fonctionner sans rien changer côté serveur. Le bouton et la liste
    personnalisée ci-dessous sont les seuls éléments réellement interactifs.
--}}
<div x-data="customSelect" x-init="init()" class="relative">
    <select {{ $attributes->except('class')->merge(['id' => $id]) }} x-ref="native" aria-hidden="true" tabindex="-1"
            class="pointer-events-none absolute h-0 w-0 opacity-0" @change="syncFromNative()">
        {{ $slot }}
    </select>

    <button type="button" @click="toggle()" @keydown.down.prevent="openAndMove(1)"
            @keydown.up.prevent="openAndMove(-1)" @keydown.escape="open = false"
            :aria-expanded="open" aria-haspopup="listbox"
            {{--
                `.field` ne fixe ni bordure ni padding : sur un <select>, ils
                venaient du plugin @tailwindcss/forms, qui ne cible pas les
                <button>. Sans les rajouter ici à la main, le déclencheur
                rendait plus petit qu'avant.
            --}}
            {{ $attributes->only('class')->merge(['class' => 'field mt-1.5 flex items-center justify-between gap-2 border px-3 py-2 text-left']) }}>
        <span x-text="currentLabel" class="truncate"></span>
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="none" stroke="currentColor"
             stroke-width="1.5" class="h-4 w-4 shrink-0 text-ink-400 transition-transform"
             :class="open && 'rotate-180'">
            <path stroke-linecap="round" stroke-linejoin="round" d="m6 8 4 4 4-4" />
        </svg>
    </button>

    {{--
        w-max plutôt que w-full : un bouton dimensionné à son contenu
        ("Tous") est souvent plus étroit que la plus longue option de la
        liste ("En attente de validation"), qui repartirait sinon sur
        quatre lignes au lieu de tenir sur une seule.
    --}}
    <ul x-show="open" x-cloak x-transition.origin.top @click.outside="open = false"
        role="listbox" tabindex="-1"
        class="absolute z-20 mt-1 max-h-64 w-max min-w-full max-w-xs overflow-auto rounded-xl border border-ink-700 bg-ink-900 p-1 shadow-lifted">
        <template x-for="(opt, index) in options" :key="opt.value">
            <li role="option" :aria-selected="opt.value === selectedValue"
                @click="select(opt.value)" @mouseenter="highlighted = index"
                class="cursor-pointer whitespace-nowrap rounded-lg px-3 py-2 text-sm"
                :class="{
                    'bg-brand-500/15 text-brand-300': opt.value === selectedValue,
                    'bg-ink-800 text-ink-50': opt.value !== selectedValue && highlighted === index,
                    'text-ink-100': opt.value !== selectedValue && highlighted !== index,
                }"
                x-text="opt.label"></li>
        </template>
    </ul>
</div>
