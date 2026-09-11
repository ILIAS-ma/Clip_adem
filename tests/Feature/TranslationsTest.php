<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Lang;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Les messages produits par le framework.
 *
 * Laravel ne livre plus ses fichiers de traduction depuis la version 11. Sans
 * eux, une clé introuvable est affichée telle quelle : un clippeur lisait
 * « auth.failed » au lieu d'une phrase, et chaque formulaire du site annonçait
 * « validation.required » à la place de « Ce champ est obligatoire ».
 *
 * La panne était invisible en relecture — les textes de l'application sont
 * écrits en dur en français et se lisaient très bien. Seuls les messages
 * fabriqués par Laravel étaient touchés, et rien ne les signalait.
 *
 * Le repli vaut `fr` lui aussi : il n'y a donc aucun filet sous ces fichiers.
 */
class TranslationsTest extends TestCase
{
    /** Les messages qu'un visiteur rencontre le plus vite. */
    public static function keys(): array
    {
        return [
            'échec de connexion' => ['auth.failed'],
            'trop de tentatives' => ['auth.throttle'],
            'champ obligatoire' => ['validation.required'],
            'adresse e-mail' => ['validation.email'],
            'confirmation' => ['validation.confirmed'],
            'valeur déjà prise' => ['validation.unique'],
            'fichier trop lourd' => ['validation.uploaded'],
            'lien envoyé' => ['passwords.sent'],
            'page suivante' => ['pagination.next'],
        ];
    }

    #[Test]
    #[DataProvider('keys')]
    public function a_visitor_never_sees_a_raw_translation_key(string $key): void
    {
        $message = trans($key);

        $this->assertNotSame(
            $key,
            $message,
            "« {$key} » s’affiche en clé brute : le fichier de langue correspondant manque.",
        );
    }

    #[Test]
    public function the_french_files_cover_everything_the_framework_can_say(): void
    {
        /*
         * Le vrai risque n'est pas le message qu'on a traduit, c'est celui
         * qu'on a oublié : il ne se voit que le jour où un visiteur déclenche
         * cette règle-là, en production. On compare donc au fichier anglais
         * publié par le framework, qui fait référence.
         */
        foreach (['auth', 'passwords', 'pagination', 'validation'] as $file) {
            $reference = $this->flatten(require lang_path("en/{$file}.php"));
            $french = $this->flatten(require lang_path("fr/{$file}.php"));

            // `custom` et `attributes` sont des espaces laissés libres à
            // l'application, pas des messages du framework à traduire.
            $missing = array_filter(
                array_diff($reference, $french),
                fn (string $key) => ! str_starts_with($key, 'custom.')
                    && ! str_starts_with($key, 'attributes.'),
            );

            $this->assertSame(
                [],
                array_values($missing),
                "Traductions manquantes dans lang/fr/{$file}.php.",
            );
        }
    }

    #[Test]
    public function the_application_speaks_french(): void
    {
        // Le repli compte autant que la locale : c'est lui qui répond quand une
        // clé manque dans la langue demandée.
        $this->assertSame('fr', config('app.locale'));
        $this->assertSame('fr', config('app.fallback_locale'));
        $this->assertTrue(Lang::has('auth.failed'));
    }

    #[Test]
    public function the_failure_message_does_not_say_which_emails_exist(): void
    {
        /*
         * « Cet e-mail est inconnu » transforme le formulaire de connexion en
         * moyen de savoir qui est inscrit chez nous. Le message reste donc le
         * même que l'adresse existe ou non.
         */
        $this->assertStringNotContainsStringIgnoringCase('inconnu', trans('auth.failed'));
        $this->assertStringNotContainsStringIgnoringCase('existe pas', trans('auth.failed'));
    }

    /** Aplatit un tableau de traductions en clés « a.b.c ». */
    protected function flatten(array $items, string $prefix = ''): array
    {
        $keys = [];

        foreach ($items as $key => $value) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            $keys = is_array($value)
                ? array_merge($keys, $this->flatten($value, $path))
                : array_merge($keys, [$path]);
        }

        return $keys;
    }
}
