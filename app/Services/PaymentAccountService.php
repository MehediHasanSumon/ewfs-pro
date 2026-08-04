<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Group;
use Illuminate\Validation\ValidationException;

class PaymentAccountService
{
    public function resolve(
        int $accountId,
        string $paymentType,
        string $errorKey,
        ?int $groupId = null
    ): Account {
        $account = Account::query()
            ->with('group:id,code,name,account_class,status')
            ->where('status', true)
            ->find($accountId);
        $allowedGroupCodes = $this->groupCodesFor($paymentType);

        if (
            ! $account
            || ! $account->group
            || ! $account->group->status
            || $account->group->account_class !== 'asset'
            || ($groupId !== null && $account->group_id !== $groupId)
            || ! in_array($account->group->code, $allowedGroupCodes, true)
        ) {
            throw ValidationException::withMessages([
                $errorKey => 'The selected account is not valid for this payment method.',
            ]);
        }

        return $account;
    }

    public function formOptions(): array
    {
        $paymentGroups = $this->paymentGroups();
        $configuredGroupCodes = collect($paymentGroups)
            ->flatten()
            ->map(fn ($code) => (string) $code)
            ->unique()
            ->values();

        $groups = Group::query()
            ->active()
            ->where('account_class', 'asset')
            ->whereIn('code', $configuredGroupCodes)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $accounts = Account::query()
            ->active()
            ->whereIn('group_id', $groups->pluck('id'))
            ->orderBy('name')
            ->get(['id', 'group_id', 'name', 'ac_number']);

        return [
            'paymentMethods' => collect($paymentGroups)
                ->map(fn (array $codes, string $method) => [
                    'value' => $method,
                    'label' => $method,
                    'group_codes' => collect($codes)
                        ->map(fn ($code) => (string) $code)
                        ->values()
                        ->all(),
                ])
                ->values(),
            'paymentAccountGroups' => $groups,
            'paymentAccounts' => $accounts,
        ];
    }

    public function methods(): array
    {
        return array_keys($this->paymentGroups());
    }

    private function groupCodesFor(string $paymentType): array
    {
        return collect($this->paymentGroups()[$paymentType] ?? [])
            ->map(fn ($code) => (string) $code)
            ->values()
            ->all();
    }

    private function paymentGroups(): array
    {
        return config(
            'erp.accounting.payment_groups',
            config('erp.sales.payment_groups', [])
        );
    }
}
