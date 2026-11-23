<?php

namespace App\Enums;

enum ExpensePaymentStatus: string
{
    case Unpaid = 'unpaid';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Pending Payment',
            self::Paid => 'Paid',
        };
    }
}
