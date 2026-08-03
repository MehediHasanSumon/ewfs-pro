<?php

namespace App\Services;

use App\Models\Account;
use Illuminate\Validation\ValidationException;

class PaymentAccountService
{
    public function resolve(
        int $accountId,
        string $paymentType,
        string $errorKey
    ): Account {
        $account = Account::query()
            ->with('group:id,code,name,account_class,status')
            ->where('status', true)
            ->find($accountId);
        $allowedGroupCodes = config(
            "erp.accounting.payment_groups.{$paymentType}",
            config("erp.sales.payment_groups.{$paymentType}", [])
        );

        if (
            ! $account
            || ! $account->group
            || ! $account->group->status
            || $account->group->account_class !== 'asset'
            || ! in_array($account->group->code, $allowedGroupCodes, true)
        ) {
            throw ValidationException::withMessages([
                $errorKey => 'The selected account is not valid for this payment method.',
            ]);
        }

        return $account;
    }
}
