<?php

namespace App\Support\Clippers;

use Illuminate\Support\Carbon;

/** Pourquoi un clippeur figure dans la liste des clippeurs à relancer. */
final readonly class RetentionSignal
{
    public const AT_RISK = 'at_risk';

    public const DROPPING = 'dropping';

    public const INACTIVE = 'inactive';

    /** @param  list<string>  $reasons */
    public function __construct(
        public int $userId,
        public string $level,
        public array $reasons,
        public int $clipsInWindow,
        public int $earnedInWindowCents,
        public int $earnedPreviousCents,
        public ?Carbon $lastClipAt,
    ) {}

    /** @return array<string, string> */
    public static function levels(): array
    {
        return [
            self::AT_RISK => 'À risque',
            self::DROPPING => 'Gains en baisse',
            self::INACTIVE => 'Inactif',
        ];
    }

    public function label(): string
    {
        return self::levels()[$this->level];
    }

    public function color(): string
    {
        return match ($this->level) {
            self::AT_RISK => 'danger',
            self::DROPPING => 'warning',
            default => 'gray',
        };
    }

    /** Ordre d'affichage : le plus urgent d'abord. */
    public function priority(): int
    {
        return match ($this->level) {
            self::AT_RISK => 0,
            self::DROPPING => 1,
            default => 2,
        };
    }

    /**
     * Premier jet du message, à retoucher avant l'envoi : une relance qui
     * sonne comme un modèle se lit comme telle.
     */
    public function draftMessage(string $name): string
    {
        return match ($this->level) {
            self::AT_RISK => "Salut {$name} ! On voit que tu postes beaucoup en ce moment sans que ça paie encore. "
                .'Envoie-nous un de tes liens : on regarde avec toi ce qui bloque (brief, compte lié, format). '
                .'La formation peut aussi aider : '.route('training.index'),
            self::DROPPING => "Salut {$name} ! Tes gains ont baissé ces dernières semaines. "
                .'De nouvelles campagnes sont ouvertes, et on peut regarder ensemble les formats qui marchent le mieux en ce moment.',
            default => "Salut {$name}, ça fait un moment ! De nouvelles campagnes sont ouvertes et le budget part au premier arrivé. "
                .'Si quelque chose t’a freiné, dis-le nous ici.',
        };
    }
}
