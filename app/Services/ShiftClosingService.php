<?php

namespace App\Services;

use App\Helpers\ErpHelper;
use App\Models\CreditSale;
use App\Models\CreditSaleItem;
use App\Models\Dispenser;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\ShiftClosing;
use App\Models\VoucherLine;
use Illuminate\Support\Collection;
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
            $unrecordedFuelSales = 0.0;
            $recordedFuelSales = $this->recordedFuelSales(
                $data['transaction_date'],
                (int) $data['shift_id']
            );
            $fuelReadings = collect();
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

                $productReading = $fuelReadings->get(
                    $dispenser->product_id,
                    [
                        'product' => $dispenser->product,
                        'quantity' => 0.0,
                        'gross_amount' => 0.0,
                        'reading_ids' => [],
                    ]
                );
                $productReading['quantity'] += $netQuantity;
                $productReading['gross_amount'] += $grossAmount;
                $productReading['reading_ids'][] = $reading->id;
                $fuelReadings->put(
                    $dispenser->product_id,
                    $productReading
                );
            }

            foreach ($fuelReadings as $productId => $meterReading) {
                $recorded = $recordedFuelSales->get($productId, [
                    'quantity' => 0.0,
                    'inventory_quantity' => 0.0,
                    'amount' => 0.0,
                ]);
                $meterQuantity = round(
                    (float) $meterReading['quantity'],
                    6
                );
                $recordedQuantity = round(
                    (float) $recorded['quantity'],
                    6
                );
                $inventoryQuantity = round(
                    (float) $recorded['inventory_quantity'],
                    6
                );

                if ($recordedQuantity > $meterQuantity + 0.000001) {
                    throw ValidationException::withMessages([
                        'dispenser_readings' => 'Dispenser quantity cannot be less than already posted fuel sales.',
                    ]);
                }

                $varianceQuantity = round(
                    $meterQuantity - $inventoryQuantity,
                    6
                );
                $unitCost = (float) (
                    $meterReading['product']->activeRate?->purchase_price ?? 0
                );
                $varianceCost = round(
                    $varianceQuantity * $unitCost,
                    4
                );
                $unrecordedFuelSales += max(
                    0,
                    round(
                        (float) $meterReading['gross_amount']
                        - (float) $recorded['amount'],
                        4
                    )
                );

                if ($varianceQuantity <= 0) {
                    continue;
                }

                $movement = $this->inventory->record([
                    'product_id' => $productId,
                    'shift_id' => $closing->shift_id,
                    'business_date' => $closing->business_date,
                    'movement_type' => 'dispenser_reading',
                    'quantity_out' => $varianceQuantity,
                    'unit_cost' => $unitCost,
                    'total_cost' => $varianceCost,
                    'source_type' => ShiftClosing::class,
                    'source_id' => $closing->id,
                    'source_line_id' => $meterReading['reading_ids'][0] ?? null,
                    'idempotency_key' => 'shift-closing-fuel:'
                        .$closing->id.':'.$productId,
                    'remarks' => 'Dispenser meter quantity less inventory already issued by posted fuel sales.',
                ]);

                if ($meterReading['reading_ids'] !== []) {
                    $closing->dispenserReadings()
                        ->whereKey($meterReading['reading_ids'][0])
                        ->update([
                            'inventory_movement_id' => $movement->id,
                        ]);
                }

                $cogsTotal += $varianceCost;
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
                        'remarks' => 'Other product physical stock variance recorded during shift closing.',
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

            if ($unrecordedFuelSales > 0) {
                $cash = $this->systemAccounts->cashOnHand();
                $revenue = $this->systemAccounts->salesRevenue();
                $journalLines[] = [
                    'account_id' => $cash->id,
                    'debit_amount' => $unrecordedFuelSales,
                    'credit_amount' => 0,
                    'payment_method' => 'cash',
                    'description' => 'Unrecorded shift cash fuel sales',
                ];
                $journalLines[] = [
                    'account_id' => $revenue->id,
                    'debit_amount' => 0,
                    'credit_amount' => $unrecordedFuelSales,
                    'description' => 'Unrecorded shift cash fuel sales',
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
        $categoryCodes = ErpHelper::dispenserProductCategoryCodes();
        $bankMethods = ['bank', 'mobile_bank', 'cheque', 'online'];

        $legacyPaymentMethods = DB::table('journal_lines')
            ->select('journal_entry_id')
            ->selectRaw('MAX(payment_method) AS payment_method')
            ->where('debit_amount', '>', 0)
            ->whereNotNull('payment_method')
            ->groupBy('journal_entry_id');

        return (float) DB::table('sale_items AS si')
            ->join('sales AS s', 's.id', '=', 'si.sale_id')
            ->join('categories AS c', 'c.id', '=', 'si.category_id')
            ->join('journal_entries AS je', function ($join) {
                $join->on('je.id', '=', 's.journal_entry_id')
                    ->where('je.status', 'posted');
            })
            ->leftJoin('sale_payment_details AS spd', 'spd.sale_id', '=', 's.id')
            ->leftJoinSub($legacyPaymentMethods, 'jl_pay', fn ($join) => $join
                ->on('jl_pay.journal_entry_id', '=', 's.journal_entry_id'))
            ->whereDate('s.sale_date', $date)
            ->where('s.shift_id', $shiftId)
            ->where('s.sale_type', 'regular')
            ->when(
                $fuel,
                fn ($q) => $q->whereIn('c.code', $categoryCodes),
                fn ($q) => $q->whereNotIn('c.code', $categoryCodes)
            )
            ->whereRaw(
                "COALESCE(spd.payment_method, jl_pay.payment_method, 'cash') IN ("
                . implode(', ', array_map(fn ($m) => DB::getPdo()->quote($m), $bankMethods))
                . ')'
            )
            ->sum('si.line_total');
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

    private function recordedFuelSales(
        string $date,
        int $shiftId
    ): Collection {
        $categoryCodes = ErpHelper::dispenserProductCategoryCodes();
        $saleRows = DB::table('sale_items as si')
            ->join('sales as s', 's.id', '=', 'si.sale_id')
            ->join('categories as category', 'category.id', '=', 'si.category_id')
            ->join('journal_entries as je', function ($join) {
                $join->on('je.id', '=', 's.journal_entry_id')
                    ->where('je.status', 'posted');
            })
            ->whereDate('s.sale_date', $date)
            ->where('s.shift_id', $shiftId)
            ->whereIn('category.code', $categoryCodes)
            ->groupBy('si.product_id')
            ->selectRaw(
                'si.product_id,
                 SUM(si.quantity) AS quantity,
                 SUM(CASE WHEN EXISTS (
                     SELECT 1 FROM inventory_movements im
                     WHERE im.source_line_id = si.id
                       AND im.source_type = ?
                       AND im.reversal_of_id IS NULL
                 ) THEN si.quantity ELSE 0 END) AS inventory_quantity,
                 SUM(si.line_total) AS amount',
                [Sale::class]
            )
            ->get();
        $creditRows = DB::table('credit_sale_items as csi')
            ->join('credit_sale_customers as csc', 'csc.id', '=', 'csi.credit_sale_customer_id')
            ->join('credit_sales as cs', 'cs.id', '=', 'csc.credit_sale_id')
            ->join('categories as category', 'category.id', '=', 'csi.category_id')
            ->join('journal_entries as je', function ($join) {
                $join->on('je.id', '=', 'csc.journal_entry_id')
                    ->where('je.status', 'posted');
            })
            ->whereDate('cs.sale_date', $date)
            ->where('cs.shift_id', $shiftId)
            ->whereIn('category.code', $categoryCodes)
            ->groupBy('csi.product_id')
            ->selectRaw(
                'csi.product_id,
                 SUM(csi.quantity) AS quantity,
                 SUM(CASE WHEN EXISTS (
                     SELECT 1 FROM inventory_movements im
                     WHERE im.source_line_id = csi.id
                       AND im.source_type = ?
                       AND im.reversal_of_id IS NULL
                 ) THEN csi.quantity ELSE 0 END) AS inventory_quantity,
                 SUM(csi.line_total) AS amount',
                [CreditSale::class]
            )
            ->get();

        return $saleRows
            ->concat($creditRows)
            ->groupBy('product_id')
            ->map(fn ($rows) => [
                'quantity' => (float) $rows->sum('quantity'),
                'inventory_quantity' => (float) $rows
                    ->sum('inventory_quantity'),
                'amount' => (float) $rows->sum('amount'),
            ]);
    }
}
