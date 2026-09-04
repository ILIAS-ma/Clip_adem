<?php

namespace Tests\Feature\Campaigns;

use App\Enums\AssetKind;
use App\Enums\CampaignStatus;
use App\Enums\ParticipationStatus;
use App\Enums\Platform;
use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\CampaignAsset;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Les pièces jointes du brief : le son imposé, les rushes, la charte.
 *
 * Ce qui compte ici n'est pas le stockage mais ce que le clippeur voit : sans
 * accès à la matière première, il tourne à côté du brief et le clip est refusé.
 */
class CampaignAssetTest extends TestCase
{
    use RefreshDatabase;

    protected function campaign(): Campaign
    {
        return Campaign::factory()
            ->withRate(Platform::TikTok, ratePer1kCents: 100)
            ->create([
                'status' => CampaignStatus::Active,
                'budget_total_cents' => 100_000,
                'brief' => 'Utiliser le refrain.',
            ]);
    }

    protected function clipperOn(Campaign $campaign): User
    {
        $clipper = User::factory()->create([
            'role' => UserRole::Clipper,
            'email_verified_at' => now(),
            'pseudo' => 'maya.clips',
            'country' => 'FR',
            'paypal_email' => 'maya@paypal.test',
            'profile_completed_at' => now(),
        ]);

        $account = SocialAccount::factory()->create([
            'user_id' => $clipper->getKey(),
            'platform' => Platform::TikTok,
        ]);

        $campaign->participations()->create([
            'user_id' => $clipper->getKey(),
            'social_account_id' => $account->getKey(),
            'status' => ParticipationStatus::Approved,
            'applied_at' => now(),
            'approved_at' => now(),
        ]);

        return $clipper;
    }

    #[Test]
    public function a_clipper_sees_the_brief_material_on_the_campaign_page(): void
    {
        $campaign = $this->campaign();
        $clipper = $this->clipperOn($campaign);

        $campaign->assets()->create([
            'kind' => AssetKind::Audio,
            'label' => 'Son officiel du refrain',
            'description' => 'Caler le drop à 0:12.',
            'external_url' => 'https://example.com/son.mp3',
            'is_required' => true,
        ]);

        $this->actingAs($clipper)
            ->get(route('campaigns.show', $campaign))
            ->assertSuccessful()
            ->assertSee('Matière première')
            ->assertSee('Son officiel du refrain')
            ->assertSee('Caler le drop à 0:12.')
            // Ce qui est imposé doit se distinguer de ce qui inspire.
            ->assertSee('Imposé');
    }

    #[Test]
    public function a_campaign_without_material_shows_no_empty_section(): void
    {
        $campaign = $this->campaign();
        $clipper = $this->clipperOn($campaign);

        $this->actingAs($clipper)
            ->get(route('campaigns.show', $campaign))
            ->assertSuccessful()
            ->assertDontSee('Matière première');
    }

    #[Test]
    public function an_uploaded_file_gets_its_weight_and_type_read_from_disk(): void
    {
        Storage::fake('public');

        $path = UploadedFile::fake()
            ->image('pochette.png', 400, 400)
            ->store('campagnes/pieces', 'public');

        $asset = $this->campaign()->assets()->create([
            'kind' => AssetKind::Image,
            'label' => 'Pochette',
            'path' => $path,
        ]);

        // Le formulaire pourrait mentir sur le poids ; le disque, non.
        $this->assertGreaterThan(0, $asset->size_bytes);
        $this->assertStringContainsString('image', (string) $asset->mime_type);
        $this->assertNotNull($asset->humanSize());
        $this->assertTrue($asset->isPreviewable());
    }

    #[Test]
    public function a_hosted_file_wins_over_a_leftover_link(): void
    {
        Storage::fake('public');

        $path = UploadedFile::fake()->create('charte.pdf', 12)->store('campagnes/pieces', 'public');

        $asset = $this->campaign()->assets()->create([
            'kind' => AssetKind::Document,
            'label' => 'Charte',
            'path' => $path,
            'external_url' => 'https://drive.example.com/charte',
        ]);

        // Deux sources pour la même pièce, c'est deux vérités sur ce qu'il faut
        // réellement utiliser.
        $this->assertNull($asset->external_url);
        $this->assertTrue($asset->isHosted());
        $this->assertStringContainsString($path, (string) $asset->url());
    }

    #[Test]
    public function an_external_link_stays_usable_without_any_upload(): void
    {
        $asset = $this->campaign()->assets()->create([
            'kind' => AssetKind::Video,
            'label' => 'Rushes',
            'external_url' => 'https://drive.example.com/rushes',
        ]);

        $this->assertFalse($asset->isHosted());
        $this->assertSame('https://drive.example.com/rushes', $asset->url());

        // Rien à prévisualiser : le fichier n'est pas chez nous.
        $this->assertFalse($asset->isPreviewable());
    }

    #[Test]
    public function material_is_ordered_by_position_not_by_creation(): void
    {
        $campaign = $this->campaign();

        $campaign->assets()->create(['kind' => AssetKind::Document, 'label' => 'Charte', 'position' => 2]);
        $campaign->assets()->create(['kind' => AssetKind::Audio, 'label' => 'Son', 'position' => 0]);
        $campaign->assets()->create(['kind' => AssetKind::Video, 'label' => 'Rushes', 'position' => 1]);

        $this->assertSame(
            ['Son', 'Rushes', 'Charte'],
            $campaign->assets()->pluck('label')->all(),
        );
    }

    #[Test]
    public function deleting_a_campaign_takes_its_material_with_it(): void
    {
        $campaign = $this->campaign();
        $campaign->assets()->create(['kind' => AssetKind::Audio, 'label' => 'Son']);

        $campaign->forceDelete();

        $this->assertSame(0, CampaignAsset::count());
    }
}
