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
    public function an_archive_can_carry_a_whole_kit_in_one_file(): void
    {
        Storage::fake('public');

        $path = UploadedFile::fake()->create('pack-visuel.zip', 2_048)->store('campagnes/pieces', 'public');

        $asset = $this->campaign()->assets()->create([
            'kind' => AssetKind::Archive,
            'label' => 'Pack visuel complet',
            'path' => $path,
        ]);

        $this->assertSame(AssetKind::Archive, $asset->kind);
        $this->assertTrue($asset->isHosted());
        // Rien à jouer dans le navigateur : une archive se télécharge.
        $this->assertFalse($asset->isPreviewable());
    }

    #[Test]
    public function every_accepted_extension_has_a_real_mime_type(): void
    {
        // Le repli `application/octet-stream` existe pour ne jamais bloquer un
        // fichier légitime, mais s'il sert vraiment, l'attribut `accept` du
        // navigateur cesse de filtrer quoi que ce soit. Ce test attrape
        // l'extension ajoutée sans sa correspondance.
        foreach (AssetKind::cases() as $kind) {
            $this->assertNotSame([], $kind->acceptedExtensions(), "{$kind->label()} n'accepte aucune extension.");

            $this->assertNotContains(
                'application/octet-stream',
                $kind->acceptedMimeTypes(),
                "Une extension de {$kind->label()} n'a pas de type MIME déclaré.",
            );
        }
    }

    #[Test]
    public function the_common_formats_an_admin_will_actually_drop_are_accepted(): void
    {
        // La demande était explicite : mp4, mp3, texte, « tout type de doc ».
        $expected = [
            'audio' => ['mp3', 'wav', 'm4a'],
            'video' => ['mp4', 'mov', 'webm'],
            'image' => ['jpg', 'png', 'webp'],
            'document' => ['pdf', 'txt', 'docx', 'xlsx', 'pptx', 'csv'],
            'archive' => ['zip', 'rar'],
        ];

        foreach ($expected as $value => $extensions) {
            $kind = AssetKind::from($value);

            foreach ($extensions as $extension) {
                $this->assertContains(
                    $extension,
                    $kind->acceptedExtensions(),
                    "{$extension} devrait être accepté comme {$kind->label()}.",
                );
            }
        }
    }

    #[Test]
    public function svg_is_deliberately_refused(): void
    {
        // Servi depuis notre propre domaine, un SVG peut embarquer du script et
        // s'exécuter dans le contexte du site. C'est une exclusion voulue, pas
        // un oubli — ce test est là pour qu'on ne l'annule pas par distraction.
        $this->assertNotContains('svg', AssetKind::Image->acceptedExtensions());
    }

    #[Test]
    public function a_video_may_weigh_far_more_than_an_image(): void
    {
        // Un plafond unique obligerait soit à refuser un rush, soit à laisser
        // passer une image de 500 Mo.
        $this->assertGreaterThan(
            AssetKind::Image->maxSizeKb(),
            AssetKind::Video->maxSizeKb(),
        );

        // Livewire plafonne les téléversements temporaires : un type qui
        // dépasserait ce plafond serait refusé avant d'atteindre Filament,
        // avec un message qui ne dit pas pourquoi.
        $livewireCeiling = (int) collect(config('livewire.temporary_file_upload.rules'))
            ->map(fn ($rule) => str_starts_with((string) $rule, 'max:') ? (int) substr((string) $rule, 4) : 0)
            ->max();

        foreach (AssetKind::cases() as $kind) {
            $this->assertLessThanOrEqual(
                $livewireCeiling,
                $kind->maxSizeKb(),
                "Le plafond de {$kind->label()} dépasse celui de Livewire : l'upload échouerait sans explication.",
            );
        }
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
