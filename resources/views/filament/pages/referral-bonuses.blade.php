<x-filament-panels::page>
    @php
        $toPay = $this->toPay();
        $paid = $this->paid();
        $amount = $this->amountCents();
        $card = 'border:1px solid rgba(128,128,128,.25);background:rgba(128,128,128,.07);border-radius:.85rem;';
        $muted = 'color:var(--gray-500);';
        $euros = fn (int $cents) => number_format($cents / 100, 2, ',', ' ').' €';
    @endphp

    <div style="display:grid;gap:1.1rem;">

        <div style="max-width:320px;">
            <label class="text-sm text-gray-500 dark:text-gray-400" for="bonus-month">Mois</label>
            <x-filament::input.wrapper>
                <x-filament::input.select id="bonus-month" wire:model.live="month">
                    @foreach ($this->months() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>

        <p class="text-sm text-gray-500 dark:text-gray-400" style="margin:0;">
            Un parrain touche <strong>{{ $euros($amount) }}</strong> pour chaque filleul qui poste au moins
            <strong>{{ $this->minClips() }} clips validés</strong> dans le mois. La prime n'est versée qu'une seule fois par filleul,
            et elle arrive directement sur le solde du parrain. À faire le 25 de chaque mois.
        </p>

        {{-- À verser --}}
        <div style="{{ $card }}padding:1rem;display:grid;gap:.75rem;">
            <div style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:center;justify-content:space-between;">
                <div style="font-weight:700;">
                    À verser : {{ $toPay->count() }} prime{{ $toPay->count() > 1 ? 's' : '' }}
                    ({{ $euros($toPay->count() * $amount) }})
                </div>
                @if ($toPay->isNotEmpty())
                    <x-filament::button wire:click="pay" color="success"
                        wire:confirm="Verser {{ $toPay->count() }} prime(s) de {{ $euros($amount) }} aux parrains ? Les montants arrivent tout de suite sur leurs soldes.">
                        Verser {{ $euros($toPay->count() * $amount) }}
                    </x-filament::button>
                @endif
            </div>

            @if ($toPay->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400" style="margin:0;">Aucun filleul n'a atteint le seuil ce mois-ci, ou toutes les primes sont déjà versées.</p>
            @else
                <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;font-size:.875rem;">
                        <thead>
                            <tr style="text-align:left;{{ $muted }}font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;">
                                <th style="padding:.4rem .6rem .4rem 0;">Parrain</th>
                                <th style="padding:.4rem .6rem;">Filleul</th>
                                <th style="padding:.4rem 0 .4rem .6rem;text-align:right;">Clips du mois</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($toPay as $row)
                                <tr style="border-top:1px solid rgba(128,128,128,.18);">
                                    <td style="padding:.5rem .6rem .5rem 0;font-weight:600;">{{ $row->referrer }}</td>
                                    <td style="padding:.5rem .6rem;">{{ $row->referred }}</td>
                                    <td style="padding:.5rem 0 .5rem .6rem;text-align:right;font-variant-numeric:tabular-nums;">{{ $row->clips }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Déjà versées --}}
        <div style="{{ $card }}padding:1rem;display:grid;gap:.6rem;">
            <div style="font-weight:700;">Déjà versées ce mois-ci : {{ $paid->count() }}</div>
            @if ($paid->isNotEmpty())
                <ul style="list-style:none;margin:0;padding:0;display:grid;gap:.35rem;font-size:.875rem;">
                    @foreach ($paid as $line)
                        <li style="display:flex;justify-content:space-between;gap:.5rem;flex-wrap:wrap;">
                            <span><strong>{{ $line->referrer?->displayName() ?? 'Parrain supprimé' }}</strong> · pour {{ $line->referred?->displayName() ?? 'filleul supprimé' }}</span>
                            <span style="{{ $muted }}">{{ $euros($line->amount_cents) }} · {{ $line->created_at->format('d/m H:i') }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</x-filament-panels::page>
