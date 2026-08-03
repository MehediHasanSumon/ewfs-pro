<?php

namespace App\Services;

use App\Models\CreditSaleItem;
use App\Models\Dispenser;
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
        private readonly SystemAccountService $systemAccounts,
        private readonly DispenserCalculationService $calculations
    ) {}

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

            $otherProductLines = $this->calculations->resolveForShiftClosing(
                $data['transaction_date'],
                (int) $data['shift_id'],
                $data['other_product_sales'] ?? []
            );
            $otherSummary = $this->calculations->otherProductSalesSummary(
                $data['transaction_date'],
                (int) $data['shift_id']
            );
            $operational = $this->operationalSummary(
                $data['transaction_date'],
                (int) $data['shift_id'],
                $otherSummary
            )['getTotalSummeryReport'][0];
            $creditFuel = (float) $operational['total_credit_sales_amount'];
            $bankFuel = (float) $operational['total_bank_sale_amount'];
            $creditOther = (float) $otherSummary['credit_sales'];
            $bankOther = (float) $otherSummary['bank_sales'];
            $cashOther = (float) $otherSummary['cash_sales'];
            $cashReceipts = (float) $operational['total_cash_receive_amount'];
            $cashPayments = (float) $operational['total_cash_payment_amount'];
            $officePayments = (float) $operational['total_office_payment_amount'];
            $otherProductSales = (float) $otherSummary['total_sales'];

            $dispenserIds = collect($data['dispenser_readings'])
                ->pluck('dispenser_id')
                ->map(fn ($id) => (int) $id);
            $dispensers = Dispenser::query()
                ->with(['product.activeRate'])
                ->whereIn('id', $dispenserIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($dispensers->count() !== $dispenserIds->unique()->count()) {
                throw ValidationException::withMessages([
                    'dispenser_readings' => 'One or more selected dispensers are unavailable.',
                ]);
            }

            $fuelSales = 0.0;
            $closing = ShiftClosing::query()->create([
                'business_date' => $data['transaction_date'],
                'shift_id' => $data['shift_id'],
                'status' => 'draft',
                'expected_cash' => 0,
                'actual_cash' => 0,
                'variance_amount' => 0,
                'created_by' => auth()->id(),
                'remarks' => $data['remarks'] ?? null,
            ]);

            $cogsTotal = 0.0;
            $inventoryAdjustments = [];

            foreach ($data['dispenser_readings'] as $readingData) {
                $dispenser = $dispensers->get(
                    (int) $readingData['dispenser_id']
                );

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
                $unitPrice = (float) (
                    $dispenser->product->activeRate?->sales_price ?? 0
                );
                $grossAmount = round($netQuantity * $unitPrice, 4);
                $unitCost = (float) ($dispenser->product->activeRate?->purchase_price ?? 0);
                $totalCost = round($netQuantity * $unitCost, 4);
                $fuelSales += $grossAmount;

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

            foreach ($otherProductLines as $line) {
                $product = $line['product'];

                $item = $closing->productItems()->create([
                    'product_id' => $product->id,
                    'unit_id' => $product->unit_id,
                    'employee_id' => $line['employee_id'],
                    'product_name_snapshot' => $product->product_name,
                    'unit_name_snapshot' => $product->unit->name,
                    'unit_price' => $line['unit_price'],
                    'quantity' => $line['quantity'],
                    'recorded_quantity' => $line['recorded_quantity'],
                    'quantity_variance' => $line['quantity_variance'],
                    'line_total' => $line['line_total'],
                ]);

                if (
                    $product->is_inventory_item
                    && abs($line['quantity_variance']) > 0.0000001
                ) {
                    $varianceOut = max(0, $line['quantity_variance']);
                    $varianceIn = max(0, -$line['quantity_variance']);
                    $movementData = [
                        'product_id' => $product->id,
                        'shift_id' => $closing->shift_id,
                        'business_date' => $closing->business_date,
                        'movement_type' => $varianceOut > 0
                            ? 'shift_other_product_variance_out'
                            : 'shift_other_product_variance_in',
                        'quantity_in' => $varianceIn,
                        'quantity_out' => $varianceOut,
                        'unit_cost' => $line['unit_cost'],
                        'total_cost' => $line['total_cost'],
                        'source_type' => ShiftClosing::class,
                        'source_id' => $closing->id,
                        'source_line_id' => $item->id,
                        'idempotency_key' => 'shift-closing-product:'.$item->id,
                    ];
                    $movement = $this->inventory->record($movementData);

                    $item->update(['inventory_movement_id' => $movement->id]);
                    $inventoryAdjustments[] = [
                        'product_id' => $product->id,
                        'amount' => $line['total_cost'],
                        'is_stock_loss' => $varianceOut > 0,
                    ];
                }
            }

            $fuelSales = round($fuelSales, 4);

            if ($creditFuel + $bankFuel > $fuelSales) {
                throw ValidationException::withMessages([
                    'dispenser_readings' => 'Fuel credit and bank sales cannot exceed total dispenser sales.',
                ]);
            }

            $cashFuel = round($fuelSales - $creditFuel - $bankFuel, 4);
            $cashSales = round($cashFuel + $cashOther, 4);
            $expectedCash = round($cashSales + $cashReceipts, 4);
            $actualCash = max(
                0,
                round(
                    $expectedCash - $cashPayments - $officePayments,
                    4
                )
            );
            $journalLines = [];

            if ($cashFuel > 0) {
                $cash = $this->systemAccounts->cashOnHand();
                $revenue = $this->systemAccounts->salesRevenue();
                $journalLines[] = [
                    'account_id' => $cash->id,
                    'debit_amount' => $cashFuel,
                    'credit_amount' => 0,
                    'payment_method' => 'cash',
                    'description' => 'Shift cash fuel sales',
                ];
                $journalLines[] = [
                    'account_id' => $revenue->id,
                    'debit_amount' => 0,
                    'credit_amount' => $cashFuel,
                    'description' => 'Shift cash fuel sales',
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

            if ($inventoryAdjustments !== []) {
                $inventory = $this->systemAccounts->inventoryAsset();
                $adjustment = $this->systemAccounts->inventoryAdjustment();

                foreach ($inventoryAdjustments as $inventoryAdjustment) {
                    $amount = (float) $inventoryAdjustment['amount'];

                    if ($amount <= 0) {
                        continue;
                    }

                    if ($inventoryAdjustment['is_stock_loss']) {
                        $journalLines[] = [
                            'account_id' => $adjustment->id,
                            'debit_amount' => $amount,
                            'credit_amount' => 0,
                            'product_id' => $inventoryAdjustment['product_id'],
                            'description' => 'Other product physical stock shortage',
                        ];
                        $journalLines[] = [
                            'account_id' => $inventory->id,
                            'debit_amount' => 0,
                            'credit_amount' => $amount,
                            'product_id' => $inventoryAdjustment['product_id'],
                            'description' => 'Other product physical stock shortage',
                        ];

                        continue;
                    }

                    $journalLines[] = [
                        'account_id' => $inventory->id,
                        'debit_amount' => $amount,
                        'credit_amount' => 0,
                        'product_id' => $inventoryAdjustment['product_id'],
                        'description' => 'Other product physical stock surplus',
                    ];
                    $journalLines[] = [
                        'account_id' => $adjustment->id,
                        'debit_amount' => 0,
                        'credit_amount' => $amount,
                        'product_id' => $inventoryAdjustment['product_id'],
                        'description' => 'Other product physical stock surplus',
                    ];
                }
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

            $closing->update([
                'expected_cash' => $expectedCash,
                'actual_cash' => $actualCash,
            ]);

            $closing->summary()->create([
                'fuel_sales' => $fuelSales,
                'other_product_sales' => $otherProductSales,
                'credit_sales' => $creditFuel + $creditOther,
                'bank_sales' => $bankFuel + $bankOther,
                'cash_sales' => $cashSales,
                'cash_receipts' => $cashReceipts,
                'bank_receipts' => 0,
                'cash_payments' => $cashPayments,
                'bank_payments' => 0,
                'office_payments' => $officePayments,
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

    public function operationalSummary(
        string $date,
        int $shiftId,
        ?array $otherProductSummary = null
    ): array {
        $otherProductSummary ??= $this->calculations
            ->otherProductSalesSummary($date, $shiftId);
        $creditFuel = (float) CreditSaleItem::query()
            ->whereHas('customerAllocation.creditSale', fn ($sale) => $sale
                ->whereDate('sale_date', $date)
                ->where('shift_id', $shiftId))
            ->whereHas('customerAllocation.journalEntry', fn ($entry) => $entry->posted())
            ->whereHas('category', fn ($category) => $category
                ->allowedForDispenser())
            ->sum('line_total');

        $bankFuel = $this->bankSales($date, $shiftId, true);

        return [
            'getTotalSummeryReport' => [[
                'total_credit_sales_amount' => $creditFuel,
                'total_bank_sale_amount' => $bankFuel,
                'total_cash_receive_amount' => $this->voucherTotal($date, $shiftId, 'receipt'),
                'total_cash_payment_amount' => $this->voucherTotal($date, $shiftId, 'payment'),
                'total_office_payment_amount' => $this->voucherTotal($date, $shiftId, 'office_payment'),
                'total_credit_sales_other_amount' => $otherProductSummary['credit_sales'],
                'total_bank_sales_other_amount' => $otherProductSummary['bank_sales'],
                'total_cash_sales_other_amount' => $otherProductSummary['cash_sales'],
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
            ->whereHas(
                'category',
                fn ($category) => $fuel
                    ? $category->allowedForDispenser()
                    : $category->otherForDispenser()
            )
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
