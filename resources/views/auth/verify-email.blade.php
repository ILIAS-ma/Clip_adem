<x-guest-layout>
    <div class="mb-8">
        <span class="chip-wait">Étape 1 sur 2</span>
        <h2 class="mt-4 font-display text-3xl font-bold text-ink-900">Confirmez votre e-mail</h2>
        <p class="mt-3 text-ink-500">
            Un lien vient d'être envoyé à <strong class="text-ink-800">{{ auth()->user()->email }}</strong>.
            Ouvrez-le pour activer votre compte.
        </p>
    </div>

    @if (session('status') === 'verification-link-sent')
        <div class="alert-ok mb-6">
            Nouveau lien envoyé. Vérifiez votre boîte de réception.
        </div>
    @endif

    <div class="card p-6">
        <h3 class="text-sm font-semibold text-ink-800">Vous ne trouvez pas l'e-mail ?</h3>
        <ul class="mt-3 space-y-2 text-sm text-ink-500">
            <li class="flex gap-2"><span class="text-ink-300">1.</span> Regardez dans vos spams ou vos promotions.</li>
            <li class="flex gap-2"><span class="text-ink-300">2.</span> Vérifiez l'adresse saisie à l'inscription.</li>
            <li class="flex gap-2"><span class="text-ink-300">3.</span> Redemandez un lien ci-dessous.</li>
        </ul>
    </div>

    <div class="mt-6 flex flex-wrap items-center gap-3">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-primary-button>Renvoyer le lien</x-primary-button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn-ghost">Se déconnecter</button>
        </form>
    </div>

    @if (app()->environment('local'))
        {{-- En développement, les e-mails partent vers Mailpit et non vers une
             vraie boîte : sans ce rappel, l'écran est un cul-de-sac. --}}
        <div class="alert-warn mt-8">
            <p class="font-semibold">Environnement de développement</p>
            <p class="mt-1">
                Les e-mails n'arrivent pas dans une vraie boîte : ils sont capturés par Mailpit.
                Ouvrez <a href="http://127.0.0.1:8025" target="_blank" rel="noopener"
                          class="font-semibold underline underline-offset-2">127.0.0.1:8025</a>
                pour lire le message et cliquer sur le lien.
            </p>
        </div>
    @endif
</x-guest-layout>
