<x-studio-layout title="Mode créateur">
    {{-- Tout l'écran est le composant : le rail en fait partie, parce que
         chacune de ses lignes déclenche une recherche. --}}
    @livewire('clip-discovery', ['simule' => $simule, 'utilisable' => $utilisable])
</x-studio-layout>
