<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\AdminBroadcast;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Notification as Notifier;

/**
 * Un message à tout un groupe, en un clic.
 *
 * Réservé au super-administrateur : c'est le seul écran du back-office qui
 * parle directement à tous les utilisateurs à la fois. Une erreur ici ne se
 * rattrape pas — un e-mail parti est parti.
 */
class SendBroadcast extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $navigationLabel = 'Envoyer un message';

    protected static ?string $title = 'Envoyer un message';

    protected static string|\UnitEnum|null $navigationGroup = 'Communication';

    protected string $view = 'filament.pages.send-broadcast';

    /** @var array<string, mixed> */
    public array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public function mount(): void
    {
        $this->form->fill([
            'audience' => 'clippers',
            'only_verified' => true,
            'exclude_banned' => true,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Destinataires')
                    ->columns(2)
                    ->schema([
                        Select::make('audience')
                            ->label('À qui')
                            ->options(static::audiences())
                            ->required()
                            // Le compte de destinataires doit se recalculer à
                            // chaque changement : envoyer sans savoir combien
                            // de personnes sont visées est la façon la plus
                            // simple de se tromper de groupe.
                            ->live(),

                        Toggle::make('only_verified')
                            ->label('Seulement les adresses confirmées')
                            ->helperText('Écrire à des adresses jamais confirmées abîme la réputation d’envoi.')
                            ->live(),

                        Toggle::make('exclude_banned')
                            ->label('Exclure les comptes suspendus')
                            ->live(),
                    ]),

                Section::make('Message')
                    ->schema([
                        TextInput::make('subject')
                            ->label('Objet')
                            ->required()
                            ->maxLength(150),

                        Textarea::make('body')
                            ->label('Message')
                            ->required()
                            ->rows(10)
                            ->maxLength(5000)
                            ->helperText('Une ligne vide sépare deux paragraphes. Le prénom est ajouté automatiquement.'),

                        TextInput::make('action_label')
                            ->label('Libellé du bouton (facultatif)')
                            ->maxLength(60),

                        TextInput::make('action_url')
                            ->label('Lien du bouton')
                            ->url()
                            ->maxLength(2048)
                            ->requiredWith('action_label'),
                    ]),
            ]);
    }

    /** @return array<string, string> */
    public static function audiences(): array
    {
        return [
            'clippers' => 'Tous les clippeurs',
            'creators' => 'Tous les créateurs',
            'staff' => 'Administrateurs et modérateurs',
            'everyone' => 'Tout le monde',
        ];
    }

    /** La requête qui désigne les destinataires. */
    public function recipients(): Builder
    {
        $data = $this->data;

        $query = User::query()->whereNotNull('email');

        $query->when($data['audience'] ?? null, fn (Builder $q, string $audience) => match ($audience) {
            'clippers' => $q->where('role', UserRole::Clipper->value),
            'creators' => $q->where('role', UserRole::Creator->value),
            'staff' => $q->whereIn('role', [UserRole::SuperAdmin->value, UserRole::Moderator->value]),
            default => $q,
        });

        if ($data['only_verified'] ?? false) {
            $query->whereNotNull('email_verified_at');
        }

        if ($data['exclude_banned'] ?? false) {
            $query->where('is_banned', false);
        }

        return $query;
    }

    public function recipientCount(): int
    {
        return $this->recipients()->count();
    }

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('send')
                ->label('Envoyer')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->requiresConfirmation()
                ->modalHeading('Confirmer l’envoi')
                // Le nombre exact dans la confirmation : c'est le dernier
                // moment où une erreur de groupe peut encore être vue.
                ->modalDescription(fn () => sprintf(
                    'Ce message partira à %d destinataire(s). Un e-mail envoyé ne se rappelle pas.',
                    $this->recipientCount(),
                ))
                ->action('send'),
        ];
    }

    public function send(): void
    {
        $data = $this->form->getState();

        $notification = new AdminBroadcast(
            subjectLine: $data['subject'],
            body: $data['body'],
            actionLabel: $data['action_label'] ?: null,
            actionUrl: $data['action_url'] ?: null,
        );

        $sent = 0;

        /*
         * Par paquets, et en file.
         *
         * `chunkById` plutôt que `get()` : à quelques milliers de comptes, tout
         * charger en mémoire fait tomber le processus. Et l'envoi passe par la
         * file — trois cents e-mails dans le cycle d'une requête HTTP la font
         * expirer bien avant la fin, sans qu'on sache qui a reçu quoi.
         */
        $this->recipients()->chunkById(200, function ($users) use ($notification, &$sent) {
            Notifier::send($users, $notification);
            $sent += $users->count();
        });

        Notification::make()
            ->success()
            ->title('Message mis en file')
            ->body($sent.' destinataire(s). L’envoi se fait en arrière-plan.')
            ->send();
    }
}
