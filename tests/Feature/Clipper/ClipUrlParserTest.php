<?php

namespace Tests\Feature\Clipper;

use App\Enums\Platform;
use App\Exceptions\ClipSubmissionRefused;
use App\Services\Clips\ClipUrlParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClipUrlParserTest extends TestCase
{
    protected ClipUrlParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new ClipUrlParser;
    }

    public static function validUrls(): array
    {
        return [
            'TikTok' => [
                'https://www.tiktok.com/@lina.clips/video/7123456789012345678',
                Platform::TikTok,
                '7123456789012345678',
            ],
            'TikTok avec paramètres de suivi' => [
                'https://www.tiktok.com/@lina.clips/video/7123456789012345678?is_from_webapp=1&sender_device=pc',
                Platform::TikTok,
                '7123456789012345678',
            ],
            'YouTube classique' => [
                'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                Platform::YouTube,
                'dQw4w9WgXcQ',
            ],
            'YouTube Shorts' => [
                'https://www.youtube.com/shorts/dQw4w9WgXcQ',
                Platform::YouTube,
                'dQw4w9WgXcQ',
            ],
            'YouTube court' => [
                'https://youtu.be/dQw4w9WgXcQ',
                Platform::YouTube,
                'dQw4w9WgXcQ',
            ],
            'Instagram Reel' => [
                'https://www.instagram.com/reel/C1a2B3c4D5e/',
                Platform::Instagram,
                'C1a2B3c4D5e',
            ],
            'Instagram post' => [
                'https://www.instagram.com/p/C1a2B3c4D5e/?utm_source=ig_web',
                Platform::Instagram,
                'C1a2B3c4D5e',
            ],
        ];
    }

    #[Test]
    #[DataProvider('validUrls')]
    public function it_extracts_the_platform_and_post_id(string $url, Platform $platform, string $id): void
    {
        $parsed = $this->parser->parse($url);

        $this->assertSame($platform, $parsed->platform);
        $this->assertSame($id, $parsed->externalPostId);
    }

    #[Test]
    public function two_urls_of_the_same_post_produce_the_same_identifier(): void
    {
        // C'est cette propriété qui fait tenir la contrainte d'unicité : sans
        // elle, un paramètre de suivi suffirait à soumettre deux fois le même clip.
        $a = $this->parser->parse('https://www.youtube.com/watch?v=dQw4w9WgXcQ');
        $b = $this->parser->parse('https://youtu.be/dQw4w9WgXcQ');

        $this->assertSame($a->externalPostId, $b->externalPostId);
        $this->assertSame($a->canonicalUrl, $b->canonicalUrl);
    }

    #[Test]
    public function short_links_are_refused_with_an_explanation(): void
    {
        $this->expectException(ClipSubmissionRefused::class);
        $this->expectExceptionMessage('adresse complète');

        $this->parser->parse('https://vm.tiktok.com/ZMabcdef/');
    }

    #[Test]
    public function an_unsupported_platform_is_refused(): void
    {
        $this->expectException(ClipSubmissionRefused::class);
        $this->expectExceptionMessage('TikTok, YouTube et Instagram');

        $this->parser->parse('https://twitter.com/user/status/123');
    }

    #[Test]
    public function a_profile_url_is_not_a_clip(): void
    {
        $this->expectException(ClipSubmissionRefused::class);

        $this->parser->parse('https://www.tiktok.com/@lina.clips');
    }

    #[Test]
    public function a_malformed_youtube_id_is_refused(): void
    {
        // Les identifiants YouTube font exactement 11 caractères : sans ce
        // contrôle, n'importe quel fragment de chemin passerait.
        $this->expectException(ClipSubmissionRefused::class);

        $this->parser->parse('https://www.youtube.com/watch?v=trop-court');
    }

    #[Test]
    public function anything_that_is_not_a_url_is_refused(): void
    {
        $this->expectException(ClipSubmissionRefused::class);

        $this->parser->parse('mon super clip');
    }
}
