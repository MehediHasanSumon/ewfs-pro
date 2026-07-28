<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Group;

class PartyAccountService
{
    public function __construct(
        private readonly DocumentNumberService $numbers
    ) {
    }

    public function createCustomerAccount(string $name, bool $status = true): Account
    {
        return $this->create(
            $name,
            $this->resolveGroup(
                '100020001',
                'Customer Receivables',
                'asset',
                'debit',
                'receivable'
            ),
            $status
        );
    }

    public function createSupplierAccount(string $name, bool $status = true): Account
    {
        return $this->create(
            $name,
            $this->resolveGroup(
                '400010001',
                'Supplier Payables',
                'liability',
                'credit',
                'payable'
            ),
            $status
        );
    }

    public function createEmployeeAccount(string $name, bool $status = true): Account
    {
        return $this->create(
            $name,
            $this->resolveGroup(
                '40002',
                'Employee Accounts',
                'liability',
                'credit',
                'employee'
            ),
            $status
        );
    }

    private function create(string $name, Group $group, bool $status): Account
    {
        return Account::query()->create([
            'group_id' => $group->id,
            'ac_number' => $this->numbers->next('account', 'AC'),
            'name' => $name,
            'currency' => 'BDT',
            'is_control_account' => false,
            'allow_manual_posting' => true,
            'is_system' => false,
            'status' => $status,
        ]);
    }

    private function resolveGroup(
        string $preferredCode,
        string $name,
        string $accountClass,
        string $normalBalance,
        string $fallbackName
    ): Group {
        $group = Group::query()
            ->where('code', $preferredCode)
            ->first();

        if ($group) {
            return $group;
        }

        $group = Group::query()
            ->where('account_class', $accountClass)
            ->where('status', true)
            ->where('name', 'like', '%'.$fallbackName.'%')
            ->orderByDesc('is_system')
            ->orderBy('id')
            ->first();

        return $group ?? Group::query()->firstOrCreate(
            ['code' => 'SYS-'.strtoupper($fallbackName)],
            [
                'name' => $name,
                'account_class' => $accountClass,
                'normal_balance' => $normalBalance,
                'is_system' => true,
                'status' => true,
            ]
        );
    }
}
