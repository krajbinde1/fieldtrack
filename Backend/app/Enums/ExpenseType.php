<?php

namespace App\Enums;

enum ExpenseType: string
{
    case Travelling = 'travelling';
    case Hotel = 'hotel';
    case Food = 'food';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Travelling => 'Travelling',
            self::Hotel => 'Hotel',
            self::Food => 'Food',
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
