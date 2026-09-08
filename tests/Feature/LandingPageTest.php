<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La page d'accueil publique.
 *
 * Le bandeau « Publiez depuis vos comptes habituels » affichait TikTok, YouTube
 * et Instagram dans des `<span>` décoratifs — mais avec un effet de survol qui
 * les faisait passer pour des boutons. On cliquait, il ne se passait rien, et on
 * restait sur l'accueil sans comprendre pourquoi : c'est le parcours qui a fait
 * croire que la liaison TikTok était cassée alors qu'elle fonctionnait.
 *
 * Un élément qui s'éclaire au survol promet une action. Ici l'intention est
 * claire — publier depuis ces plateformes — et la réponse est l'inscription.
 */
class LandingPageTest extends TestCase
{
    #[Test]
    public function the_landing_page_is_reachable_without_an_account(): void
    {
        $this->get('/')->assertSuccessful();
    }

    #[Test]
    public function each_platform_badge_leads_somewhere(): void
    {
        $html = $this->get('/')->assertSuccessful()->getContent();

        /*
         * TikTok seul : au lancement, l'accueil ne promet que la plateforme
         * réellement disponible. YouTube et Instagram restent implémentés mais
         * ne sont plus annoncés — ce test suit ce choix plutôt que de le
         * contredire, et couvrira la pastille suivante le jour où elle revient.
         */
        foreach (['TikTok'] as $platform) {
            $this->assertMatchesRegularExpression(
                '#<a[^>]+href="[^"]*'.preg_quote(route('register', absolute: false), '#').'[^"]*"[^>]*>(?:(?!</a>).)*'.$platform.'#s',
                $html,
                "La pastille $platform n'est pas un lien.",
            );
        }
    }

    #[Test]
    public function no_platform_badge_pretends_to_be_a_button(): void
    {
        /*
         * Le défaut d'origine, formulé tel qu'il se reproduirait : un `<span>`
         * portant un effet de survol. Le test vise l'apparence trompeuse, pas
         * la balise en elle-même — un logo réellement inerte, sans effet, reste
         * permis.
         */
        $html = $this->get('/')->assertSuccessful()->getContent();

        preg_match_all('#<span[^>]*class="[^"]*hover:[^"]*"#', $html, $matches);

        $this->assertSame(
            [],
            $matches[0],
            'Un <span> s’éclaire au survol : il annonce une action qu’il n’a pas.',
        );
    }
}
