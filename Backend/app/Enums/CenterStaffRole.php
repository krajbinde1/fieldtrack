<?php

namespace App\Enums;

enum CenterStaffRole: string
{
    case Mis = 'mis';
    case Mobilizer = 'mobilizer';
    case Housekeeper = 'housekeeper';
    case Trainer = 'trainer';
    case FoodHostelSupervisor = 'food_hostel_supervisor';

    public function label(): string
    {
        return match ($this) {
            self::Mis => 'MIS',
            self::Mobilizer => 'Mobilizer',
            self::Housekeeper => 'Housekeeper',
            self::Trainer => 'Trainer',
            self::FoodHostelSupervisor => 'Food & Hostel Supervisor',
        };
    }

    public function canSubmitAdmissions(): bool
    {
        return $this !== self::FoodHostelSupervisor;
    }

    /**
     * Reserved for later modules. No Food or Hostel screens are registered from these keys.
     *
     * @return list<string>
     */
    public function reservedPermissions(): array
    {
        if ($this !== self::FoodHostelSupervisor) {
            return [];
        }

        return [
            'food_expenses',
            'hostel_expenses',
            'daily_food_consumption',
            'hostel_occupancy',
        ];
    }

    /**
     * Roles that can be assigned to new staff. Existing MIS and Housekeeper values still resolve.
     *
     * @return array<string, string>
     */
    public static function assignableOptions(): array
    {
        return [
            self::Trainer->value => self::Trainer->label(),
            self::Mobilizer->value => self::Mobilizer->label(),
            self::FoodHostelSupervisor->value => self::FoodHostelSupervisor->label(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return self::assignableOptions();
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
