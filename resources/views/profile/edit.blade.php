<x-app-layout>
    <x-slot name="header">
        <h1 class="font-display text-2xl font-bold text-ink-50">Mon profil</h1>
        <p class="mt-1 text-ink-300">Identité, numéro, mot de passe et suppression du compte.</p>
    </x-slot>

    <div class="mx-auto max-w-3xl space-y-4 px-4 py-5 sm:space-y-6 sm:px-6 sm:py-8 lg:px-8">
        <div class="card p-6 sm:p-8">
            @include('profile.partials.update-avatar-form')
        </div>

        <div class="card p-6 sm:p-8">
            @include('profile.partials.update-profile-information-form')
        </div>

        {{-- Proposé seulement si un SMS peut partir, ou si la vérification est
             exigée. Sinon le bloc invite à une action qui échoue : avec une
             cinquantaine de clippeurs, chacun l'essaierait, et chacun
             conclurait que le site est cassé. --}}
        @if (app(App\Services\Sms\SmsManager::class)->isConfigured()
            || config('clipping.onboarding.require_phone_verification'))
            <div class="card p-6 sm:p-8">
                @include('profile.partials.phone-summary')
            </div>
        @endif

        <div class="card p-6 sm:p-8">
            @include('profile.partials.update-password-form')
        </div>

        <div class="card border-red-500/40 p-6 sm:p-8">
            @include('profile.partials.delete-user-form')
        </div>
    </div>
</x-app-layout>
