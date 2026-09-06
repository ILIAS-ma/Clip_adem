@props(['code', 'title', 'message', 'showHome' => true])

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $code }} — {{ $title }}</title>

    <meta name="theme-color" content="#101210">
    <meta name="robots" content="noindex">

    <x-favicon />

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700|bricolage-grotesque:600,700|fraunces:600,700&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-ink-900">
    <main class="relative isolate flex min-h-screen flex-col items-center justify-center overflow-hidden px-6 text-center">
        <div aria-hidden="true" class="pointer-events-none absolute -left-24 -top-32 h-[34rem] w-[34rem] rounded-full bg-brand-500/15 blur-[110px]"></div>
        <div aria-hidden="true" class="pointer-events-none absolute right-[-10rem] bottom-0 h-[28rem] w-[28rem] rounded-full bg-brand-300/10 blur-[100px]"></div>

        <a href="{{ route('home') }}" class="relative mb-10">
            <x-brand-mark />
        </a>

        <p class="relative font-serif text-7xl font-semibold text-brand-500 sm:text-8xl">{{ $code }}</p>

        <h1 class="relative mt-4 font-display text-2xl font-bold text-ink-50 sm:text-3xl">{{ $title }}</h1>

        <p class="relative mx-auto mt-3 max-w-md text-ink-300">{{ $message }}</p>

        @if ($showHome)
            <a href="{{ route('home') }}" class="btn-brand relative mt-8 px-6 py-3 text-base">
                Retour à l'accueil
            </a>
        @endif
    </main>
</body>
</html>
