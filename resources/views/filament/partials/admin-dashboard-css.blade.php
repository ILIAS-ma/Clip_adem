{{--
    Styles communs aux widgets et pages du back-office.

    Le back-office n'a pas de thème compilé : une classe Tailwind absente de la feuille de
    Filament ne s'applique tout simplement pas (c'est pourquoi les anciens tableaux n'avaient
    aucun espacement). Tout ce qui sert aux widgets est donc défini ici, une fois.
--}}
<style>
    /* Tuiles : chiffres cliquables */
    .ad-tiles { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: .75rem; }
    .ad-tile { display: block; border: 1px solid rgba(128,128,128,.25); background: rgba(128,128,128,.07); border-radius: .85rem; padding: .85rem 1rem; color: inherit; text-decoration: none; }
    a.ad-tile:hover { border-color: var(--primary-500); }
    .ad-tile .n { font-size: 1.7rem; font-weight: 800; line-height: 1.1; font-variant-numeric: tabular-nums; }
    .ad-tile .l { font-size: .8rem; opacity: .75; margin-top: .25rem; }
    .ad-tile.is-warn { border-color: var(--warning-500); background: color-mix(in oklab, var(--warning-500) 10%, transparent); }
    .ad-tile.is-danger { border-color: var(--danger-500); background: color-mix(in oklab, var(--danger-500) 10%, transparent); }
    .ad-tile.is-calm .n { opacity: .45; }

    /* Tableaux : espacés sur ordinateur, une carte par ligne sur téléphone */
    .ad-table { width: 100%; border-collapse: collapse; font-size: .875rem; }
    .ad-table th { text-align: left; font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; opacity: .6; font-weight: 600; padding: .45rem .75rem; }
    .ad-table td { padding: .65rem .75rem; border-top: 1px solid rgba(128,128,128,.18); vertical-align: middle; }
    .ad-table .r { text-align: right; font-variant-numeric: tabular-nums; }
    .ad-table th:first-child, .ad-table td:first-child { padding-left: 0; }
    .ad-table th:last-child, .ad-table td:last-child { padding-right: 0; }
    .ad-wrap { overflow-x: auto; }

    .ad-link { color: var(--primary-500); font-weight: 600; text-decoration: none; }
    .ad-link:hover { text-decoration: underline; }
    .ad-bad { color: var(--danger-500); font-weight: 600; font-size: .75rem; margin-left: .35rem; }
    .ad-danger { color: var(--danger-500); font-weight: 600; }
    .ad-warn { color: var(--warning-500); font-weight: 600; }
    .ad-ok { color: var(--success-500); }
    .ad-muted { opacity: .65; }
    .ad-empty { margin: 0; font-size: .875rem; opacity: .7; }

    /* Cartes de campagne */
    .ad-camps { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: .85rem; }
    .ad-camp { border: 1px solid rgba(128,128,128,.25); background: rgba(128,128,128,.07); border-radius: .85rem; padding: 1rem; display: grid; gap: .75rem; align-content: start; }
    .ad-camp .t { font-weight: 700; font-size: 1rem; word-break: break-word; }
    .ad-camp .s { font-size: .78rem; opacity: .7; }
    .ad-camp .figs { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .5rem; }
    .ad-camp .figs b { display: block; font-size: 1.15rem; font-variant-numeric: tabular-nums; }
    .ad-camp .figs span { font-size: .72rem; opacity: .65; }
    .ad-camp ol { margin: 0; padding: 0; list-style: none; display: grid; gap: .3rem; font-size: .85rem; }
    .ad-camp li { display: flex; justify-content: space-between; gap: .5rem; }
    .ad-camp .links { display: flex; flex-wrap: wrap; gap: .75rem; font-size: .85rem; }
    .ad-pill { display: inline-block; padding: .1rem .55rem; border-radius: 999px; font-size: .72rem; font-weight: 700; background: color-mix(in oklab, var(--warning-500) 18%, transparent); color: var(--warning-500); }

    @media (max-width: 640px) {
        /* Chiffres clés de Filament : en demi-largeur, « 8 301,00 € » se coupait sur trois lignes. */
        .fi-wi-stats-overview-stat-value { font-size: 1.3rem !important; line-height: 1.2 !important; white-space: nowrap; }
        .fi-wi-stats-overview-stat { padding: .85rem !important; }

        .ad-tiles { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .ad-camps { grid-template-columns: minmax(0, 1fr); }

        .ad-table thead { display: none; }
        .ad-table, .ad-table tbody, .ad-table tr, .ad-table td { display: block; width: 100%; }
        .ad-table tr { border: 1px solid rgba(128,128,128,.25); background: rgba(128,128,128,.07); border-radius: .75rem; padding: .6rem .85rem; margin-bottom: .6rem; }
        .ad-table td { border: 0; padding: .2rem 0 !important; display: flex; justify-content: space-between; gap: 1rem; text-align: right; word-break: break-word; }
        .ad-table td::before { content: attr(data-label); opacity: .6; font-size: .78rem; text-align: left; flex-shrink: 0; }
        .ad-table td.main { display: block; text-align: left; font-weight: 700; font-size: .95rem; padding-bottom: .35rem !important; }
        .ad-table td.main::before { content: none; }
    }
</style>
