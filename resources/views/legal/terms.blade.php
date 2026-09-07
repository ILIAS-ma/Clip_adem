<x-legal.layout title="Conditions d'utilisation" :updated-at="$updatedAt" :contact-email="$contactEmail">

    <section>
        <h2>Objet</h2>
        <p>
            {{ config('app.name') }} met en relation des <strong>créateurs</strong>, qui financent
            des campagnes de promotion, et des <strong>clippeurs</strong>, qui publient des vidéos
            sur les réseaux sociaux et sont rémunérés selon les vues réellement générées.
        </p>
        <p>
            L'utilisation du service vaut acceptation des présentes conditions.
        </p>
    </section>

    <section>
        <h2>Compte</h2>
        <ul>
            <li>Vous devez avoir <strong>18 ans révolus</strong>, ou l'accord de votre représentant légal.</li>
            <li>Un compte par personne. Les comptes multiples destinés à contourner un plafond ou un bannissement sont supprimés.</li>
            <li>Vous êtes responsable de la confidentialité de vos identifiants.</li>
        </ul>
    </section>

    <section>
        <h2>Comment fonctionne la rémunération</h2>
        <p>
            Chaque campagne annonce un <strong>budget total</strong> et un <strong>tarif pour
            1 000 vues</strong>. Vos gains sont calculés à partir des vues relevées via l'API
            officielle de la plateforme concernée, jamais déclarées à la main.
        </p>

        <h3>Le budget part au premier arrivé</h3>
        <p>
            Le budget d'une campagne est <strong>fini</strong>. Il est consommé au fur et à mesure
            des vues créditées, dans l'ordre où elles sont relevées. Lorsqu'il est épuisé, la
            campagne cesse de rémunérer, <strong>même si votre clip continue de faire des vues</strong>.
            Le budget restant est affiché en permanence sur la page de chaque campagne : consultez-le
            avant de publier.
        </p>

        <h3>Plafonds</h3>
        <p>
            Une campagne peut fixer un plafond par clip et un plafond par clippeur. Ils sont annoncés
            sur la page de la campagne avant que vous ne la rejoigniez.
        </p>

        <h3>Délai de comptage</h3>
        <p>
            Les vues ne sont pas comptées en temps réel : aucune plateforme ne le permet. Les clips
            récents sont relevés toutes les trente minutes, puis moins souvent à mesure que leur
            audience se stabilise. Un bouton permet de forcer un relevé depuis la page de votre clip.
        </p>
    </section>

    <section>
        <h2>Ce qui est interdit</h2>
        <ul>
            <li><strong>Acheter des vues, des likes ou de l'engagement</strong>, sous quelque forme que ce soit.</li>
            <li>Utiliser des robots, des fermes à clics ou tout dispositif gonflant artificiellement les compteurs.</li>
            <li>Soumettre une publication <strong>qui ne vous appartient pas</strong>. La propriété est vérifiée automatiquement à chaque relevé : une vidéo émise par un autre compte que celui que vous avez lié n'est jamais rémunérée.</li>
            <li>Supprimer ou passer en privé une publication déjà rémunérée.</li>
            <li>Publier un contenu illicite, haineux, ou contraire aux règles de la plateforme de diffusion.</li>
            <li>Ignorer les consignes obligatoires du brief (son imposé, hashtags, mentions).</li>
        </ul>
        <p>
            Un manquement entraîne l'<strong>invalidation du clip</strong> : les sommes créditées sont
            reprises et rendues au budget de la campagne. Les manquements répétés entraînent la
            suspension du compte et la perte du solde non versé.
        </p>
    </section>

    <section>
        <h2>Retraits</h2>
        <ul>
            <li>Vous choisissez d'être payé par <strong>PayPal</strong> ou par <strong>virement bancaire</strong>.</li>
            <li>Un montant minimum s'applique, indiqué sur la page « Mes revenus ».</li>
            <li>Un retrait demandé immobilise la somme : elle n'est plus disponible tant que le versement n'est pas traité ou annulé.</li>
            <li>Les virements PayPal partent automatiquement après validation ; les virements bancaires sous 1 à 3 jours ouvrés.</li>
            <li>Un versement rejeté par la banque ou par PayPal remet la somme à votre solde. Vérifiez vos coordonnées avant de demander un retrait.</li>
        </ul>
        <p>
            <strong>Votre statut fiscal vous appartient.</strong> Les sommes versées le sont en tant
            que prestataire indépendant : il vous revient de les déclarer selon la réglementation de
            votre pays de résidence.
        </p>
    </section>

    <section>
        <h2>Parrainage</h2>
        <p>
            Vous pouvez inviter d'autres clippeurs avec votre lien personnel et percevoir une
            commission sur leurs gains. Cette commission est payée par {{ config('app.name') }} :
            elle n'est <strong>ni prélevée sur les gains du filleul</strong>, ni sur le budget de la
            campagne. Une commission versée sur des vues finalement invalidées est reprise.
        </p>
        <p>
            L'auto-parrainage et la création de comptes de complaisance entraînent la perte des
            commissions et la suspension du compte.
        </p>
    </section>

    <section>
        <h2>Contenus et droits</h2>
        <p>
            Vous restez propriétaire de vos vidéos. En rejoignant une campagne, vous autorisez le
            créateur et {{ config('app.name') }} à en citer les statistiques et à afficher un lien
            vers la publication, à des fins de suivi de campagne.
        </p>
        <p>
            Les éléments fournis dans un brief — sons, rushes, visuels — restent la propriété du
            créateur et ne peuvent servir qu'à la campagne concernée.
        </p>
    </section>

    <section>
        <h2>Modération</h2>
        <p>
            Chaque clip peut être contrôlé. Les vérifications automatiques produisent un rapport ;
            la décision d'invalider reste humaine et motivée. Un refus vous est notifié par e-mail
            avec son motif. Vous pouvez le contester en répondant à cet e-mail.
        </p>
    </section>

    <section>
        <h2>Disponibilité et responsabilité</h2>
        <p>
            Le service dépend d'API tierces (TikTok, YouTube, Instagram, PayPal). Une interruption
            de leur côté peut retarder un relevé de vues ou un versement.
            {{ config('app.name') }} ne saurait être tenu responsable d'un manque à gagner résultant
            d'une indisponibilité indépendante de sa volonté.
        </p>
        <p>
            Les vues créditées le sont sur la foi des chiffres communiqués par la plateforme de
            diffusion. Une correction ultérieure de leur part peut entraîner un ajustement.
        </p>
    </section>

    <section>
        <h2>Résiliation</h2>
        <p>
            Vous pouvez supprimer votre compte à tout moment. Le solde acquis et non contesté reste
            versable. En cas de fraude avérée, le compte est suspendu et le solde non versé est
            retenu, les sommes correspondantes étant rendues aux budgets des campagnes concernées.
        </p>
    </section>

    <section>
        <h2>Droit applicable</h2>
        <p>
            Les présentes conditions sont soumises au droit français. À défaut d'accord amiable, tout
            litige relève des juridictions compétentes.
        </p>
        <p>
            Pour toute question :
            <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>.
        </p>
    </section>
</x-legal.layout>
