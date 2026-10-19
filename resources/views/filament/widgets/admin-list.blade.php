<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Administrateurs</x-slot>
        <x-slot name="description">{{ $admins->count() }} compte{{ $admins->count() > 1 ? 's' : '' }} avec accès au back-office, chacun aussi clippeur</x-slot>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        <th class="py-2 text-left font-medium">Nom</th>
                        <th class="py-2 text-left font-medium">E-mail</th>
                        <th class="py-2 text-left font-medium">Rôle</th>
                        <th class="py-2 text-left font-medium">Double authentification</th>
                        <th class="py-2 text-right font-medium">Clips</th>
                        <th class="py-2 text-right font-medium">Depuis</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-ink-700">
                    @foreach ($admins as $admin)
                        <tr>
                            <td class="py-2 pr-4 font-medium">
                                {{ $admin->displayName() }}
                                @if ($admin->is(auth()->user()))
                                    <span class="ml-1 text-xs text-gray-500 dark:text-gray-400">(vous)</span>
                                @endif
                                @if ($admin->is_banned)
                                    <span class="ml-1 text-xs font-medium text-danger-600 dark:text-danger-400">banni</span>
                                @endif
                            </td>
                            <td class="py-2 pr-4 text-gray-500 dark:text-gray-400">{{ $admin->email }}</td>
                            <td class="py-2 pr-4">{{ $admin->role->label() }}</td>
                            {{-- Un admin sans double authentification est le point faible des paiements. --}}
                            <td @class([
                                'py-2 pr-4',
                                'text-success-600 dark:text-success-400' => filled($admin->app_authentication_secret),
                                'font-medium text-warning-600 dark:text-warning-400' => blank($admin->app_authentication_secret),
                            ])>
                                {{ filled($admin->app_authentication_secret) ? 'Activée' : 'À configurer' }}
                            </td>
                            <td class="py-2 text-right tabular-nums">{{ $admin->clips_count }}</td>
                            <td class="py-2 text-right tabular-nums text-gray-500 dark:text-gray-400">{{ $admin->created_at?->format('d/m/Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
