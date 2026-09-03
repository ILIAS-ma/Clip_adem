@php
    // Un contrôle désactivé « le temps de voir l'interface » finit en
    // production si rien ne le rappelle à l'écran.
    $suspended = collect([
        'require_email_verification' => "vérification d'e-mail",
        'require_complete_profile' => 'profil complet obligatoire',
        'require_admin_2fa' => '2FA administrateur',
    ])->reject(fn ($label, $key) => config("clipping.onboarding.{$key}"));
@endphp

@if ($suspended->isNotEmpty() && ! app()->environment('production'))
    <div class="bg-brand-500">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-x-3 gap-y-1 px-4 py-2 text-sm text-ink-950 sm:px-6 lg:px-8">
            <span class="font-semibold">Contrôles suspendus</span>
            <span class="text-ink-900">{{ $suspended->values()->join(', ', ' et ') }}</span>
            <span class="ms-auto text-xs text-ink-800">
                Réactivez-les dans <code class="font-mono">.env</code> avant toute mise en ligne.
            </span>
        </div>
    </div>
@endif
