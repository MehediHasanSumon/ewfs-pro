<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Validation\ValidationException;

class SalesCustomerService
{
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

        return $customer;
    }

    public function resolve(array $data): ?Customer
    {
        if (! empty($data['customer_id'])) {
            $customer = Customer::query()
                ->active()
                ->with('account')
                ->lockForUpdate()
                ->findOrFail($data['customer_id']);

            if (! $this->mobileMatches($customer, $data['customer_mobile'])) {
                throw ValidationException::withMessages([
                    'customer_mobile' => 'The mobile number does not match the selected customer.',
                ]);
            }

            $this->updateEditableFields($customer, $data);

            return $customer;
        }

        $existing = $this->customerQuery($data['customer_mobile'])
            ->with('account')
            ->lockForUpdate()
            ->first();

        if ($existing) {
            if (! $existing->status) {
                throw ValidationException::withMessages([
                    'customer_mobile' => 'The customer registered with this mobile number is inactive.',
                ]);
            }

            $this->updateEditableFields($existing, $data);

            return $existing;
        }

        // Walk-in identity remains on the sale snapshots and has no party account.
        return null;
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

                $query->orWhereRaw(
                    "REPLACE(REPLACE(REPLACE(REPLACE(mobile, ' ', ''), '-', ''), '(', ''), ')', '') = ?",
                    [$normalized]
                );
            })
            ->orderByDesc('status')
            ->orderBy('id');
    }

    private function updateEditableFields(Customer $customer, array $data): void
    {
        $name = trim((string) ($data['customer_name'] ?? ''));

        if ($name === '' || $name === $customer->name) {
            return;
        }

        $customer->account?->update(['name' => $name]);
        $customer->update(['name' => $name]);
    }
}
