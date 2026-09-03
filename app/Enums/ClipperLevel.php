<?php

namespace App\Enums;

/**
 * Niveau d'un clippeur, dérivé de son expérience de carrière.
 *
 * Le niveau ne redescend jamais : c'est une reconnaissance de ce qui a été
 * accompli. Les avantages qu'il ouvre, eux, exigent de rester actif — sans
 * quoi un clippeur inactif depuis un an garderait un accès prioritaire au
 * détriment de ceux qui travaillent.
 */
enum ClipperLevel: string
{
    case Beginner = 'beginner';
    case Confirmed = 'confirmed';
    case Expert = 'expert';
    case Elite = 'elite';
    case Legend = 'legend';

    /** Expérience de carrière nécessaire pour atteindre ce niveau. */
    public function threshold(): int
    {
        return match ($this) {
            self::Beginner => 0,
            self::Confirmed => 50_000,
            self::Expert => 250_000,
            self::Elite => 1_000_000,
            self::Legend => 5_000_000,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Beginner => 'Débutant',
            self::Confirmed => 'Confirmé',
            self::Expert => 'Expert',
            self::Elite => 'Élite',
            self::Legend => 'Légende',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Beginner => 'gray',
            self::Confirmed => 'info',
            self::Expert => 'success',
            self::Elite => 'warning',
            self::Legend => 'danger',
        };
    }

    /**
     * Avance d'accès aux nouvelles campagnes, en heures.
     *
     * C'est l'avantage le plus fort de la plateforme : le budget part au
     * premier arrivé, donc l'antériorité est la vraie monnaie. Il ne coûte
     * rien au budget et ne touche pas au moteur.
     */
    public function earlyAccessHours(): int
    {
        return match ($this) {
            self::Beginner, self::Confirmed => 0,
            self::Expert => 12,
            self::Elite, self::Legend => 24,
        };
    }

    /**
     * Multiplicateur du plafond de gain par clip.
     *
     * Il ne déplace que la répartition : le budget total de la campagne reste
     * le plafond absolu, et le moteur continue de le faire respecter.
     */
    public function clipCapMultiplier(): float
    {
        return match ($this) {
            self::Beginner => 1.0,
            self::Confirmed => 1.5,
            self::Expert => 1.75,
            self::Elite, self::Legend => 2.0,
        };
    }

    /**
     * Vues rémunérées à maintenir sur la fenêtre d'activité pour conserver ses
     * avantages. Le niveau Débutant n'en ouvrant aucun, il n'a rien à maintenir.
     */
    public function activityFloor(): int
    {
        return match ($this) {
            self::Beginner => 0,
            self::Confirmed => 10_000,
            self::Expert => 25_000,
            self::Elite => 50_000,
            self::Legend => 100_000,
        };
    }

    public function hasPerks(): bool
    {
        return $this !== self::Beginner;
    }

    /** Niveau atteint avec cette expérience de carrière. */
    public static function fromXp(int $xp): self
    {
        return collect(self::cases())
            ->sortByDesc(fn (self $level) => $level->threshold())
            ->first(fn (self $level) => $xp >= $level->threshold()) ?? self::Beginner;
    }

    public function next(): ?self
    {
        return collect(self::cases())
            ->sortBy(fn (self $level) => $level->threshold())
            ->first(fn (self $level) => $level->threshold() > $this->threshold());
    }
}
