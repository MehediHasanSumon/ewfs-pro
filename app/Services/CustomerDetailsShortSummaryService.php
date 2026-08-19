<?php

namespace App\Services;

use App\Helpers\NumberToWordsHelper;
use App\Models\Customer;
use App\Models\Vehicle;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CustomerDetailsShortSummaryService
{
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
        $sales = $this->salesRows($startDate, $endDate, $customerId, $vehicleId);

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

    private function salesRows(
        string $startDate,
        string $endDate,
        int $customerId,
        ?int $vehicleId = null
    ): Collection {
        $rates = Schema::hasTable('product_rates')
            ? DB::table('product_rates')
                ->where('status', true)
                ->where('effective_date', '<=', $endDate)
                ->orderBy('effective_date')
                ->orderBy('id')
                ->get()
            : collect();

        return DB::table('credit_sale_items as csi')
            ->join('credit_sale_customers as csc', 'csc.id', '=', 'csi.credit_sale_customer_id')
            ->join('credit_sales as cs', 'cs.id', '=', 'csc.credit_sale_id')
            ->join('customers as c', 'c.id', '=', 'csc.customer_id')
            ->join('journal_entries as je', function ($join) {
                $join->on('je.id', '=', 'csc.journal_entry_id')
                    ->where('je.status', 'posted');
            })
            ->leftJoin('vehicles as vehicle', 'vehicle.id', '=', 'csi.vehicle_id')
            ->whereBetween('cs.sale_date', [$startDate, $endDate])
            ->where('c.id', $customerId)
            ->when($vehicleId, fn ($query) => $query
                ->where('csi.vehicle_id', $vehicleId))
            ->orderBy('cs.sale_date')
            ->orderBy('cs.id')
            ->orderBy('csi.line_no')
            ->select([
                'csi.id',
                'csi.vehicle_id',
                'csi.product_id',
                'c.id as customer_id',
                'c.name as customer_name',
                'c.mobile as customer_mobile',
                'c.address as customer_address',
                'cs.sale_date',
                DB::raw(
                    'COALESCE(csi.vehicle_number_snapshot, vehicle.vehicle_number) AS vehicle_number'
                ),
                'cs.invoice_no',
                'cs.memo_no',
                'cs.invoice_no as transaction_id',
                'csi.product_name_snapshot as product_name',
                'csi.unit_name_snapshot as unit_name',
                'csi.unit_price as price',
                'csi.quantity',
                'csi.line_total as total_amount',
            ])
            ->get()
            ->map(function (object $sale) use ($rates) {
                $sale->memo_no = ! empty($sale->memo_no) ? (string) $sale->memo_no : 'N/A';
                $rawPrice = (float) $sale->price;

                if (! empty($sale->product_id)) {
                    $matchedRate = $rates
                        ->where('product_id', $sale->product_id)
                        ->where('effective_date', '<=', $sale->sale_date)
                        ->last();

                    if ($matchedRate && abs($rawPrice - (float) $matchedRate->sales_price) < 0.15) {
                        $rawPrice = (float) $matchedRate->sales_price;
                    }
                }

                $sale->price = (float) round($rawPrice, 2);
                $sale->quantity = (float) $sale->quantity;
                $sale->total_amount = (float) $sale->total_amount;

                return $sale;
            });
    }
}
