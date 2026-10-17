<?php

namespace Database\Seeders;

use App\Models\TrainingLesson;
use Illuminate\Database\Seeder;

/**
 * Le parcours de formation par défaut.
 *
 * Idempotent : relancé, il met à jour les textes sans toucher aux vidéos
 * déposées dans le back-office ni à la progression des clippeurs. Les
 * identifiants (slug) ne changent jamais : ce sont eux qui relient une leçon
 * à sa vidéo enregistrée et à la progression de chacun.
 *
 * Écrit au tutoiement : c'est ainsi que parlent les clippeurs entre eux.
 *
 *     php artisan db:seed --class=TrainingLessonSeeder --force
 */
class TrainingLessonSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->lessons() as $position => $lesson) {
            $model = TrainingLesson::firstOrNew(['slug' => $lesson['slug']]);

            $model->fill([
                ...$lesson,
                'position' => ($position + 1) * 10,
            ]);

            // Une leçon dépubliée exprès dans le back-office le reste.
            if (! $model->exists) {
                $model->is_published = true;
            }

            $model->save();
        }
    }

    /** @return list<array{slug: string, title: string, summary: string, duration_minutes: int, body: string}> */
    protected function lessons(): array
    {
        return [
            [
                'slug' => 'bienvenue',
                'title' => 'Le principe : tes vues = ton argent',
                'summary' => 'Comment un clip devient de l’argent, en 30 secondes.',
                'duration_minutes' => 1,
                'body' => <<<'MD'
## En une phrase

Un artiste, un label ou un streamer **finance une campagne**. Tu publies des clips sur **ton propre compte**, et chaque tranche de **1 000 vues** te rapporte le cachet affiché sur la campagne.

## Ce qu'il faut retenir

- **Zéro abonné minimum.** On paie la vue, pas la taille de ta commu. Un compte tout neuf peut exploser.
- **Le budget part au premier arrivé.** Chaque campagne affiche ce qu'il lui reste en direct : quand c'est vide, plus rien n'est payé. Ne traîne pas.
- **Tes vues sont relevées toutes seules.** Rien à déclarer à la main.
- **Tu retires dès 20 €**, sur PayPal ou par virement.

> Le parcours : lier ton compte → choisir une campagne → poster → soumettre le lien → suivre tes vues → retirer ton argent.
MD,
            ],
            [
                'slug' => 'lier-ses-comptes',
                'title' => 'Lie ton compte TikTok',
                'summary' => 'Pas de compte lié, pas de vues comptées. Pas de vues, pas d’argent.',
                'duration_minutes' => 1,
                'body' => <<<'MD'
## Pourquoi c'est obligatoire

On lit tes vues directement chez TikTok, YouTube ou Instagram, avec ton accord. C'est ce qui garantit que tu es payé sur du vrai, et qu'un tricheur ne l'est pas.

## Comment faire

1. Ouvre **Mes comptes** dans le menu.
2. Clique sur **Lier un compte** et choisis ta plateforme.
3. Connecte-toi et **accepte toutes les permissions**.

> Si tu refuses l'accès aux statistiques, la liaison est bloquée : sans lui, tes vues ne remonteraient jamais.

## Un bandeau te demande de te reconnecter ?

Les plateformes font expirer les accès de temps en temps. Tant que le bandeau est là, **tes vues ne sont plus relevées** : reconnecte-toi tout de suite depuis **Mes comptes**.
MD,
            ],
            [
                'slug' => 'choisir-une-campagne',
                'title' => 'Choisis ta campagne',
                'summary' => 'Cachet, budget, règles : ce qu’il faut regarder avant de te lancer.',
                'duration_minutes' => 2,
                'body' => <<<'MD'
## Ce que dit une fiche de campagne

- **Le cachet pour 1 000 vues**, plateforme par plateforme.
- **Le budget restant.** Une campagne grisée est épuisée : tu peux la regarder, plus la rejoindre.
- **Les règles du jeu** : vues minimum par clip, nombre de clips à faire valider, plafond par clip. Lis-les avant de poster, pas après.
- **Le brief** : ce que le créateur veut (son imposé, hashtags, durée, ambiance).
- **La matière première** : sons, rushes, images à télécharger.
- **La prime de lancement**, s'il y en a une : un bonus garanti pour les premiers clippeurs. Le nombre de places restantes est affiché.

## Rejoindre

Clique sur **Rejoindre la campagne**. Certaines campagnes valident les participations à la main : attends le feu vert avant de poster.

> Une campagne avec un minimum de clips ne paie rien tant que tu ne l'as pas atteint. Mais rien n'est perdu : dès que ton dernier clip requis est validé, tout l'arriéré tombe d'un coup.
MD,
            ],
            [
                'slug' => 'formats-qui-marchent',
                'title' => 'Les formats qui cartonnent',
                'summary' => 'L’accroche, 6 formats à tester, la régularité : la recette des clips qui tournent.',
                'duration_minutes' => 1,
                'body' => <<<'MD'
## Tout se joue en 3 secondes

Le spectateur décide de rester ou de swiper quasi instantanément. Démarre direct sur le moment fort, une phrase choc ou une question. Jamais d'intro.

## Les 6 formats à tester

- **Extrait** : le meilleur passage d'un live, d'un son ou d'une interview, coupé serré.
- **Paroles** : le texte qui s'affiche en rythme sur le son.
- **Speed up / Slowed** : le son accéléré ou ralenti, ultra partagé sur la musique.
- **Edit** : montage nerveux calé sur le son (film, sport, animé…).
- **Réaction** : ta réaction face caméra.
- **Storytelling** : une histoire racontée avec le son en fond.

## Teste plusieurs formats sur la même campagne

Un même son peut faire 3 000 vues en extrait et 9 000 en paroles. Essaie, compare, garde ce qui marche.

## Le vrai secret : poster régulièrement

Les meilleurs clippeurs ne misent pas tout sur un clip viral. Ils postent souvent, et les vues s'empilent.

> Respecte toujours le brief : un clip hors sujet peut être refusé, et un clip refusé ne rapporte rien.
MD,
            ],
            [
                'slug' => 'echo-studio',
                'title' => 'Prépare tes clips avec Echo Studio',
                'summary' => 'Récupère un passage et monte-le en vertical sans quitter le site.',
                'duration_minutes' => 2,
                'body' => <<<'MD'
## À quoi ça sert

**Echo Studio**, dans le menu, t'aide à récupérer le passage d'une vidéo et à le recadrer au format vertical. Tes fichiers restent quelques jours dans ton espace, puis sont supprimés : télécharge ce que tu veux garder.

## Le bon réflexe

1. Pars de la matière première fournie par la campagne quand elle existe : c'est du contenu officiel, autorisé.
2. Fais court : au-delà de 60 secondes, tu perds ton public.
3. Poste depuis **ton** compte lié, puis reviens soumettre le lien.
MD,
            ],
            [
                'slug' => 'soumettre-un-clip',
                'title' => 'Poste et soumets ton clip',
                'summary' => 'Publier, coller le lien, et comprendre la validation.',
                'duration_minutes' => 2,
                'body' => <<<'MD'
## Les étapes

1. Poste la vidéo sur ton compte **lié**. Un clip posté depuis un autre compte ne sera jamais payé.
2. Copie le lien de la publication.
3. Sur la campagne, clique sur **Soumettre un clip** et colle le lien.

## Et après ?

Au premier relevé, on vérifie automatiquement ton clip : qu'il vient bien de ton compte, sa durée, les hashtags demandés. Si tout est bon, il est **validé tout seul** ; au moindre doute, un modérateur le regarde.

Tu suis chaque clip dans **Mes clips** : en attente, validé ou refusé (avec la raison).

> Un même clip ne peut être soumis qu'une fois, même avec un autre lien.
MD,
            ],
            [
                'slug' => 'suivre-ses-gains',
                'title' => 'Suis tes vues et tes gains',
                'summary' => 'Pourquoi tes vues ne bougent pas à la seconde, et où voir ton argent.',
                'duration_minutes' => 1,
                'body' => <<<'MD'
## Le rythme des relevés

Aucune plateforme ne nous envoie les vues en direct : on va les chercher.

- **Moins de 48 h** : toutes les 30 minutes.
- **De 2 à 7 jours** : toutes les 3 heures.
- **Jusqu'à 30 jours** : une fois par jour.

Pressé ? Le bouton **Actualiser les vues**, sur la page d'un clip, lance un relevé (une fois par quart d'heure).

## Où voir ton argent

- **Revenus** : ton solde dispo et l'historique de tes retraits.
- **Classement** : ta place cette semaine, sur la saison, depuis toujours et par catégorie.
- **Succès** : les étapes que tu as passées.
MD,
            ],
            [
                'slug' => 'retirer-ses-gains',
                'title' => 'Retire ton argent',
                'summary' => 'Moyen de paiement, retrait minimum et statut légal.',
                'duration_minutes' => 2,
                'body' => <<<'MD'
## Avant ton premier retrait

Indique où tu veux être payé dans **Moyen de paiement** : une adresse PayPal ou un IBAN.

## Demander un retrait

Dans **Revenus**, clique sur **Demander un retrait**. Le minimum est de **20 €**. Les petits montants sont validés automatiquement si ton compte est clean ; les autres passent par l'équipe avant le virement.

## Le statut légal

Gagner de l'argent régulièrement en ligne, ça se déclare. Sans statut, tes retraits sont **plafonnés sur l'année**. Tu es auto-entrepreneur (ou en société) ? Déclare ton **SIRET** dans **Moyen de paiement** : une fois vérifié par l'équipe, le plafond saute.

> Le compteur de la page **Moyen de paiement** te montre où tu en es par rapport au plafond de l'année.
MD,
            ],
            [
                'slug' => 'les-regles',
                'title' => 'Les 3 règles d’or',
                'summary' => 'Ce qui te fait perdre tes gains, et ton compte.',
                'duration_minutes' => 1,
                'body' => <<<'MD'
## Interdit, sans exception

1. **Jamais de vues achetées ni de bots.** Les pics anormaux sont détectés.
2. **Ne soumets que tes propres vidéos.** Seules les publications de ton compte lié sont payées.
3. **Ne reposte pas de contenu non autorisé** quand la campagne fournit le sien.

## En cas de fraude

Le clip est **invalidé** : ce qu'il t'avait rapporté est repris et rendu à la campagne. En cas de récidive, le compte est banni et les retraits bloqués.

> Un doute sur ce qui est permis ? Écris à l'équipe dans **Messages** avant de poster. On répond.
MD,
            ],
        ];
    }
}
