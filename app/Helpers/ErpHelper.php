<?php

namespace App\Helpers;

use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class ErpHelper
{
    public static function oilCategoryCode(): string
    {
        return self::getProductCategoryCode('oil');
    }

    public static function gasCategoryCode(): string
    {
        return self::getProductCategoryCode('gas');
    }

    public static function lubricantCategoryCode(): string
    {
        return self::getProductCategoryCode('lubricant');
    }

    public static function getProductCategoryCode(string $category): string
    {
        $categories = config('erp.product_categories');
        $key = Str::slug($category);
        $code = is_array($categories) ? ($categories[$key] ?? null) : null;

        if ($code === null) {
            throw new InvalidArgumentException(
                "Unknown ERP product category [{$category}]."
            );
        }

        return (string) $code;
    }

    public static function dispenserProductCategoryCodes(): array
    {
        $keys = config('erp.dispenser.allowed_product_category_keys');

        if (! is_array($keys) || $keys === []) {
            throw new RuntimeException(
                'Dispenser product category configuration is missing.'
            );
        }

        return array_values(array_unique(array_map(
            fn (string $key): string => self::getProductCategoryCode($key),
            $keys
        )));
    }

    public static function isDispenserProductCategoryCode(
        string|int|null $code
    ): bool {
        return $code !== null
            && in_array(
                (string) $code,
                self::dispenserProductCategoryCodes(),
                true
            );
    }

    public static function getCategoryCode(string $category): string
    {
        $needle = Str::slug($category);

        foreach (self::getCategoryDefaults() as $code => $name) {
            $configuredName = Str::slug($name);

            if (
                $needle === $configuredName
                || str_starts_with($configuredName, $needle.'-')
            ) {
                return (string) $code;
            }
        }

        throw new InvalidArgumentException("Unknown ERP category [{$category}].");
    }

    public static function getCategoryDefaultName(string|int $code): string
    {
        $categories = self::getCategoryDefaults();
        $normalizedCode = (string) $code;

        if (! array_key_exists($normalizedCode, $categories)) {
            throw new InvalidArgumentException(
                "Unknown ERP category code [{$normalizedCode}]."
            );
        }

        return $categories[$normalizedCode];
    }

    public static function getReservedCategoryCodes(): array
    {
        return array_map(
            static fn (int|string $code): string => (string) $code,
            array_keys(self::getCategoryDefaults())
        );
    }

    public static function isReservedCategoryCode(string|int|null $code): bool
    {
        return $code !== null
            && in_array((string) $code, self::getReservedCategoryCodes(), true);
    }

    public static function getCategoryDefaults(): array
    {
        $categories = config('erp.categories');

        if (! is_array($categories) || $categories === []) {
            throw new RuntimeException('ERP category configuration is missing.');
        }

        $normalized = [];

        foreach ($categories as $code => $name) {
            $normalized[(string) $code] = (string) $name;
        }

        return $normalized;
    }
}
