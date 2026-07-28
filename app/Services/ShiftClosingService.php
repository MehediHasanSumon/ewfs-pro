<?php

namespace App\Services;

use App\Models\CreditSaleItem;
use App\Models\Dispenser;
use App\Models\Product;
use App\Models\SaleItem;
use App\Models\ShiftClosing;
use App\Models\VoucherLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShiftClosingService
{
    public function __construct(
        private readonly AccountingService $accounting,
        private readonly InventoryService $inventory,
        private readonly SystemAccountService $systemAccounts
    ) {
    }

    public function close(array $data): ShiftClosing
    {
        return DB::transaction(function () use ($data) {
            $existing = ShiftClosing::query()
                ->whereDate('business_date', $data['transaction_date'])
                ->where('shift_id', $data['shift_id'])
                ->lockForUpdate()
                ->first();

            if ($existing) {
                throw ValidationException::withMessages([
                    'shift_id' => 'This shift is already closed for the selected date.',
                ]);
            }

            $closing = ShiftClosing::query()->create([
                'business_date' => $data['transaction_date'],
                'shift_id' => $data['shift_id'],
                'status' => 'draft',
                'expected_cash' => max(0, (float) $data['total_cash']),
                'actual_cash' => max(0, (float) $data['final_due_amount']),
                'variance_amount' => 0,
                'created_by' => auth()->id(),
                'remarks' => $data['remarks'] ?? null,
            ]);

            $cogsTotal = 0.0;

            foreach ($data['dispenser_readings'] as $readingData) {
                $dispenser = Dispenser::query()
                    ->with(['product.activeRate'])
                    ->findOrFail($readingData['dispenser_id']);

                if ((int) $dispenser->product_id !== (int) $readingData['product_id']) {
                    throw ValidationException::withMessages([
                        'dispenser_readings' => 'The selected product does not belong to its dispenser.',
                    ]);
                }

                $netQuantity = max(
                    0,
                    (float) $readingData['end_reading']
                    - (float) $readingData['start_reading']
                    - (float) ($readingData['meter_test'] ?? 0)
                );
                $unitPrice = (float) $readingData['item_rate'];
                $grossAmount = round($netQuantity * $unitPrice, 4);
                $unitCost = (float) ($dispenser->product->activeRate?->purchase_price ?? 0);
                $totalCost = round($netQuantity * $unitCost, 4);

                $reading = $closing->dispenserReadings()->create([
                    'dispenser_id' => $dispenser->id,
                    'product_id' => $dispenser->product_id,
                    'employee_id' => $readingData['reading_by'] ?? null,
                    'start_reading' => $readingData['start_reading'],
                    'end_reading' => $readingData['end_reading'],
                    'meter_test' => $readingData['meter_test'] ?? 0,
                    'net_quantity' => $netQuantity,
                    'unit_price' => $unitPrice,
                    'gross_amount' => $grossAmount,
                ]);

                if ($netQuantity > 0) {
                    $movement = $this->inventory->record([
                        'product_id' => $dispenser->product_id,
                        'shift_id' => $closing->shift_id,
                        'business_date' => $closing->business_date,
                        'movement_type' => 'dispenser_reading',
                        'quantity_out' => $netQuantity,
                        'unit_cost' => $unitCost,
                        'total_cost' => $totalCost,
                        'source_type' => ShiftClosing::class,
                        'source_id' => $closing->id,
                        'source_line_id' => $reading->id,
                        'idempotency_key' => 'shift-closing-reading:'.$reading->id,
                    ]);

                    $reading->update(['inventory_movement_id' => $movement->id]);
                    $cogsTotal += $totalCost;
                }
            }

            foreach ($data['other_product_sales'] ?? [] as $itemData) {
                $quantity = (float) ($itemData['quantity'] ?? 0);
                if ($quantity <= 0) {
                    continue;
                }

                $product = Product::query()
                    ->with(['unit', 'activeRate'])
                    ->findOrFail($itemData['product_id']);
                $unitPrice = (float) $itemData['unit_price'];
                $lineTotal = round($quantity * $unitPrice, 4);
                $unitCost = (float) ($product->activeRate?->purchase_price ?? 0);
                $totalCost = round($quantity * $unitCost, 4);

                $item = $closing->productItems()->create([
                    'product_id' => $product->id,
                    'unit_id' => $product->unit_id,
                    'employee_id' => $itemData['employee_id'],
                    'product_name_snapshot' => $product->product_name,
                    'unit_name_snapshot' => $product->unit->name,
                    'unit_price' => $unitPrice,
                    'quantity' => $quantity,
                    'line_total' => $lineTotal,
                ]);

                if ($product->is_inventory_item) {
                    $movement = $this->inventory->record([
                        'product_id' => $product->id,
                        'shift_id' => $closing->shift_id,
                        'business_date' => $closing->business_date,
                        'movement_type' => 'shift_other_product_sale',
                        'quantity_out' => $quantity,
                        'unit_cost' => $unitCost,
                        'total_cost' => $totalCost,
                        'source_type' => ShiftClosing::class,
                        'source_id' => $closing->id,
                        'source_line_id' => $item->id,
                        'idempotency_key' => 'shift-closing-product:'.$item->id,
                    ]);

                    $item->update(['inventory_movement_id' => $movement->id]);
                    $cogsTotal += $totalCost;
                }
            }

            $cashRevenue = round(
                (float) $data['cash_sales'] + (float) $data['cash_sales_other'],
                4
            );
            $journalLines = [];

            if ($cashRevenue > 0) {
                $cash = $this->systemAccounts->cashOnHand();
                $revenue = $this->systemAccounts->salesRevenue();
                $journalLines[] = [
                    'account_id' => $cash->id,
                    'debit_amount' => $cashRevenue,
                    'credit_amount' => 0,
                    'payment_method' => 'cash',
                    'description' => 'Shift cash sales',
                ];
                $journalLines[] = [
                    'account_id' => $revenue->id,
                    'debit_amount' => 0,
                    'credit_amount' => $cashRevenue,
                    'description' => 'Shift cash sales',
                ];
            }

            if ($cogsTotal > 0) {
                $cogs = $this->systemAccounts->costOfGoodsSold();
                $inventory = $this->systemAccounts->inventoryAsset();
                $journalLines[] = [
                    'account_id' => $cogs->id,
                    'debit_amount' => $cogsTotal,
                    'credit_amount' => 0,
                    'description' => 'Shift inventory cost',
                ];
                $journalLines[] = [
                    'account_id' => $inventory->id,
                    'debit_amount' => 0,
                    'credit_amount' => $cogsTotal,
                    'description' => 'Shift inventory cost',
                ];
            }

            $journal = null;
            if ($journalLines !== []) {
                $journal = $this->accounting->post([
                    'shift_id' => $closing->shift_id,
                    'business_date' => $closing->business_date,
                    'event_type' => 'shift_closing',
                    'source_type' => ShiftClosing::class,
                    'source_id' => $closing->id,
                    'reference_no' => 'SHIFT-'.$closing->id,
                    'description' => 'Shift closing',
                    'idempotency_key' => 'shift-closing:'.$closing->id,
                ], $journalLines);
            }

            $closing->summary()->create([
                'fuel_sales' => collect($data['dispenser_readings'])->sum('total_sale'),
                'other_product_sales' => collect($data['other_product_sales'] ?? [])->sum(
                    fn (array $item) => (float) $item['quantity'] * (float) $item['unit_price']
                ),
                'credit_sales' => (float) $data['credit_sales'] + (float) $data['credit_sales_other'],
                'bank_sales' => (float) $data['bank_sales'] + (float) $data['bank_sales_other'],
                'cash_sales' => (float) $data['cash_sales'] + (float) $data['cash_sales_other'],
                'cash_receipts' => $data['cash_receive'],
                'bank_receipts' => $data['bank_receive'] ?? 0,
                'cash_payments' => $data['cash_payment'],
                'bank_payments' => $data['bank_payment'] ?? 0,
                'office_payments' => $data['office_payment'],
                'expected_cash' => $closing->expected_cash,
                'actual_cash' => $closing->actual_cash,
                'variance_amount' => (float) $closing->expected_cash - (float) $closing->actual_cash,
                'refreshed_at' => now(),
            ]);

            $closing->update([
                'journal_entry_id' => $journal?->id,
                'status' => 'posted',
                'closed_by' => auth()->id(),
                'closed_at' => now(),
                'lock_version' => $closing->lock_version + 1,
            ]);

            return $closing->fresh([
                'shift',
                'summary',
                'dispenserReadings.dispenser',
                'dispenserReadings.product',
                'productItems.product',
                'productItems.unit',
            ]);
        });
    }

    public function operationalSummary(string $date, int $shiftId): array
    {
        $creditFuel = (float) CreditSaleItem::query()
            ->whereHas('customerAllocation.creditSale', fn ($sale) => $sale
                ->whereDate('sale_date', $date)
                ->where('shift_id', $shiftId))
            ->whereHas('customerAllocation.journalEntry', fn ($entry) => $entry->posted())
            ->whereHas('category', fn ($category) => $category->where('inventory_class', 'fuel'))
            ->sum('line_total');

        $creditOther = (float) CreditSaleItem::query()
            ->whereHas('customerAllocation.creditSale', fn ($sale) => $sale
                ->whereDate('sale_date', $date)
                ->where('shift_id', $shiftId))
            ->whereHas('customerAllocation.journalEntry', fn ($entry) => $entry->posted())
            ->whereHas('category', fn ($category) => $category->where('inventory_class', '!=', 'fuel'))
            ->sum('line_total');

        $bankFuel = $this->bankSales($date, $shiftId, true);
        $bankOther = $this->bankSales($date, $shiftId, false);

        return [
            'getTotalSummeryReport' => [[
                'total_credit_sales_amount' => $creditFuel,
                'total_bank_sale_amount' => $bankFuel,
                'total_cash_receive_amount' => $this->voucherTotal($date, $shiftId, 'receipt'),
                'total_cash_payment_amount' => $this->voucherTotal($date, $shiftId, 'payment'),
                'total_office_payment_amount' => $this->voucherTotal($date, $shiftId, 'office_payment'),
                'total_credit_sales_other_amount' => $creditOther,
                'total_bank_sales_other_amount' => $bankOther,
            ]],
            'getCreditSalesDetailsReport' => CreditSaleItem::query()
                ->whereHas('customerAllocation.creditSale', fn ($sale) => $sale
                    ->whereDate('sale_date', $date)
                    ->where('shift_id', $shiftId))
                ->whereHas('customerAllocation.journalEntry', fn ($entry) => $entry->posted())
                ->select('product_id', DB::raw('SUM(line_total) as product_wise_credit_sales'))
                ->groupBy('product_id')
                ->get(),
        ];
    }

    public function reverse(ShiftClosing $closing): void
    {
        throw ValidationException::withMessages([
            'shift' => 'Posted shift closings require an explicit controlled reversal workflow.',
        ]);
    }

    private function bankSales(string $date, int $shiftId, bool $fuel): float
    {
        return (float) SaleItem::query()
            ->whereHas('sale', fn ($sale) => $sale
                ->whereDate('sale_date', $date)
                ->where('shift_id', $shiftId)
                ->where('sale_type', 'regular')
                ->whereHas('journalEntry', fn ($entry) => $entry->posted())
                ->whereHas('transaction', fn ($line) => $line
                    ->whereIn('payment_method', ['bank', 'mobile_bank', 'cheque', 'online'])))
            ->whereHas('category', fn ($category) => $category
                ->where('inventory_class', $fuel ? '=' : '!=', 'fuel'))
            ->sum('line_total');
    }

    private function voucherTotal(string $date, int $shiftId, string $type): float
    {
        return (float) VoucherLine::query()
            ->where('entry_side', 'debit')
            ->whereHas('voucher', fn ($voucher) => $voucher
                ->where('voucher_type', $type)
                ->whereDate('voucher_date', $date)
                ->where('shift_id', $shiftId)
                ->whereHas('journalEntry', fn ($entry) => $entry->posted()))
            ->sum('amount');
    }
}
