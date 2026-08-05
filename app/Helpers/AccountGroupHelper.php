<?php

namespace App\Helpers;

use InvalidArgumentException;
use RuntimeException;

class AccountGroupHelper
{
    public static function code(string $key): string
    {
        $groups = self::systemGroups();

        if (! array_key_exists($key, $groups)) {
            throw new InvalidArgumentException(
                "Unknown ERP account group [{$key}]."
            );
        }

        return $groups[$key]['code'];
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    public static function codes(array $keys): array
    {
        return array_values(array_map(
            fn (string $key): string => self::code($key),
            $keys
        ));
    }

    /**
     * @return array<string, array{
     *     code: string,
     *     name: string,
     *     parent: string|null,
     *     account_class: string,
     *     normal_balance: string
     * }>
     */
    public static function systemGroups(): array
    {
        $groups = config(
            'app.erp.account_groups.system',
            config('erp.account_groups.system')
        );

        if (! is_array($groups) || $groups === []) {
            throw new RuntimeException(
                'ERP account group configuration is missing.'
            );
        }

        $normalized = [];
        $usedCodes = [];

        foreach ($groups as $key => $group) {
            if (! is_array($group)) {
                throw new RuntimeException(
                    "ERP account group [{$key}] is invalid."
                );
            }

            foreach (
                ['code', 'name', 'account_class', 'normal_balance'] as $field
            ) {
                if (! isset($group[$field]) || trim((string) $group[$field]) === '') {
                    throw new RuntimeException(
                        "ERP account group [{$key}] is missing [{$field}]."
                    );
                }
            }

            $parent = $group['parent'] ?? null;
            $code = (string) $group['code'];
            $accountClass = (string) $group['account_class'];
            $normalBalance = (string) $group['normal_balance'];

            if ($parent !== null && ! array_key_exists((string) $parent, $groups)) {
                throw new RuntimeException(
                    "ERP account group [{$key}] has an unknown parent [{$parent}]."
                );
            }

            if (isset($usedCodes[$code])) {
                throw new RuntimeException(
                    "ERP account group code [{$code}] is duplicated."
                );
            }

            if (! in_array(
                $accountClass,
                ['asset', 'liability', 'equity', 'revenue', 'expense'],
                true
            )) {
                throw new RuntimeException(
                    "ERP account group [{$key}] has an invalid account class."
                );
            }

            if (! in_array($normalBalance, ['debit', 'credit'], true)) {
                throw new RuntimeException(
                    "ERP account group [{$key}] has an invalid normal balance."
                );
            }

            $usedCodes[$code] = true;
            $normalized[(string) $key] = [
                'code' => $code,
                'name' => (string) $group['name'],
                'parent' => $parent === null ? null : (string) $parent,
                'account_class' => $accountClass,
                'normal_balance' => $normalBalance,
            ];
        }

        return $normalized;
    }

    /**
     * @return list<string>
     */
    public static function systemCodes(): array
    {
        return array_values(array_map(
            static fn (array $group): string => $group['code'],
            self::systemGroups()
        ));
    }
}
