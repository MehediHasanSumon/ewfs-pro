<?php

namespace App\Helpers;

use InvalidArgumentException;
use RuntimeException;

class VoucherTransactionTypeHelper
{
    public static function systemTypes(): array
    {
        $types = config('app.erp.voucher.transaction_types.system');

        if (! is_array($types) || $types === []) {
            throw new RuntimeException('Voucher transaction type ERP configuration is missing.');
        }

        return $types;
    }

    public static function flattenedSystemTypes(): array
    {
        $flattened = [];
        $sortOrder = 1;

        foreach (self::systemTypes() as $categoryKey => $types) {
            foreach ($types as $key => $type) {
                $flattened[] = [
                    ...$type,
                    'key' => $key,
                    'category_key' => $categoryKey,
                    'category_code' => VoucherCategoryHelper::getCategoryCode($categoryKey),
                    'description' => $type['description'] ?? null,
                    'sort_order' => $type['sort_order'] ?? $sortOrder,
                ];
                $sortOrder++;
            }
        }

        return $flattened;
    }

    public static function getCode(string $categoryKey, string $transactionKey): string
    {
        $types = self::systemTypes();

        if (! isset($types[$categoryKey][$transactionKey]['code'])) {
            throw new InvalidArgumentException(
                "Unknown voucher transaction type [{$categoryKey}.{$transactionKey}]."
            );
        }

        return (string) $types[$categoryKey][$transactionKey]['code'];
    }

    public static function customerSecurityDepositRefundCode(): string
    {
        return self::getCode('customer', 'security_deposit_refund');
    }

    public static function customerAdvanceReturnCode(): string
    {
        return self::getCode('customer', 'advance_return');
    }

    public static function customerSecurityDepositCode(): string
    {
        return self::getCode('customer', 'security_deposit');
    }

    public static function customerDuePaidCode(): string
    {
        return self::getCode('customer', 'due_paid');
    }

    public static function legacyCustomerAdvancePaymentCode(): string
    {
        $code = config(
            'app.erp.voucher.transaction_types.legacy.customer.advance_payment'
        );

        if (! is_string($code) || $code === '') {
            throw new RuntimeException(
                'Legacy customer advance payment transaction code is missing.'
            );
        }

        return $code;
    }

    public static function monthlySalaryCode(): string
    {
        return self::getCode('employee', 'monthly_salary');
    }

    public static function voucherTypes(): array
    {
        $types = config('app.erp.voucher.types');

        if (! is_array($types) || $types === []) {
            throw new RuntimeException('Voucher type ERP configuration is missing.');
        }

        return array_values($types);
    }

    public static function assignableVoucherTypes(): array
    {
        return [
            self::paymentVoucherType(),
            self::receiptVoucherType(),
        ];
    }

    public static function paymentVoucherType(): string
    {
        return self::getVoucherType('payment');
    }

    public static function receiptVoucherType(): string
    {
        return self::getVoucherType('receipt');
    }

    public static function bothVoucherType(): string
    {
        return self::getVoucherType('both');
    }

    public static function codePadding(): int
    {
        return (int) config('app.erp.voucher.transaction_types.code_padding', 4);
    }

    public static function permissionNames(): array
    {
        return [
            'voucher-transaction-type-view',
            'voucher-transaction-type-create',
            'voucher-transaction-type-update',
            'voucher-transaction-type-delete',
        ];
    }

    public static function isSystemIdentity(string $categoryCode, string $code): bool
    {
        foreach (self::flattenedSystemTypes() as $type) {
            if ($type['category_code'] === $categoryCode && $type['code'] === $code) {
                return true;
            }
        }

        return false;
    }

    private static function getVoucherType(string $key): string
    {
        $type = config("app.erp.voucher.types.{$key}");

        if (! is_string($type) || $type === '') {
            throw new RuntimeException("Voucher type ERP configuration [{$key}] is missing.");
        }

        return $type;
    }
}
