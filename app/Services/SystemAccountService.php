<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Group;
use Illuminate\Support\Str;

class SystemAccountService
{
    public function __construct(
        private readonly DocumentNumberService $numbers
    ) {}

    public function salesRevenue(): Account
    {
        return $this->resolve('sales_revenue', 'Sales Revenue', 'revenue', 'credit');
    }

    public function inventoryAsset(): Account
    {
        return $this->resolve('inventory_asset', 'Inventory Asset', 'asset', 'debit');
    }

    public function costOfGoodsSold(): Account
    {
        return $this->resolve('cost_of_goods_sold', 'Cost of Goods Sold', 'expense', 'debit');
    }

    public function officeExpense(): Account
    {
        return $this->resolve('office_expense', 'Office Expense', 'expense', 'debit');
    }

    public function payrollSalaryExpense(): Account
    {
        return $this->resolve(
            'payroll_salary_expense',
            'Payroll Salary Expense',
            'expense',
            'debit'
        );
    }

    public function payrollAdvanceAdjustment(): Account
    {
        return $this->payrollSalaryExpense();
    }

    public function inventoryAdjustment(): Account
    {
        return $this->resolve(
            'inventory_adjustment',
            'Inventory Adjustment',
            'expense',
            'debit'
        );
    }

    public function bankChargeExpense(): Account
    {
        $existing = Account::query()
            ->whereHas('group', fn ($query) => $query
                ->where('account_class', 'expense')
                ->where(fn ($q) => $q
                    ->where('name', 'like', '%bank charge%')
                    ->orWhere('name', 'like', '%bank fee%')
                    ->orWhere('name', 'like', '%transfer fee%')))
            ->where('status', true)
            ->orderBy('id')
            ->first();

        return $existing ?? $this->resolve('bank_charge_expense', 'Bank Charges and Fees', 'expense', 'debit');
    }

    public function cashOnHand(): Account
    {
        $cash = Account::query()
            ->whereHas('group', fn ($query) => $query
                ->where('account_class', 'asset')
                ->where('name', 'like', '%cash%'))
            ->where('status', true)
            ->orderBy('id')
            ->first();

        return $cash ?? $this->resolve('cash_on_hand', 'Cash on Hand', 'asset', 'debit');
    }

    public function openingBalanceEquity(): Account
    {
        return $this->resolve('opening_balance_equity', 'Opening Balance Equity', 'equity', 'credit');
    }

    private function resolve(
        string $semanticCode,
        string $name,
        string $accountClass,
        string $normalBalance
    ): Account {
        $existing = Account::query()
            ->where('semantic_code', $semanticCode)
            ->first();

        if ($existing) {
            return $existing;
        }

        $group = Group::query()
            ->where('account_class', $accountClass)
            ->where('status', true)
            ->orderByDesc('is_system')
            ->orderBy('id')
            ->first();

        if (! $group) {
            $group = Group::query()->create([
                'code' => 'SYS-'.Str::upper($accountClass),
                'name' => 'System '.Str::headline($accountClass),
                'account_class' => $accountClass,
                'normal_balance' => $normalBalance,
                'is_system' => true,
                'status' => true,
            ]);
        }

        return Account::query()->firstOrCreate(
            ['semantic_code' => $semanticCode],
            [
                'group_id' => $group->id,
                'ac_number' => $this->numbers->next('account', 'AC'),
                'name' => $name,
                'currency' => 'BDT',
                'is_control_account' => true,
                'allow_manual_posting' => false,
                'is_system' => true,
                'status' => true,
            ]
        );
    }
}
