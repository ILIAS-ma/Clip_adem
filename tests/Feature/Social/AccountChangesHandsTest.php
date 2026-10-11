<?php

namespace Tests\Feature\Social;

use App\Enums\Platform;
use App\Enums\UserRole;
use App\Exceptions\SocialProviderFailed;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Social\SocialAccountLinker;
use App\Support\Social\ConnectedAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Un compte TikTok peut-il passer d'un profil à un autre ?
 *
 * La personne qui relie un compte vient de prouver par OAuth qu'elle le
 * détient : le refus ne la protège pas d'un vol. Il protège de deux choses —
 * deux profils qui se disputent le même compte, et un banni qui revient sous
 * un nouveau profil avec le même compte.
 *
 * Avant, un compte délié restait attaché à son ancien profil pour toujours :
 * un TikTok essayé une fois sur un profil de test ne pouvait plus servir
 * ailleurs, et le message ne disait pas quoi faire.
 */
class AccountChangesHandsTest extends TestCase
{
    use RefreshDatabase;

    protected function clipper(array $attributs = []): User
    {
        return User::factory()->create(array_merge([
            'role' => UserRole::Clipper,
            'email_verified_at' => now(),
        ], $attributs));
    }

    protected function compteLie(User $user, bool $actif = true): SocialAccount
    {
        return SocialAccount::factory()->create([
            'user_id' => $user->getKey(),
            'platform' => Platform::TikTok,
            'external_account_id' => 'tiktok-123',
            'handle' => 'maya.clips',
            'is_active' => $actif,
        ]);
    }

    protected function connexion(): ConnectedAccount
    {
        return new ConnectedAccount(
            platform: Platform::TikTok,
            externalAccountId: 'tiktok-123',
            handle: 'maya.clips',
            accessToken: 'jeton',
            refreshToken: 'renouvellement',
            expiresAt: now()->addDay(),
            scopes: ['user.info.basic', 'video.list'],
            followersCount: 1000,
        );
    }

    protected function lier(User $user): SocialAccount
    {
        return app(SocialAccountLinker::class)->link($user, $this->connexion());
    }

    #[Test]
    public function an_unlinked_account_can_move_to_another_profile(): void
    {
        $ancien = $this->clipper();
        $nouveau = $this->clipper();
        $compte = $this->compteLie($ancien, actif: false);

        $lie = $this->lier($nouveau);

        // Le même enregistrement, pas un doublon : les clips existants y
        // restent rattachés.
        $this->assertSame($compte->getKey(), $lie->getKey());
        $this->assertSame($nouveau->getKey(), $lie->user_id);
        $this->assertTrue($lie->is_active);
    }

    #[Test]
    public function an_account_still_active_elsewhere_does_not_move(): void
    {
        // Sinon deux profils se disputeraient le compte, et les vues de l'un
        // partiraient au gré des reconnexions.
        $ancien = $this->clipper();
        $this->compteLie($ancien, actif: true);

        try {
            $this->lier($this->clipper());
            $this->fail('La liaison devait être refusée.');
        } catch (SocialProviderFailed $e) {
            // Le message dit quoi faire, pas seulement que c'est interdit.
            $this->assertStringContainsString('Déliez-le', $e->getMessage());
        }

        $this->assertSame($ancien->getKey(), SocialAccount::first()->user_id);
    }

    #[Test]
    public function a_banned_profiles_account_never_moves(): void
    {
        // Nouveau profil, même compte, mêmes clips qui recommencent à
        // rapporter : c'est le contournement de bannissement le plus simple.
        $banni = $this->clipper(['is_banned' => true]);
        $this->compteLie($banni, actif: false);

        $this->expectException(SocialProviderFailed::class);
        $this->expectExceptionMessage('suspendu');

        $this->lier($this->clipper());
    }

    #[Test]
    public function an_account_left_on_a_deleted_profile_can_move(): void
    {
        // Le profil n'existe plus : rien ne peut plus se disputer le compte.
        $ancien = $this->clipper();
        $this->compteLie($ancien, actif: true);
        $ancien->delete();

        $lie = $this->lier($nouveau = $this->clipper());

        $this->assertSame($nouveau->getKey(), $lie->user_id);
    }

    #[Test]
    public function relinking_on_the_same_profile_still_works(): void
    {
        $user = $this->clipper();
        $compte = $this->compteLie($user, actif: true);

        $this->assertSame($compte->getKey(), $this->lier($user)->getKey());
    }
}
