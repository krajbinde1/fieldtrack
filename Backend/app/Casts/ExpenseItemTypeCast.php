<?php

namespace App\Casts;

use App\Enums\CenterExpenseCategory;
use App\Enums\ExpenseType;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * @implements CastsAttributes<ExpenseType|CenterExpenseCategory|null, ExpenseType|CenterExpenseCategory|string|null>
 */
final class ExpenseItemTypeCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ExpenseType|CenterExpenseCategory|null
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return ExpenseType::tryFrom($value) ?? CenterExpenseCategory::tryFrom($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value instanceof ExpenseType || $value instanceof CenterExpenseCategory) {
            return $value->value;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }
}
