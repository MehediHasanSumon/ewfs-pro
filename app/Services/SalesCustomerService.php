<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesCustomerService
{
    public function __construct(
        private readonly PartyAccountService $partyAccounts,
        private readonly DocumentNumberService $numbers
    ) {}

    public function lookup(string $mobile): ?Customer
    {
        $customer = $this->customerQuery($mobile)
            ->active()
            ->with([
                'vehicles' => fn ($query) => $query
                    ->where('vehicles.status', true)
                    ->orderBy('vehicles.vehicle_number')
                    ->select([
                        'vehicles.id',
                        'vehicles.customer_id',
                        'vehicles.vehicle_name',
                        'vehicles.vehicle_number',
                    ]),
            ])
            ->first();

        if (! $customer) {
            return null;
        }

        $customer->setAttribute('previous_due', $this->currentDue($customer));

        return $customer;
    }

    public function resolve(array $data): ?Customer
    {
        if (! empty($data['customer_id'])) {
            $customer = Customer::query()
                ->active()
                ->findOrFail($data['customer_id']);

            if (! $this->mobileMatches($customer, $data['customer_mobile'])) {
                throw ValidationException::withMessages([
                    'customer_mobile' => 'The mobile number does not match the selected customer.',
                ]);
            }

            return $customer;
        }

        $existingQuery = $this->customerQuery($data['customer_mobile']);

        if ($data['save_customer'] ?? false) {
            $existingQuery->lockForUpdate();
        }

        $existing = $existingQuery->first();

        if ($existing) {
            if (! $existing->status) {
                throw ValidationException::withMessages([
                    'customer_mobile' => 'The customer registered with this mobile number is inactive.',
                ]);
            }

            return $existing;
        }

        if (! ($data['save_customer'] ?? false)) {
            return null;
        }

        $account = $this->partyAccounts->createCustomerAccount(
            $data['customer_name'],
            true
        );

        return Customer::query()->create([
            'account_id' => $account->id,
            'code' => $this->numbers->next('customer', 'CC', null, 3),
            'name' => $data['customer_name'],
            'mobile' => $this->normalizeMobile($data['customer_mobile']),
            'address' => $data['customer_address'] ?? null,
            'discount_rate' => 0,
            'credit_limit' => 0,
            'credit_days' => 0,
            'status' => true,
        ]);
    }

    public function normalizeMobile(string $mobile): string
    {
        return preg_replace('/[\s\-()]+/', '', trim($mobile)) ?? trim($mobile);
    }

    public function mobileMatches(Customer $customer, string $mobile): bool
    {
        return $this->normalizeMobile((string) $customer->mobile)
            === $this->normalizeMobile($mobile);
    }

    private function customerQuery(string $mobile)
    {
        $raw = trim($mobile);
        $normalized = $this->normalizeMobile($mobile);

        return Customer::query()
            ->where(function ($query) use ($raw, $normalized) {
                $query->where('mobile', $raw);

                if ($normalized !== $raw) {
                    $query->orWhere('mobile', $normalized);
                }
            })
            ->orderByDesc('status')
            ->orderBy('id');
    }

    private function currentDue(Customer $customer): float
    {
        $balance = DB::table('journal_lines as jl')
            ->join('journal_entries as je', 'je.id', '=', 'jl.journal_entry_id')
            ->where('jl.account_id', $customer->account_id)
            ->whereIn('je.status', ['posted', 'reversed'])
            ->selectRaw(
                'COALESCE(SUM(jl.debit_amount - jl.credit_amount), 0) AS balance'
            )
            ->value('balance');

        return max(0, (float) $balance);
    }
}
