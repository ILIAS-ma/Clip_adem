@php use App\Support\PhoneNumber; @endphp

{{--
    Le numéro, dans le profil.

    La page de vérification existait sans qu'aucun lien n'y mène : elle
    n'était atteignable qu'en tapant l'adresse. Un dispositif qu'on ne peut
    pas trouver ne protège rien.
--}}
<div class="flex flex-wrap items-start justify-between gap-4">
    <div class="min-w-0">
        <h2 class="font-display text-lg font-bold text-ink-50">Numéro de téléphone</h2>

        @if (auth()->user()->hasVerifiedPhone())
            <p class="mt-1.5 flex flex-wrap items-center gap-2">
                <span class="chip-ok">Vérifié</span>
                <span class="font-semibold tabular text-ink-100">
                    {{ PhoneNumber::masque(auth()->user()->phone) }}
                </span>
            </p>
            <p class="mt-2 text-sm leading-relaxed text-ink-400">
                Il ne sert qu'à confirmer que ce compte est bien tenu par une personne :
                ni démarchage, ni transmission à un tiers.
            </p>
        @else
            <p class="mt-1.5">
                <span class="chip-wait">Non vérifié</span>
            </p>
            <p class="mt-2 text-sm leading-relaxed text-ink-400">
                Une adresse e-mail jetable s'obtient en dix secondes, une ligne téléphonique non.
                C'est ce qui rend un bannissement dissuasif, et ce qui protège vos gains d'un
                compte qui en créerait dix.
            </p>
        @endif
    </div>

    <a href="{{ route('phone.verify') }}" wire:navigate class="btn-ghost shrink-0">
        {{ auth()->user()->hasVerifiedPhone() ? 'Changer' : 'Vérifier mon numéro' }}
    </a>
</div>
