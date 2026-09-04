@props(['asset'])

@php
    use App\Enums\AssetKind;

    $url = $asset->url();
@endphp

{{--
    Une pièce du brief. L'aperçu est joué sur place quand le navigateur sait le
    faire : envoyer un clippeur télécharger un fichier de 80 Mo pour découvrir
    que ce n'était pas le bon son, c'est le perdre.
--}}
<div class="rounded-xl border border-ink-700 p-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="flex flex-wrap items-center gap-2">
                <span class="chip bg-ink-800 text-ink-300">{{ $asset->kind->label() }}</span>
                <span class="font-semibold text-ink-50">{{ $asset->label }}</span>
                @if ($asset->is_required)
                    <span class="chip-danger">Imposé</span>
                @endif
            </p>

            @if ($asset->description)
                <p class="mt-1.5 text-sm leading-relaxed text-ink-300">{{ $asset->description }}</p>
            @endif
        </div>

        @if ($url)
            <a href="{{ $url }}" target="_blank" rel="noopener"
               @if ($asset->isHosted()) download @endif
               class="btn-ghost shrink-0">
                {{ $asset->isHosted() ? 'Télécharger' : 'Ouvrir' }}
                @if ($asset->humanSize())
                    <span class="ml-1 text-xs text-ink-400 tabular">{{ $asset->humanSize() }}</span>
                @endif
            </a>
        @endif
    </div>

    @if ($asset->isPreviewable())
        <div class="mt-4">
            @switch ($asset->kind)
                @case (AssetKind::Audio)
                    <audio controls preload="none" class="w-full" src="{{ $url }}"></audio>
                    @break

                @case (AssetKind::Video)
                    <video controls preload="metadata" class="max-h-80 w-full rounded-lg bg-ink-900"
                           src="{{ $url }}"></video>
                    @break

                @case (AssetKind::Image)
                    <img src="{{ $url }}" alt="{{ $asset->label }}"
                         loading="lazy" class="max-h-80 rounded-lg" />
                    @break
            @endswitch
        </div>
    @endif
</div>
