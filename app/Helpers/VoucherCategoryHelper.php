<?php

namespace App\Helpers;

use InvalidArgumentException;
use RuntimeException;

class VoucherCategoryHelper
{
    public static function customerCode(): string
    {
        return self::getCategoryCode('customer');
    }

    public static function employeeCode(): string
    {
        return self::getCategoryCode('employee');
    }

    public static function supplierCode(): string
    {
        return self::getCategoryCode('supplier');
    }

    public static function operatingCode(): string
    {
        return self::getCategoryCode('operating');
    }

    public static function financeCode(): string
    {
        return self::getCategoryCode('finance');
    }

    public static function getCategoryCode(string $key): string
    {
        $categories = self::systemCategories();

        if (! array_key_exists($key, $categories)) {
            throw new InvalidArgumentException("Unknown voucher category [{$key}].");
        }

        return $categories[$key]['code'];
    }

    public static function getCategoryDefaultName(string $key): string
    {
        $categories = self::systemCategories();

        if (! array_key_exists($key, $categories)) {
            throw new InvalidArgumentException("Unknown voucher category [{$key}].");
        }

        return $categories[$key]['name'];
    }

    public static function systemCategories(): array
    {
        $categories = config(
            'app.erp.voucher.categories.system',
            config('erp.voucher_categories.system')
        );

        if (! is_array($categories) || $categories === []) {
            throw new RuntimeException('Voucher category ERP configuration is missing.');
        }

        return $categories;
    }

    public static function getSystemCategoryCodes(): array
    {
        return array_values(array_map(
            static fn (array $category): string => $category['code'],
            self::systemCategories()
        ));
    }

    public static function isSystemCode(?string $code): bool
    {
        return $code !== null
            && in_array($code, self::getSystemCategoryCodes(), true);
    }

    public static function resolveSystemCode(
        ?string $code,
        ?string $name
    ): ?string {
        if (self::isSystemCode($code)) {
            return $code;
        }

        $normalizedName = mb_strtolower(trim((string) $name));

        foreach (self::systemCategories() as $category) {
            $names = array_unique([
                $category['name'],
                ...($category['legacy_names'] ?? []),
            ]);

            foreach ($names as $candidate) {
                if (mb_strtolower(trim((string) $candidate)) === $normalizedName) {
                    return $category['code'];
                }
            }
        }

        return null;
    }

    public static function prefix(): string
    {
        return (string) config(
            'app.erp.voucher.categories.prefix',
            config('erp.voucher_categories.prefix', 'VC')
        );
    }

    public static function codePadding(): int
    {
        return (int) config(
            'app.erp.voucher.categories.padding',
            config('erp.voucher_categories.padding', 3)
        );
    }

    public static function minimumCustomSequence(): int
    {
        $highestSystemSequence = 0;

        foreach (self::getSystemCategoryCodes() as $code) {
            $highestSystemSequence = max(
                $highestSystemSequence,
                self::sequenceNumber($code)
            );
        }

        return $highestSystemSequence + 1;
    }

    public static function sequenceNumber(?string $code): int
    {
        if (! $code) {
            return 0;
        }

        $pattern = '/^'.preg_quote(self::prefix(), '/').'(\d+)$/';

        return preg_match($pattern, $code, $matches) === 1
            ? (int) $matches[1]
            : 0;
    }

    public static function permissionNames(): array
    {
        return [
            'voucher-category-view',
            'voucher-category-create',
            'voucher-category-update',
            'voucher-category-delete',
        ];
    }
}
