<?php

namespace Database\Seeders;

use App\Models\TrainingLesson;
use Illuminate\Database\Seeder;

/**
 * Le parcours de formation par défaut.
 *
 * Idempotent : relancé, il met à jour les textes sans toucher aux vidéos
 * déposées dans le back-office ni à la progression des clippeurs.
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
                'title' => 'Le principe : payé à la vue',
                'summary' => 'Comment un clip devient de l’argent, en deux minutes.',
                'duration_minutes' => 2,
                'body' => <<<'MD'
## Le modèle en une phrase

Un artiste, un label ou un streamer finance une **campagne**. Vous publiez des clips sur **votre propre compte**, et chaque tranche de **1 000 vues** vous rapporte le cachet annoncé sur la campagne.

## Ce qu'il faut retenir

- **Aucun minimum d'abonnés.** C'est la vue qui est payée, pas la taille de votre communauté. Un compte tout neuf peut percer.
- **Le budget part au premier arrivé.** Chaque campagne affiche son budget restant en temps réel : quand il est épuisé, plus rien n'est crédité. Ne tardez pas.
- **Les vues sont relevées automatiquement** sur vos publications. Vous n'avez rien à déclarer à la main.
- **Vous retirez vos gains** dès 20 €, sur PayPal ou par virement.

> Le parcours complet : lier son compte → rejoindre une campagne → publier → soumettre le lien → suivre ses vues → retirer. Chaque leçon qui suit couvre une étape.
MD,
            ],
            [
                'slug' => 'lier-ses-comptes',
                'title' => 'Lier ses comptes TikTok, YouTube et Instagram',
                'summary' => 'Sans compte lié, aucune vue ne remonte, donc aucun paiement.',
                'duration_minutes' => 2,
                'body' => <<<'MD'
## Pourquoi c'est obligatoire

Nous lisons vos vues directement chez la plateforme, avec votre autorisation. C'est ce qui garantit que vous êtes payé sur du réel, et qu'un tricheur ne peut pas l'être.

## Comment faire

1. Ouvrez **Mes comptes** dans le menu.
2. Cliquez sur **Lier un compte**, puis choisissez la plateforme.
3. Connectez-vous chez TikTok, YouTube ou Instagram et **acceptez toutes les permissions** demandées.

> Refuser l'accès aux statistiques empêche la liaison : sans lui, vos vues ne remonteraient jamais et vous ne seriez pas payé.

## Si un bandeau vous demande de reconnecter

Les plateformes font expirer les autorisations de temps en temps. Tant que le bandeau est affiché, **vos vues ne sont plus relevées** : reconnectez-vous tout de suite depuis **Mes comptes**.
MD,
            ],
            [
                'slug' => 'choisir-une-campagne',
                'title' => 'Choisir et rejoindre une campagne',
                'summary' => 'Lire une fiche de campagne avant de se lancer.',
                'duration_minutes' => 3,
                'body' => <<<'MD'
## Ce que dit une fiche de campagne

- **Le cachet pour 1 000 vues**, plateforme par plateforme.
- **Le budget restant.** Une campagne grisée est épuisée : vous pouvez la consulter, plus la rejoindre.
- **Les garde-fous** : vues minimum par clip, nombre minimum de clips à faire valider, plafond par clip. Lisez-les avant de publier, pas après.
- **Le brief** : ce que le créateur attend (son imposé, hashtags, durée, ton).
- **La matière première** : sons, rushes, images à télécharger.

## Rejoindre

Cliquez sur **Rejoindre la campagne**. Certaines campagnes demandent une validation manuelle de votre participation : attendez-la avant de publier.

> Une campagne avec un minimum de clips ne paie rien tant que le compte n'est pas atteint. Rien n'est perdu : dès que le dernier clip requis est validé, tout l'arriéré est crédité d'un coup.
MD,
            ],
            [
                'slug' => 'formats-qui-marchent',
                'title' => 'Les formats qui font des vues',
                'summary' => 'Accroche, formats, régularité : ce qui sépare un clip à 500 vues d’un clip à 500 000.',
                'duration_minutes' => 4,
                'body' => <<<'MD'
## Les 3 premières secondes décident de tout

Le spectateur choisit de rester ou de swiper presque immédiatement. Commencez par le moment fort, une phrase choc ou une question, jamais par une intro.

## Les formats à tester

- **Extrait** : le meilleur passage d'un live, d'un son ou d'une interview, découpé serré.
- **Paroles (lyrics)** : le texte qui s'affiche en rythme sur le son.
- **Speed up / slowed** : le son accéléré ou ralenti, très partagé sur la musique.
- **Edit** : montage dynamique sur le son (images, film, sport, animé).
- **Réaction** : votre réaction face caméra au son ou au moment.
- **Storytelling** : une histoire racontée sur le son en fond.

## La règle d'or : le volume

Les meilleurs clippeurs ne parient pas sur un seul clip viral. Ils **postent régulièrement**, testent plusieurs formats sur la même campagne et gardent ce qui marche.

> Respectez toujours le brief : un clip hors sujet peut être refusé, et un clip refusé ne rapporte rien.
MD,
            ],
            [
                'slug' => 'echo-studio',
                'title' => 'Préparer ses clips avec Echo Studio',
                'summary' => 'Récupérer un passage et le monter en vertical sans quitter le site.',
                'duration_minutes' => 3,
                'body' => <<<'MD'
## À quoi sert Echo Studio

**Echo Studio**, dans le menu, vous aide à récupérer le passage d'une vidéo et à le recadrer au format vertical des réseaux. Les fichiers restent dans votre espace pendant quelques jours, puis sont purgés : téléchargez ce que vous voulez garder.

## Le bon réflexe

1. Partez de la matière première fournie par la campagne quand elle existe : c'est le contenu officiel, autorisé.
2. Coupez court : au-delà de 60 secondes, le clip perd son public.
3. Publiez depuis **votre** compte lié, puis revenez soumettre le lien.
MD,
            ],
            [
                'slug' => 'soumettre-un-clip',
                'title' => 'Soumettre son clip',
                'summary' => 'Publier, coller le lien, et comprendre la validation.',
                'duration_minutes' => 3,
                'body' => <<<'MD'
## Les étapes

1. Publiez la vidéo sur votre compte **lié**. Un clip publié depuis un autre compte ne sera jamais payé.
2. Copiez le lien de la publication.
3. Sur la campagne, cliquez sur **Soumettre un clip** et collez le lien.

## Ce qui se passe ensuite

Au premier relevé, nous vérifions automatiquement le clip : qu'il vient bien de votre compte, la durée, les hashtags demandés. Un clip conforme sur toute la ligne est **validé automatiquement** ; au moindre doute, un modérateur le regarde.

Vous suivez l'état de chaque clip dans **Mes clips** : en attente, validé ou refusé (avec le motif).

> Un même clip ne peut être soumis qu'une fois, même avec un lien différent.
MD,
            ],
            [
                'slug' => 'suivre-ses-gains',
                'title' => 'Suivre ses vues et ses gains',
                'summary' => 'Pourquoi les vues ne bougent pas à la seconde, et comment lire ses revenus.',
                'duration_minutes' => 2,
                'body' => <<<'MD'
## Le rythme des relevés

Aucune plateforme ne nous envoie les vues en direct : nous allons les chercher.

- **Moins de 48 h** : toutes les 30 minutes.
- **De 2 à 7 jours** : toutes les 3 heures.
- **Jusqu'à 30 jours** : une fois par jour.

Pressé ? Le bouton **Actualiser les vues**, sur la page d'un clip, déclenche un relevé (une fois tous les quart d'heure).

## Où voir son argent

- **Revenus** : votre solde disponible et l'historique de vos retraits.
- **Classement** : votre place cette semaine, depuis toujours, par catégorie et sur la saison en cours.
- **Succès** : les étapes franchies.
MD,
            ],
            [
                'slug' => 'retirer-ses-gains',
                'title' => 'Retirer ses gains',
                'summary' => 'Moyen de paiement, retrait minimum et statut légal.',
                'duration_minutes' => 3,
                'body' => <<<'MD'
## Avant le premier retrait

Renseignez où vous voulez être payé dans **Moyen de paiement** : une adresse PayPal ou un IBAN.

## Demander un retrait

Dans **Revenus**, cliquez sur **Demander un retrait**. Le minimum est de **20 €**. Les petits montants d'un compte sans incident sont validés automatiquement ; les autres passent par un administrateur avant le virement.

## Le statut légal

Gagner de l'argent régulièrement en ligne est une activité déclarable. Sans statut, vos retraits sont **plafonnés sur l'année**. Si vous êtes auto-entrepreneur (ou en société), déclarez votre **SIRET** dans **Moyen de paiement** : une fois vérifié par l'équipe, le plafond est levé.

> Le compteur de la page **Moyen de paiement** vous montre en permanence où vous en êtes par rapport au plafond annuel.
MD,
            ],
            [
                'slug' => 'les-regles',
                'title' => 'Les règles à ne jamais enfreindre',
                'summary' => 'Ce qui fait perdre ses gains, voire son compte.',
                'duration_minutes' => 2,
                'body' => <<<'MD'
## Interdit, sans exception

- **Acheter des vues** ou utiliser des bots. Les pics anormaux sont détectés.
- **Soumettre la vidéo de quelqu'un d'autre.** Seules les publications de votre compte lié sont payées.
- **Republier du contenu non autorisé** quand la campagne fournit le sien.

## Ce qui arrive en cas de fraude

Le clip est **invalidé** : ce qu'il avait rapporté est repris et rendu à la campagne. En cas de récidive, le compte est banni et les retraits bloqués.

> En cas de doute sur ce qui est permis, écrivez à l'équipe depuis **Messages** avant de publier. On répond.
MD,
            ],
        ];
    }
}
