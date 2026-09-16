<?php

namespace App\Enums;

enum LeaveType: string
{
    case Casual = 'casual';
    case Sick = 'sick';
    case Paid = 'paid';
    case Unpaid = 'unpaid';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Casual => 'Casual Leave',
            self::Sick => 'Sick Leave',
            self::Paid => 'Paid Leave',
            self::Unpaid => 'Unpaid Leave',
            self::Other => 'Other',
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
}
