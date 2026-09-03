{{-- Le logo fourni sert de favicon dès qu'il est déposé dans public/images. --}}
@if (is_file(public_path('images/logo.png')))
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/logo.png') }}">
@else
    <link rel="icon" href="data:image/svg+xml,{{ rawurlencode(
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">'
        .'<circle cx="16" cy="16" r="15" fill="#080908" stroke="#93CE2E" stroke-width="1.5"/>'
        .'<rect x="8" y="12" width="3" height="8" rx="1.5" fill="#93CE2E"/>'
        .'<rect x="14" y="8" width="3" height="16" rx="1.5" fill="#93CE2E"/>'
        .'<rect x="20" y="13" width="3" height="6" rx="1.5" fill="#93CE2E"/>'
        .'</svg>'
    ) }}">
@endif
