<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Group;
use Illuminate\Support\Collection;
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

        return $this->validateAccount(
            $account,
            $paymentType,
            $errorKey,
            $groupId
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return Collection<int, Account>
     */
    public function resolveBatch(array $rows): Collection
    {
        $accounts = Account::query()
            ->with('group:id,code,name,account_class,status')
            ->where('status', true)
            ->whereIn(
                'id',
                collect($rows)
                    ->pluck('to_account_id')
                    ->map(fn ($id) => (int) $id)
                    ->filter()
                    ->unique()
            )
            ->get()
            ->keyBy('id');

        return collect($rows)
            ->values()
            ->map(fn (array $row, int $index) => $this->validateAccount(
                $accounts->get((int) ($row['to_account_id'] ?? 0)),
                (string) ($row['payment_type'] ?? ''),
                "rows.{$index}.to_account_id"
            ));
    }

    private function validateAccount(
        ?Account $account,
        string $paymentType,
        string $errorKey,
        ?int $groupId = null
    ): Account {
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

    public function methodFor(Account $account): ?string
    {
        $account->loadMissing('group');
        $group = $account->group;

        if (
            ! $account->status
            || ! $group
            || ! $group->status
            || $group->account_class !== 'asset'
        ) {
            return null;
        }

        foreach ($this->paymentGroups() as $method => $groupCodes) {
            if (
                in_array(
                    (string) $group->code,
                    array_map('strval', $groupCodes),
                    true
                )
            ) {
                return $method;
            }
        }

        return null;
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
