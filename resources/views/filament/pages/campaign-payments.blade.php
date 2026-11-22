<x-filament-panels::page>
    {{--
        Mise en page en styles en ligne : le back-office n'a pas de thème compilé à part,
        une classe Tailwind absente de la feuille de Filament ne s'appliquerait pas.
    --}}
    @php
        $campaign = $this->campaign();
        $rows = $this->rows();
        $finished = $this->isFinished();
        $card = 'border:1px solid rgba(128,128,128,.25);background:rgba(128,128,128,.07);border-radius:.85rem;';
        $muted = 'color:var(--gray-500);';
        $euros = fn (int $cents) => number_format($cents / 100, 2, ',', ' ').' €';
        $toPay = $rows->where('due', '>', 0);
    @endphp

    <div style="display:grid;gap:1.1rem;">

        <div style="max-width:460px;">
            <label class="text-sm text-gray-500 dark:text-gray-400" for="pay-campaign">Campagne</label>
            <x-filament::input.wrapper>
                <x-filament::input.select id="pay-campaign" wire:model.live="campaignId">
                    @foreach ($this->campaigns() as $id => $title)
                        <option value="{{ $id }}">{{ $title }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>

        @if ($campaign && ! $finished)
            <div style="border:1px solid var(--warning-500);background:color-mix(in oklab, var(--warning-500) 10%, transparent);border-radius:.85rem;padding:.9rem 1rem;">
                Cette campagne n'est pas terminée : les gains bougent encore. Paie une fois qu'elle est finie.
            </div>
        @endif

        @if ($campaign && $this->prizesPending())
            <div style="border:1px solid var(--warning-500);background:color-mix(in oklab, var(--warning-500) 10%, transparent);border-radius:.85rem;padding:.9rem 1rem;">
                Les prix du classement ne sont pas encore attribués : ils ne sont pas dans les gains ci-dessous.
                <a href="{{ \App\Filament\Resources\Campaigns\CampaignResource::getUrl('edit', ['record' => $campaign]) }}" style="text-decoration:underline;font-weight:600;">
                    Ouvre la campagne et clique sur « Clôturer et attribuer »
                </a>, puis reviens ici.
            </div>
        @endif

        @if ($campaign)
            <div style="{{ $card }}padding:1rem;display:grid;gap:.75rem;">
                <div style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:center;justify-content:space-between;">
                    <div style="font-weight:700;">
                        À payer : {{ $toPay->count() }} clippeur{{ $toPay->count() > 1 ? 's' : '' }}
                        ({{ $euros((int) $toPay->sum('due')) }})
                    </div>
                    @if ($finished && $toPay->isNotEmpty())
                        <x-filament::button wire:click="payAll" color="success"
                            wire:confirm="Marquer {{ $toPay->count() }} clippeur(s) comme payés ? Tu dois avoir envoyé l'argent avant : leur cagnotte baisse tout de suite et ils sont prévenus.">
                            Tout marquer payé ({{ $euros((int) $toPay->sum('due')) }})
                        </x-filament::button>
                    @endif
                </div>

                <div style="max-width:420px;">
                    <label class="text-sm text-gray-500 dark:text-gray-400" for="pay-ref">Référence du virement (facultatif)</label>
                    <x-filament::input.wrapper>
                        <x-filament::input id="pay-ref" type="text" wire:model.blur="reference" placeholder="ex. VIR-2026-11-22" />
                    </x-filament::input.wrapper>
                </div>

                @if ($rows->isEmpty())
                    <p class="text-sm text-gray-500 dark:text-gray-400" style="margin:0;">Aucun clippeur n'a de gains sur cette campagne.</p>
                @else
                    <div style="overflow-x:auto;">
                        <table style="width:100%;border-collapse:collapse;font-size:.875rem;">
                            <thead>
                                <tr style="text-align:left;{{ $muted }}font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;">
                                    <th style="padding:.4rem .6rem .4rem 0;">#</th>
                                    <th style="padding:.4rem .6rem;">Clippeur</th>
                                    <th style="padding:.4rem .6rem;text-align:right;">Gagné</th>
                                    <th style="padding:.4rem .6rem;text-align:right;">Déjà payé</th>
                                    <th style="padding:.4rem .6rem;text-align:right;">Reste</th>
                                    <th style="padding:.4rem .6rem;">Où payer</th>
                                    <th style="padding:.4rem 0 .4rem .6rem;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rows as $row)
                                    <tr wire:key="pay-{{ $row->user_id }}" style="border-top:1px solid rgba(128,128,128,.18);">
                                        <td style="padding:.55rem .6rem .55rem 0;font-weight:700;font-variant-numeric:tabular-nums;">{{ $row->rank }}</td>
                                        <td style="padding:.55rem .6rem;font-weight:600;">
                                            {{ $row->name }}
                                            @if ($row->banned)<span style="color:var(--danger-500);font-weight:600;"> · banni</span>@endif
                                        </td>
                                        <td style="padding:.55rem .6rem;text-align:right;font-variant-numeric:tabular-nums;">{{ $euros($row->earned) }}</td>
                                        <td style="padding:.55rem .6rem;text-align:right;font-variant-numeric:tabular-nums;{{ $muted }}">{{ $euros($row->paid) }}</td>
                                        <td style="padding:.55rem .6rem;text-align:right;font-variant-numeric:tabular-nums;font-weight:700;">{{ $euros($row->due) }}</td>
                                        <td style="padding:.55rem .6rem;">
                                            @if ($row->method)
                                                <span style="{{ $muted }}">{{ $row->method }}</span>
                                                <span style="word-break:break-all;">{{ $row->destination }}</span>
                                            @else
                                                <span style="color:var(--danger-500);">Aucun moyen de paiement</span>
                                            @endif
                                        </td>
                                        <td style="padding:.55rem 0 .55rem .6rem;text-align:right;white-space:nowrap;">
                                            @if ($row->due === 0)
                                                <span style="color:var(--success-600);font-weight:700;">✓ Payé</span>
                                            @elseif ($finished)
                                                <x-filament::button wire:click="pay({{ $row->user_id }})" size="xs" color="success"
                                                    wire:confirm="Marquer {{ $row->name }} comme payé ({{ $euros($row->due) }}) ? Tu dois avoir envoyé l'argent avant.">
                                                    Marquer payé
                                                </x-filament::button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-filament-panels::page>
