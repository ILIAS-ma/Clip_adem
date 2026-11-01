@php
    use App\Support\Money;

    $minimumCents = (int) config('clipping.payouts.minimum_cents');
    $canWithdraw = ($balanceCents ?? 0) >= $minimumCents && $minimumCents > 0;
@endphp

{{--
    Les gains d'un clippeur sur les classements, du provisoire au versé.

    Un prix n'entre dans le solde qu'une fois attribué par l'équipe. Entre la fin de la
    campagne et ce moment, le clippeur voit ici ce qu'il a gagné, au lieu d'un solde à zéro
    qui laisse croire qu'il n'a rien eu.
--}}
@if ($prizes['rows'] !== [] || $prizes['total_cents'] > 0)
    <div class="card">
        {{-- Le solde, les gains validés et le reste sont déjà dans les tuiles au-dessus : ici, seulement les prix. --}}
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-ink-700 px-6 py-4">
            <div>
                <h2 class="font-display text-lg font-bold text-ink-50">Prix de classement</h2>
                <p class="mt-0.5 text-sm text-ink-400">Ce que vous avez gagné sur vos campagnes en classement.</p>
            </div>
            @if ($prizes['pending_cents'] > 0)
                <span class="chip-wait">{{ Money::euros($prizes['pending_cents']) }} à recevoir</span>
            @endif
        </div>

        @if ($prizes['rows'] !== [])
            @include('clipper.partials.prize-rows')
        @endif

        @if ($prizes['live_cents'] > 0)
            <p class="border-t border-ink-700 px-6 py-3 text-xs text-ink-400">
                Classements en cours : {{ Money::euros($prizes['live_cents']) }} si l'un d'eux s'arrêtait maintenant. La place peut encore changer.
            </p>
        @endif

        @if ($prizes['claimable'] !== [] || $canWithdraw)
            <div class="flex flex-wrap items-center gap-3 border-t border-ink-700 px-6 py-4">
                @foreach ($prizes['claimable'] as $claim)
                    @if ($claim['claimed'])
                        <span class="chip-wait">Demande envoyée · {{ $claim['title'] }}</span>
                    @else
                        <form method="POST" action="{{ route('prizes.claim', $claim['campaign_id']) }}">
                            @csrf
                            <button type="submit" class="btn-brand">Réclamer mes prix · {{ $claim['title'] }}</button>
                        </form>
                    @endif
                @endforeach

                @if ($canWithdraw)
                    <a href="{{ route('earnings.index') }}" wire:navigate class="btn-brand">Demander un retrait</a>
                @endif
            </div>

            @if ($prizes['claimable'] !== [])
                <p class="px-6 pb-4 text-xs text-ink-400">
                    Les prix sont versés par l'équipe après vérification du classement. « Réclamer » la prévient que vous les attendez.
                </p>
            @endif
        @endif
    </div>
@endif
