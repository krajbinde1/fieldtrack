<?php

namespace App\Enums;

enum CenterStaffRole: string
{
    case Mis = 'mis';
    case Mobilizer = 'mobilizer';
    case Housekeeper = 'housekeeper';
    case Trainer = 'trainer';

    public function label(): string
    {
        return match ($this) {
            self::Mis => 'MIS',
            self::Mobilizer => 'Mobilizer',
            self::Housekeeper => 'Housekeeper',
            self::Trainer => 'Trainer',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    public static function tryFromMixed(?string $value): self
    {
        if ($value === null || $value === '') {
            return self::Mobilizer;
        }

        return self::tryFrom($value) ?? self::Mobilizer;
    }
}
