<?php

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Models\User;
use Filament\Widgets\Widget;

/**
 * Qui a les clés du back-office.
 *
 * Un compte admin donne accès aux paiements : la liste doit se voir d'un coup
 * d'œil, avec ce qui la rend sûre — la double authentification — et ce qui
 * dit si le compte sert aussi à clipper (tout admin est aussi clippeur).
 */
class AdminList extends Widget
{
    protected string $view = 'filament.widgets.admin-list';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        return [
            'admins' => User::query()
                ->whereIn('role', [UserRole::SuperAdmin, UserRole::Moderator])
                ->withCount('clips')
                ->orderByRaw('role = ? desc', [UserRole::SuperAdmin->value])
                ->orderBy('created_at')
                ->get(),
        ];
    }
}
