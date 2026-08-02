<?php

namespace App\Models;

use App\Helpers\VoucherTransactionTypeHelper;

/**
 * @deprecated Use VoucherTransactionType.
 */
class PaymentSubType extends VoucherTransactionType
{
    protected $table = 'voucher_transaction_types';

    public static function customerRefundGivenCode(): string
    {
        return VoucherTransactionTypeHelper::customerSecurityDepositRefundCode();
    }
}
