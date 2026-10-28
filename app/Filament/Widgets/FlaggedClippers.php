<?php

namespace App\Filament\Widgets;

use App\Enums\ModerationAction;
use App\Enums\UserRole;
use App\Filament\Resources\Clippers\ClipperResource;
use App\Models\Clip;
use App\Models\User;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

/**
 * Les clippeurs signalés : ceux dont des clips ont été refusés parce que les vidéos
 * avaient été publiées avant le début de la campagne.
 *
 * La carte n'existe que s'il y a quelqu'un à signaler : un tableau de bord qui affiche
 * « personne » en permanence finit par ne plus être regardé.
 */
class FlaggedClippers extends Widget
{
    protected string $view = 'filament.widgets.flagged-clippers';

    // Avant tout le reste : c'est ce qui demande une décision.
    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (auth()->user()?->isStaff() ?? false) && User::flaggedForCheating()->exists();
    }

    protected function getViewData(): array
    {
        $rows = DB::table('moderation_logs as log')
            ->join('clips as c', 'c.id', '=', 'log.subject_id')
            ->join('users as u', 'u.id', '=', 'c.user_id')
            ->join('campaigns as ca', 'ca.id', '=', 'c.campaign_id')
            ->where('log.subject_type', (new Clip)->getMorphClass())
            ->whereIn('log.action', [ModerationAction::ClipOutsideWindow->value, ModerationAction::ClipSuspectedReupload->value])
            ->groupBy('u.id', 'u.name', 'u.pseudo', 'u.role', 'u.is_banned')
            ->selectRaw('u.id, u.name, u.pseudo, u.role, u.is_banned, COUNT(*) as clips, MAX(log.created_at) as last_at')
            ->selectRaw('SUM(log.action = ?) as refused, SUM(log.action = ?) as reuploads', [ModerationAction::ClipOutsideWindow->value, ModerationAction::ClipSuspectedReupload->value])
            ->selectRaw("GROUP_CONCAT(DISTINCT ca.title ORDER BY ca.title SEPARATOR ' · ') as campaigns")
            ->orderByDesc('clips')
            ->orderByDesc('last_at')
            ->limit(15)
            ->get();

        return [
            'rows' => $rows->map(fn (object $row) => (object) [
                'name' => $row->name,
                'pseudo' => $row->pseudo,
                'banned' => (bool) $row->is_banned,
                'clips' => (int) $row->clips,
                'refused' => (int) $row->refused,
                'reuploads' => (int) $row->reuploads,
                'campaigns' => (string) $row->campaigns,
                'last_at' => $row->last_at,
                'url' => $row->role === UserRole::Clipper->value
                    ? ClipperResource::getUrl('view', ['record' => $row->id])
                    : null,
            ]),
        ];
    }
}
