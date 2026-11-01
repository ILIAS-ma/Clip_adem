<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Administrateurs</x-slot>
        <x-slot name="description">{{ $admins->count() }} compte{{ $admins->count() > 1 ? 's' : '' }} avec accès au back-office, chacun aussi clippeur</x-slot>

        <div class="ad-wrap">
            <table class="ad-table">
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>E-mail</th>
                        <th>Rôle</th>
                        <th>Double authentification</th>
                        <th class="r">Clips</th>
                        <th class="r">Depuis</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($admins as $admin)
                        <tr>
                            <td class="main">
                                {{ $admin->displayName() }}
                                @if ($admin->is(auth()->user()))<span class="ad-muted" style="font-size:.75rem;font-weight:400;"> (vous)</span>@endif
                                @if ($admin->is_banned)<span class="ad-bad">banni</span>@endif
                            </td>
                            <td class="ad-muted" data-label="E-mail">{{ $admin->email }}</td>
                            <td data-label="Rôle">{{ $admin->role->label() }}</td>
                            {{-- Un admin sans double authentification est le point faible des paiements. --}}
                            <td class="{{ filled($admin->app_authentication_secret) ? 'ad-ok' : 'ad-warn' }}" data-label="Double authentification">
                                {{ filled($admin->app_authentication_secret) ? 'Activée' : 'À configurer' }}
                            </td>
                            <td class="r" data-label="Clips">{{ $admin->clips_count }}</td>
                            <td class="r ad-muted" data-label="Depuis">{{ $admin->created_at?->format('d/m/Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
