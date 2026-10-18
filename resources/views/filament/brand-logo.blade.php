{{-- Le logo seul, comme sur le site : l'image suffit, sans le nom à côté.
     Le nom reste dans l'attribut alt pour les lecteurs d'écran.
     Styles en ligne : le back-office n'a pas de thème compilé à part. --}}
<img src="{{ asset('images/logo.png') }}?v={{ @filemtime(public_path('images/logo.png')) }}"
     alt="{{ config('app.name') }}" style="height: 2rem; width: 2rem;">
