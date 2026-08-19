<?php

namespace App\Services;

use App\Helpers\NumberToWordsHelper;
use App\Models\Customer;
use App\Models\Vehicle;
use Illuminate\Support\Collection;

class CustomerDetailsShortSummaryService
{
    public function __construct(
        private readonly CustomerReportService $customerReports
    ) {
    }

    public function getShortSummary(
        string $startDate,
        string $endDate,
        int $customerId,
        ?int $vehicleId = null
    ): ?array {
        $customer = Customer::query()->find($customerId);
        if (! $customer) {
            return null;
        }

        $selectedVehicle = $vehicleId ? Vehicle::query()->find($vehicleId) : null;
        $sales = $this->customerReports->salesRows($startDate, $endDate, $customerId, $vehicleId);

        $productSummary = $sales
            ->groupBy(function (object $item) {
                $productKey = $item->product_id ?? trim($item->product_name);
                $rateKey = number_format((float) $item->price, 2, '.', '');
                $unitKey = trim(strtolower($item->unit_name ?? ''));

                return "{$productKey}_{$rateKey}_{$unitKey}";
            })
            ->map(function (Collection $items) {
                $first = $items->first();

                return [
                    'product_id' => $first->product_id ?? null,
                    'product_name' => $first->product_name,
                    'unit_name' => $first->unit_name,
                    'price' => (float) round((float) $first->price, 2),
                    'quantity' => (float) $items->sum('quantity'),
                    'total_amount' => (float) $items->sum('total_amount'),
                ];
            })
            ->values()
            ->map(function (array $item, int $index) {
                $item['sn'] = $index + 1;

                return $item;
            })
            ->all();

        $totalSlipQuantity = $sales->pluck('transaction_id')->unique()->filter()->count();
        $totalAmount = (float) collect($productSummary)->sum('total_amount');
        $totalQuantity = (float) collect($productSummary)->sum('quantity');
        $vatPercent = 0.00;
        $vatAmount = 0.00;
        $grandTotal = $totalAmount + $vatAmount;

        $words = NumberToWordsHelper::convert(floor($grandTotal));
        $amountInWords = ! empty($words) && $words !== 'Zero' ? $words . ' Taka Only' : 'Zero Taka Only';

        return [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'mobile' => $customer->mobile,
                'address' => $customer->address,
            ],
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'formatted' => "{$startDate} to {$endDate}",
            ],
            'selected_vehicle' => $selectedVehicle ? [
                'id' => $selectedVehicle->id,
                'vehicle_number' => $selectedVehicle->vehicle_number,
            ] : null,
            'product_summary' => $productSummary,
            'total_slip_quantity' => $totalSlipQuantity,
            'total' => $totalAmount,
            'total_quantity' => $totalQuantity,
            'vat_percent' => $vatPercent,
            'vat_amount' => $vatAmount,
            'grand_total' => $grandTotal,
            'amount_in_words' => $amountInWords,
        ];
    }
}
