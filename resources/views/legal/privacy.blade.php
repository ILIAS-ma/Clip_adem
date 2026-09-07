@php
    // Les vérificateurs de TikTok lisent réellement cette page : elle doit
    // nommer les données récupérées via leur API et dire ce qu'on en fait.
    // Une politique générique fait rejeter la demande.
@endphp

<x-legal.layout title="Politique de confidentialité" :updated-at="$updatedAt" :contact-email="$contactEmail">

    <section>
        <h2>Ce que fait {{ config('app.name') }}</h2>
        <p>
            {{ config('app.name') }} met en relation des créateurs, qui financent des campagnes de
            promotion, et des clippeurs, qui publient des vidéos sur les réseaux sociaux et sont
            rémunérés selon les vues réellement générées.
        </p>
        <p>
            Ce document décrit les données que nous collectons, pourquoi, combien de temps nous les
            conservons, et comment vous en reprenez le contrôle.
        </p>
    </section>

    <section>
        <h2>Données que vous nous donnez</h2>
        <table>
            <thead>
                <tr><th>Donnée</th><th>Pourquoi</th></tr>
            </thead>
            <tbody>
                <tr><td>Nom, prénom, adresse e-mail</td><td>Créer et sécuriser votre compte</td></tr>
                <tr><td>Pseudo public</td><td>Vous identifier auprès des créateurs sans révéler votre identité civile</td></tr>
                <tr><td>Pays de résidence</td><td>Déterminer les moyens de versement disponibles</td></tr>
                <tr><td>Adresse PayPal <em>ou</em> IBAN, BIC, titulaire</td><td>Vous verser vos gains</td></tr>
            </tbody>
        </table>
        <p>
            <strong>Votre IBAN est chiffré</strong> dans notre base de données. Seuls ses quatre
            derniers chiffres restent lisibles, afin d'afficher « •••• 1234 » et de rapprocher un
            virement sans jamais déchiffrer le reste.
        </p>
    </section>

    <section>
        <h2>Données issues de vos comptes de réseaux sociaux</h2>
        <p>
            Lorsque vous liez un compte TikTok, YouTube ou Instagram, vous nous autorisez à lire
            certaines informations via l'API officielle de la plateforme. Nous ne demandons que le
            strict nécessaire au fonctionnement du service.
        </p>

        <h3>TikTok</h3>
        <table>
            <thead>
                <tr><th>Autorisation</th><th>Ce que nous en faisons</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>user.info.basic</code></td>
                    <td>
                        Identifiant public, nom affiché et photo de profil : pour rattacher vos
                        publications à votre compte et vérifier qu'une vidéo soumise vous appartient
                        bien.
                    </td>
                </tr>
                <tr>
                    <td><code>user.info.stats</code></td>
                    <td>
                        Nombre d'abonnés : sert uniquement à repérer des statistiques
                        invraisemblables, par exemple des vues sans commune mesure avec l'audience
                        du compte.
                    </td>
                </tr>
                <tr>
                    <td><code>video.list</code></td>
                    <td>
                        Nombre de vues, likes, commentaires, partages, légende et durée des vidéos
                        que <strong>vous</strong> soumettez à une campagne. C'est le compteur de vues
                        qui détermine votre rémunération.
                    </td>
                </tr>
            </tbody>
        </table>

        <p>
            Nous ne lisons <strong>jamais</strong> vos messages privés, votre liste d'abonnés, ni les
            vidéos que vous n'avez pas soumises à une campagne. Nous ne publions rien en votre nom :
            vous publiez vous-même sur TikTok, puis vous collez le lien chez nous.
        </p>
        <p>
            Vous pouvez délier un compte à tout moment depuis « Mes comptes ». Le jeton d'accès est
            alors supprimé et nous cessons immédiatement d'interroger la plateforme. Vous pouvez
            aussi révoquer l'accès directement depuis les réglages de votre compte TikTok.
        </p>
    </section>

    <section>
        <h2>Données que nous produisons</h2>
        <ul>
            <li>Relevés horodatés du nombre de vues de chaque clip soumis.</li>
            <li>Montants crédités, retraits demandés et versements effectués.</li>
            <li>Décisions de modération et leurs motifs, conservés en cas de litige sur un paiement.</li>
            <li>Journaux techniques : adresse IP, date et navigateur lors de la connexion.</li>
        </ul>
    </section>

    <section>
        <h2>Qui voit quoi</h2>
        <p>
            Les créateurs voient le <strong>pseudo</strong> des clippeurs et les performances des
            clips de leurs campagnes. Ils n'ont jamais accès à votre identité civile, à votre
            adresse e-mail, ni à vos coordonnées bancaires.
        </p>
        <p>
            Nous ne vendons aucune donnée. Nous ne les transmettons qu'aux prestataires
            indispensables au service : hébergeur, service d'envoi d'e-mails, et prestataire de
            paiement lorsque vous demandez un retrait.
        </p>
    </section>

    <section>
        <h2>Durée de conservation</h2>
        <ul>
            <li><strong>Compte et données de profil</strong> : tant que le compte existe.</li>
            <li><strong>Pièces comptables</strong> (crédits, retraits, versements) : dix ans, conformément aux obligations légales.</li>
            <li><strong>Jetons d'accès aux réseaux sociaux</strong> : supprimés dès que vous déliez le compte.</li>
            <li><strong>Journaux techniques</strong> : douze mois.</li>
        </ul>
    </section>

    <section>
        <h2>Vos droits</h2>
        <p>
            Conformément au RGPD, vous pouvez demander l'accès à vos données, leur rectification,
            leur effacement, leur portabilité, ou vous opposer à leur traitement. Écrivez-nous à
            <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a> : nous répondons sous
            trente jours.
        </p>
        <p>
            La suppression d'un compte entraîne l'effacement de vos données de profil et de vos
            coordonnées bancaires. Les écritures comptables des sommes déjà versées sont conservées
            le temps imposé par la loi, sous une forme dissociée de votre profil public.
        </p>
    </section>

    <section>
        <h2>Sécurité</h2>
        <ul>
            <li>Connexion chiffrée (HTTPS) sur l'ensemble du site.</li>
            <li>Mots de passe stockés sous forme d'empreinte, jamais en clair.</li>
            <li>IBAN chiffré en base.</li>
            <li>Double authentification obligatoire pour tout le personnel administratif.</li>
        </ul>
    </section>

    <section>
        <h2>Modifications</h2>
        <p>
            Toute évolution de cette politique sera annoncée par e-mail aux personnes concernées
            avant son entrée en vigueur.
        </p>
    </section>
</x-legal.layout>
