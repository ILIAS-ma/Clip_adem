{{-- Le logo et le nom, comme sur le site. Styles en ligne : le back-office
     n'a pas de thème compilé à part. --}}
<span style="display: inline-flex; align-items: center; gap: .6rem;">
    <img src="{{ asset('images/logo.png') }}?v={{ @filemtime(public_path('images/logo.png')) }}"
         alt="" style="height: 2rem; width: 2rem;">
    <span style="font-weight: 700; font-size: 1.125rem; letter-spacing: -.01em;">{{ config('app.name') }}</span>
</span>
