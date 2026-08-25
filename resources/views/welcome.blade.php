<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} — Gagnez de l'argent avec vos clips</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 font-sans antialiased">
    <div class="flex min-h-screen flex-col">

        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto flex max-w-5xl items-center justify-between px-6 py-4">
                <span class="text-lg font-bold tracking-tight text-gray-900">{{ config('app.name') }}</span>

                <nav class="flex items-center gap-4 text-sm">
                    <a href="{{ route('login') }}" class="font-medium text-gray-600 hover:text-gray-900">Connexion</a>
                    <a href="{{ route('register') }}"
                       class="rounded-md bg-emerald-600 px-4 py-2 font-medium text-white transition hover:bg-emerald-700">
                        Devenir clippeur
                    </a>
                </nav>
            </div>
        </header>

        <main class="mx-auto w-full max-w-5xl flex-1 px-6 py-16">
            <div class="max-w-2xl">
                <h1 class="text-4xl font-bold tracking-tight text-gray-900 sm:text-5xl">
                    Vos clips font la promo,<br>vos vues font le reste.
                </h1>
                <p class="mt-6 text-lg leading-relaxed text-gray-600">
                    Rejoignez une campagne, publiez votre clip sur TikTok, YouTube ou Instagram, et soyez rémunéré
                    selon les vues générées — jusqu'à épuisement du budget de la campagne.
                </p>
                <a href="{{ route('register') }}"
                   class="mt-8 inline-flex items-center rounded-md bg-emerald-600 px-6 py-3 font-medium text-white transition hover:bg-emerald-700">
                    Créer un compte
                </a>
            </div>

            <div class="mt-16 grid gap-6 sm:grid-cols-3">
                @foreach ([
                    ['1', 'Choisissez une campagne', 'Chaque campagne affiche son cachet pour 1000 vues et le budget qu\'il lui reste.'],
                    ['2', 'Publiez et soumettez', 'Suivez le brief, publiez sur votre compte, puis collez le lien de la publication.'],
                    ['3', 'Suivez vos gains', 'Vos vues sont relevées automatiquement et créditées jusqu\'à épuisement du budget.'],
                ] as [$step, $title, $text])
                    <div class="rounded-lg bg-white p-6 shadow-sm">
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100 text-sm font-bold text-emerald-700">
                            {{ $step }}
                        </span>
                        <h2 class="mt-4 font-semibold text-gray-900">{{ $title }}</h2>
                        <p class="mt-2 text-sm leading-relaxed text-gray-600">{{ $text }}</p>
                    </div>
                @endforeach
            </div>

            {{-- Dit d'emblée ce qui surprend le plus les nouveaux clippeurs :
                 le budget est fini, et il part au premier arrivé. --}}
            <p class="mt-10 max-w-2xl rounded-lg border-l-4 border-amber-400 bg-amber-50 p-4 text-sm text-amber-800">
                Le budget d'une campagne est limité et se consomme au fil des vues, premier arrivé premier servi.
                Quand il atteint zéro, la campagne se ferme : les vues continuent d'être comptées mais ne sont plus
                rémunérées.
            </p>
        </main>

        <footer class="border-t border-gray-200 bg-white">
            <div class="mx-auto max-w-5xl px-6 py-6 text-sm text-gray-500">
                {{ config('app.name') }}
            </div>
        </footer>
    </div>
</body>
</html>
