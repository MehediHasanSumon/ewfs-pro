<?php

namespace App\Helpers;

use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class ErpHelper
{
    public static function oilCategoryCode(): string
    {
        return self::getCategoryCode('oil');
    }

    public static function gasCategoryCode(): string
    {
        return self::getCategoryCode('gas');
    }

    public static function lubricantCategoryCode(): string
    {
        return self::getCategoryCode('lubricant');
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
