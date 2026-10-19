<p class="mt-3 rounded-xl bg-ink-900 p-3 text-sm text-ink-300">
    @if ($opensAt)
        La campagne ouvre le {{ $campaign->starts_at->format('d/m/Y à H\hi') }}. Préparez votre clip : vous pourrez le soumettre dès l'ouverture.
    @else
        Cette campagne n'accepte plus de nouveaux clips. Ceux déjà soumis continuent d'être suivis.
    @endif
</p>
