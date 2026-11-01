<?php

namespace App\Filament\Widgets;

use App\Enums\CampaignMode;
use App\Enums\CampaignStatus;
use App\Enums\PayoutStatus;
use App\Filament\Pages\Messagerie;
use App\Filament\Resources\Campaigns\CampaignResource;
use App\Filament\Resources\Clippers\ClipperResource;
use App\Filament\Resources\Clips\ClipResource;
use App\Filament\Resources\Payouts\PayoutResource;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\Message;
use App\Models\Payout;
use App\Models\PrizeClaim;
use App\Models\SocialAccount;
use App\Models\User;
use Filament\Widgets\Widget;

/**
 * Ce qui attend l'équipe, en tête du tableau de bord.
 *
 * Un tableau de bord sert d'abord à répondre à « qu'est-ce que je dois faire maintenant ? » :
 * des retraits à envoyer, des messages sans réponse, des clips à revalider, des campagnes qui
 * se terminent. Chaque tuile mène directement à l'écran où l'on règle la chose ; celles qui
 * n'ont rien à signaler s'effacent pour ne pas crier au loup.
 */
class ActionRequired extends Widget
{
    protected string $view = 'filament.widgets.action-required';

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->isStaff() ?? false;
    }

    protected function getViewData(): array
    {
        $endingSoon = Campaign::query()
            ->where('status', CampaignStatus::Active)
            ->whereNotNull('ends_at')
            ->whereBetween('ends_at', [now(), now()->addDay()])
            ->count();

        // Un classement terminé dont les prix n'ont pas été attribués : les clippeurs ont fini leur campagne
        // et attendent. Sans cette tuile, rien ne le rappelle à l'équipe.
        $toClose = Campaign::query()
            ->where('mode', CampaignMode::Contest)
            ->whereNull('prizes_paid_at')
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', now())
            ->whereNotIn('status', [CampaignStatus::Draft, CampaignStatus::Archived])
            ->count();

        $claims = PrizeClaim::query()->whereHas('campaign', fn ($q) => $q->whereNull('prizes_paid_at'))->count();

        $tiles = [
            ['n' => $toClose, 'label' => 'Classements à clôturer', 'tone' => 'danger',
                'url' => CampaignResource::getUrl('index')],
            ['n' => $claims, 'label' => 'Prix réclamés', 'tone' => 'danger',
                'url' => CampaignResource::getUrl('index')],
            ['n' => Payout::where('status', PayoutStatus::Requested)->count(), 'label' => 'Retraits à valider', 'tone' => 'warn',
                'url' => PayoutResource::getUrl('index', ['activeTab' => 'requested'])],
            ['n' => Payout::where('status', PayoutStatus::Approved)->count(), 'label' => 'Retraits à envoyer', 'tone' => 'warn',
                'url' => PayoutResource::getUrl('index', ['activeTab' => 'approved'])],
            ['n' => Message::where('from_staff', false)->whereNull('read_at')->count(), 'label' => 'Messages sans réponse', 'tone' => 'warn',
                'url' => Messagerie::getUrl()],
            ['n' => User::flaggedForCheating()->count(), 'label' => 'Clippeurs signalés', 'tone' => 'danger',
                'url' => ClipperResource::getUrl('index', ['tableFilters' => ['flagged' => ['isActive' => true]]])],
            ['n' => Clip::where('status', 'rejected')->count(), 'label' => 'Clips refusés à revoir', 'tone' => 'warn',
                'url' => ClipResource::getUrl('index', ['activeTab' => 'rejected'])],
            ['n' => SocialAccount::where('needs_reconnect', true)->count(), 'label' => 'Comptes à reconnecter', 'tone' => 'warn',
                'url' => ClipperResource::getUrl('index')],
            ['n' => $endingSoon, 'label' => 'Campagnes qui finissent sous 24 h', 'tone' => 'warn',
                'url' => CampaignResource::getUrl('index')],
        ];

        return [
            'tiles' => $tiles,
            'allClear' => collect($tiles)->every(fn (array $tile) => $tile['n'] === 0),
        ];
    }
}
