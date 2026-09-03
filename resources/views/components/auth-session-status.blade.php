@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'alert-ok']) }}>
        {{ $status }}
    </div>
@endif
