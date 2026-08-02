<?php

namespace App\Rules;

use App\Models\Product;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class AllowedDispenserProductCategory implements ValidationRule
{
    public function validate(
        string $attribute,
        mixed $value,
        Closure $fail
    ): void {
        if (! is_numeric($value)
            || ! Product::query()
                ->whereKey((int) $value)
                ->allowedForDispenser()
                ->exists()
        ) {
            $fail(
                'The selected product category is not allowed for dispenser assignment.'
            );
        }
    }
}
