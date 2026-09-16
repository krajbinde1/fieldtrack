<?php

namespace App\Enums;

enum AdmissionTargetType: string
{
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::Weekly => 'Weekly',
            self::Monthly => 'Monthly',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::Weekly->value => self::Weekly->label(),
            self::Monthly->value => self::Monthly->label(),
        ];
    }
}
