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
        <div class="border-b border-ink-700 px-6 py-4">
            <h2 class="font-display text-lg font-bold text-ink-50">Mes gains</h2>
            <p class="mt-0.5 text-sm text-ink-400">Ce que vous avez généré sur toutes vos campagnes.</p>
        </div>

        <dl class="grid grid-cols-3 divide-x divide-ink-700 border-b border-ink-700">
            <div class="min-w-0 px-4 py-4 sm:px-6">
                <dt class="text-xs text-ink-400">Total généré</dt>
                <dd class="mt-1 whitespace-nowrap font-display text-xl font-bold tabular text-brand-400 sm:text-2xl">{{ Money::euros($prizes['total_cents']) }}</dd>
            </div>
            <div class="min-w-0 px-4 py-4 sm:px-6">
                <dt class="text-xs text-ink-400">Déjà versé</dt>
                <dd class="mt-1 whitespace-nowrap font-display text-xl font-bold tabular text-ink-50 sm:text-2xl">{{ Money::euros($prizes['credited_cents']) }}</dd>
            </div>
            <div class="min-w-0 px-4 py-4 sm:px-6">
                <dt class="text-xs text-ink-400">Prix à recevoir</dt>
                <dd class="mt-1 whitespace-nowrap font-display text-xl font-bold tabular text-ink-50 sm:text-2xl">{{ Money::euros($prizes['pending_cents']) }}</dd>
            </div>
        </dl>

        @if ($prizes['rows'] !== [])
            <ul class="divide-y divide-ink-700">
                @foreach ($prizes['rows'] as $row)
                    <li class="flex items-center justify-between gap-4 px-6 py-3.5">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-ink-100">{{ $row['campaign'] }}</p>
                            <p class="mt-0.5 text-xs text-ink-400">{{ $row['board'] }} · {{ $row['rank'] === 1 ? '1re' : $row['rank'].'e' }} place</p>
                        </div>
                        <div class="flex shrink-0 items-center gap-3">
                            <span class="font-display text-base font-bold tabular text-ink-50">{{ $row['reward'] }}</span>
                            <span @class([
                                'chip-ok' => $row['state'] === 'awarded',
                                'chip-wait' => $row['state'] === 'pending',
                                'chip-neutral' => $row['state'] === 'live',
                            ])>{{ match ($row['state']) {
                                'awarded' => 'Versé',
                                'pending' => 'À verser',
                                default => 'Provisoire',
                            } }}</span>
                        </div>
                    </li>
                @endforeach
            </ul>
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
