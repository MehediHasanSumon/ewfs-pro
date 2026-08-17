<?php

namespace App\Services;

use App\Models\Account;
use App\Models\CreditSale;
use App\Models\FundTransfer;
use App\Models\JournalEntry;
use App\Models\Sale;
use App\Models\ShiftClosing;
use App\Models\Voucher;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DailyStatementReportService
{
    public function __construct(
        private readonly LedgerQueryService $ledger,
        private readonly SystemAccountService $systemAccounts
    ) {}

    public function report(
        string $startDate,
        string $endDate,
        ?int $shiftId = null
    ): array {
        $cashAccountIds = $this->ledger->cashAccountIds();
        $bankAccountIds = $this->ledger->bankAccountIds();

        // 1. Product Wise Sales
        $productWiseSales = $this->productWiseSales($startDate, $endDate, $shiftId);

        // 2. Customer & Vehicle Wise Credit Sales Detail
        $customerWiseSales = $this->customerWiseSales($startDate, $endDate, $shiftId);

        // 3. Credit Sales product-level summary
        $creditSalesSummary = $productWiseSales
            ->filter(fn (array $p) => (float) $p['credit_amount'] > 0 || (float) $p['credit_quantity'] > 0)
            ->map(fn (array $p) => (object) [
                'product_id' => $p['product_id'],
                'product_name' => $p['product_name'],
                'unit_name' => $p['unit_name'],
                'unit_price' => (float) $p['unit_price'],
                'total_quantity' => (float) $p['credit_quantity'],
                'total_amount' => (float) $p['credit_amount'],
            ])
            ->values();

        // 4. Cash & Bank product-level summary (Legacy compatibility)
        $cashBankSalesSummary = $productWiseSales
            ->filter(fn (array $p) => ((float) $p['cash_amount'] + (float) $p['bank_amount']) > 0)
            ->map(fn (array $p) => (object) [
                'product_id' => $p['product_id'],
                'product_name' => $p['product_name'],
                'unit_name' => $p['unit_name'],
                'unit_price' => (float) $p['unit_price'],
                'total_quantity' => (float) $p['cash_quantity'] + (float) $p['bank_quantity'],
                'total_amount' => (float) $p['cash_amount'] + (float) $p['bank_amount'],
            ])
            ->values();

        // 5. Operational Vouchers
        $cashReceivedVouchers = $this->voucherRows('receipt', $startDate, $endDate, $shiftId);
        $cashPaymentVouchers = $this->voucherRows('payment', $startDate, $endDate, $shiftId);

        // 6. Cash Flow Breakdown (Opening, Inflows, Outflows, Closing)
        $cashFlow = $this->cashFlowBreakdown($cashAccountIds, $startDate, $endDate, $shiftId);

        // 7. Bank Flow Breakdown (Opening, Inflows, Outflows, Closing)
        $bankFlow = $this->bankFlowBreakdown($bankAccountIds, $startDate, $endDate, $shiftId);

        // 8. Totals & Executive Summary
        $totalSales = round((float) $productWiseSales->sum('total_amount'), 4);
        $totalCashSales = round((float) $productWiseSales->sum('cash_amount'), 4);
        $totalBankSales = round((float) $productWiseSales->sum('bank_amount'), 4);
        $totalCreditSales = round((float) $productWiseSales->sum('credit_amount'), 4);

        $executiveSummary = [
            'total_sales' => $totalSales,
            'cash_sales' => $totalCashSales,
            'bank_sales' => $totalBankSales,
            'credit_sales' => $totalCreditSales,
            'opening_cash' => $cashFlow['opening_balance'],
            'total_cash_receipts' => $cashFlow['total_receipts'],
            'total_cash_payments' => $cashFlow['total_payments'],
            'net_cash_movement' => $cashFlow['net_movement'],
            'closing_cash' => $cashFlow['closing_balance'],
            'opening_bank' => $bankFlow['opening_balance'],
            'total_bank_receipts' => $bankFlow['total_receipts'],
            'total_bank_payments' => $bankFlow['total_payments'],
            'net_bank_movement' => $bankFlow['net_movement'],
            'closing_bank' => $bankFlow['closing_balance'],
            'total_expenses' => round($cashFlow['expenses'] + $bankFlow['expenses'], 4),
        ];

        return [
            'productWiseSales' => $productWiseSales->all(),
            'cashBankSales' => $cashBankSalesSummary,
            'creditSales' => $creditSalesSummary,
            'customerWiseSales' => $customerWiseSales,
            'cashReceived' => $cashReceivedVouchers,
            'cashPayment' => $cashPaymentVouchers,
            'bankReceived' => $bankFlow['receipts'],
            'bankPayment' => $bankFlow['payments'],
            'cashFlow' => $cashFlow,
            'bankFlow' => $bankFlow,
            'summary' => $executiveSummary,
        ];
    }

    private function productWiseSales(
        string $startDate,
        string $endDate,
        ?int $shiftId
    ): Collection {
        // A. POS Regular & White Sales
        $legacyPaymentMethods = DB::table('journal_lines')
            ->select('journal_entry_id')
            ->selectRaw('MAX(payment_method) AS payment_method')
            ->where('debit_amount', '>', 0)
            ->whereNotNull('payment_method')
            ->groupBy('journal_entry_id');

        $hasSalePaymentDetails = Schema::hasTable('sale_payment_details');
        $hasSales = Schema::hasTable('sales') && Schema::hasTable('sale_items');

        $posSales = collect();
        if ($hasSales) {
            $posQuery = DB::table('sale_items as si')
                ->join('sales as s', 's.id', '=', 'si.sale_id')
                ->join('journal_entries as je', function ($join) {
                    $join->on('je.id', '=', 's.journal_entry_id')
                        ->where('je.status', 'posted');
                });

            if ($hasSalePaymentDetails) {
                $posQuery->leftJoin('sale_payment_details as spd', 'spd.sale_id', '=', 's.id');
            }
            $posQuery->leftJoinSub($legacyPaymentMethods, 'jl_pay', fn ($join) => $join
                ->on('jl_pay.journal_entry_id', '=', 's.journal_entry_id'));

            $paymentExpr = $hasSalePaymentDetails
                ? "COALESCE(spd.payment_method, jl_pay.payment_method, 'cash')"
                : "COALESCE(jl_pay.payment_method, 'cash')";

            $posSales = $posQuery
                ->whereDate('s.sale_date', '>=', $startDate)
                ->whereDate('s.sale_date', '<=', $endDate)
                ->when($shiftId, fn (Builder $query) => $query->where('s.shift_id', $shiftId))
                ->groupBy('si.product_id', 'si.product_name_snapshot', 'si.unit_name_snapshot')
                ->selectRaw("
                    si.product_id,
                    si.product_name_snapshot AS product_name,
                    si.unit_name_snapshot AS unit_name,
                    SUM(CASE WHEN {$paymentExpr} = 'cash' THEN si.quantity ELSE 0 END) AS cash_quantity,
                    SUM(CASE WHEN {$paymentExpr} = 'cash' THEN si.line_total ELSE 0 END) AS cash_amount,
                    SUM(CASE WHEN {$paymentExpr} IN ('bank', 'mobile_bank', 'cheque', 'online') THEN si.quantity ELSE 0 END) AS bank_quantity,
                    SUM(CASE WHEN {$paymentExpr} IN ('bank', 'mobile_bank', 'cheque', 'online') THEN si.line_total ELSE 0 END) AS bank_amount
                ")
                ->get();
        }

        // B. Credit Sales
        $hasCreditSales = Schema::hasTable('credit_sales') && Schema::hasTable('credit_sale_items') && Schema::hasTable('credit_sale_customers');
        $creditSales = collect();
        if ($hasCreditSales) {
            $creditSales = DB::table('credit_sale_items as csi')
                ->join('credit_sale_customers as csc', 'csc.id', '=', 'csi.credit_sale_customer_id')
                ->join('credit_sales as cs', 'cs.id', '=', 'csc.credit_sale_id')
                ->join('journal_entries as je', function ($join) {
                    $join->on('je.id', '=', 'csc.journal_entry_id')
                        ->where('je.status', 'posted');
                })
                ->whereDate('cs.sale_date', '>=', $startDate)
                ->whereDate('cs.sale_date', '<=', $endDate)
                ->when($shiftId, fn (Builder $query) => $query->where('cs.shift_id', $shiftId))
                ->groupBy('csi.product_id', 'csi.product_name_snapshot', 'csi.unit_name_snapshot')
                ->selectRaw('
                    csi.product_id,
                    csi.product_name_snapshot AS product_name,
                    csi.unit_name_snapshot AS unit_name,
                    SUM(csi.quantity) AS credit_quantity,
                    SUM(csi.line_total) AS credit_amount
                ')
                ->get();
        }

        // Merge products by ID
        $combined = collect();

        foreach ($posSales as $sale) {
            $productId = (int) $sale->product_id;
            $combined->put($productId, [
                'product_id' => $productId,
                'product_name' => $sale->product_name,
                'unit_name' => $sale->unit_name,
                'cash_quantity' => (float) $sale->cash_quantity,
                'cash_amount' => (float) $sale->cash_amount,
                'bank_quantity' => (float) $sale->bank_quantity,
                'bank_amount' => (float) $sale->bank_amount,
                'credit_quantity' => 0.0,
                'credit_amount' => 0.0,
            ]);
        }

        foreach ($creditSales as $sale) {
            $productId = (int) $sale->product_id;
            $existing = $combined->get($productId, [
                'product_id' => $productId,
                'product_name' => $sale->product_name,
                'unit_name' => $sale->unit_name,
                'cash_quantity' => 0.0,
                'cash_amount' => 0.0,
                'bank_quantity' => 0.0,
                'bank_amount' => 0.0,
                'credit_quantity' => 0.0,
                'credit_amount' => 0.0,
            ]);

            $existing['credit_quantity'] += (float) $sale->credit_quantity;
            $existing['credit_amount'] += (float) $sale->credit_amount;
            $combined->put($productId, $existing);
        }

        return $combined->map(function (array $item) {
            $totalQty = round($item['cash_quantity'] + $item['bank_quantity'] + $item['credit_quantity'], 6);
            $totalAmt = round($item['cash_amount'] + $item['bank_amount'] + $item['credit_amount'], 4);
            $unitPrice = $totalQty > 0 ? round($totalAmt / $totalQty, 4) : 0.0;

            return [
                'product_id' => $item['product_id'],
                'product_name' => $item['product_name'],
                'unit_name' => $item['unit_name'],
                'unit_price' => $unitPrice,
                'cash_quantity' => round($item['cash_quantity'], 4),
                'cash_amount' => round($item['cash_amount'], 2),
                'bank_quantity' => round($item['bank_quantity'], 4),
                'bank_amount' => round($item['bank_amount'], 2),
                'credit_quantity' => round($item['credit_quantity'], 4),
                'credit_amount' => round($item['credit_amount'], 2),
                'total_quantity' => round($totalQty, 4),
                'total_amount' => round($totalAmt, 2),
            ];
        })->sortBy('product_name')->values();
    }

    private function customerWiseSales(
        string $startDate,
        string $endDate,
        ?int $shiftId
    ): Collection {
        return DB::table('credit_sale_items as csi')
            ->join('credit_sale_customers as csc', 'csc.id', '=', 'csi.credit_sale_customer_id')
            ->join('credit_sales as cs', 'cs.id', '=', 'csc.credit_sale_id')
            ->join('journal_entries as je', function ($join) {
                $join->on('je.id', '=', 'csc.journal_entry_id')
                    ->where('je.status', 'posted');
            })
            ->whereDate('cs.sale_date', '>=', $startDate)
            ->whereDate('cs.sale_date', '<=', $endDate)
            ->when($shiftId, fn (Builder $query) => $query->where('cs.shift_id', $shiftId))
            ->orderBy('cs.sale_date')
            ->orderBy('csc.customer_name_snapshot')
            ->orderBy('cs.id')
            ->get([
                'cs.sale_date',
                'csc.customer_name_snapshot as customer_name',
                'csi.vehicle_number_snapshot as vehicle_no',
                'csi.product_name_snapshot as product_name',
                'csi.unit_name_snapshot as unit_name',
                'csi.unit_price',
                'csi.quantity',
                'csi.line_total as total_amount',
            ])
            ->map(function (object $sale) {
                $sale->unit_price = (float) $sale->unit_price;
                $sale->quantity = (float) $sale->quantity;
                $sale->total_amount = (float) $sale->total_amount;

                return $sale;
            });
    }

    private function voucherRows(
        string $voucherType,
        string $startDate,
        string $endDate,
        ?int $shiftId
    ): Collection {
        if (! Schema::hasTable('vouchers') || ! Schema::hasTable('voucher_lines')) {
            return collect();
        }

        $types = in_array($voucherType, ['receipt', 'received'])
            ? ['receipt', 'received']
            : ['payment', 'office_payment'];

        $targetSide = in_array($voucherType, ['receipt', 'received']) ? 'credit' : 'debit';

        $hasVoucherCategories = Schema::hasTable('voucher_categories');
        $hasVoucherTransactionTypes = Schema::hasTable('voucher_transaction_types');
        $hasVoucherPaymentDetails = Schema::hasTable('voucher_payment_details');

        $query = DB::table('vouchers as v')
            ->join('journal_entries as je', function ($join) {
                $join->on('je.id', '=', 'v.journal_entry_id')
                    ->whereIn('je.status', ['posted', 'reversed']);
            })
            ->join('voucher_lines as vl', function ($join) use ($targetSide) {
                $join->on('vl.voucher_id', '=', 'v.id')
                    ->where('vl.entry_side', $targetSide);
            })
            ->join('accounts as a', 'a.id', '=', 'vl.account_id');

        if ($hasVoucherCategories) {
            $query->leftJoin('voucher_categories as vc', 'vc.id', '=', 'v.voucher_category_id');
        }
        if ($hasVoucherTransactionTypes) {
            $query->leftJoin('voucher_transaction_types as vtt', 'vtt.id', '=', 'v.voucher_transaction_type_id');
        }
        if ($hasVoucherPaymentDetails) {
            $query->leftJoinSub(
                DB::table('voucher_lines')
                    ->select('voucher_id', DB::raw('MIN(id) AS id'))
                    ->where('entry_side', 'debit')
                    ->groupBy('voucher_id'),
                'first_debit',
                fn ($join) => $join->on('first_debit.voucher_id', '=', 'v.id')
            )->leftJoin('voucher_payment_details as payment', 'payment.voucher_line_id', '=', 'first_debit.id');
        }

        $selects = [
            'vl.id',
            'a.name as account_name',
            'vl.amount',
            'v.description',
        ];
        $selects[] = $hasVoucherCategories ? 'vc.name as category' : DB::raw('NULL as category');
        $selects[] = $hasVoucherTransactionTypes ? 'vtt.name as type_name' : DB::raw('NULL as type_name');
        $selects[] = $hasVoucherPaymentDetails ? 'payment.payment_method as payment_type' : DB::raw("'cash' as payment_type");

        return $query
            ->where('v.status', 'posted')
            ->whereIn('v.voucher_type', $types)
            ->whereDate('v.voucher_date', '>=', $startDate)
            ->whereDate('v.voucher_date', '<=', $endDate)
            ->when($shiftId, fn (Builder $query) => $query->where('v.shift_id', $shiftId))
            ->orderBy('v.voucher_date')
            ->orderBy('v.id')
            ->orderBy('vl.id')
            ->get($selects)
            ->map(function (object $row) {
                $row->amount = (float) $row->amount;
                $row->payment_type = $row->payment_type ?: 'cash';
                $row->category = $row->category ?: ($row->type_name ?: 'Voucher');

                return $row;
            });
    }

    private function cashFlowBreakdown(
        Collection $cashAccountIds,
        string $startDate,
        string $endDate,
        ?int $shiftId
    ): array {
        if ($cashAccountIds->isEmpty()) {
            return [
                'opening_balance' => 0.0,
                'receipts' => collect(),
                'payments' => collect(),
                'total_receipts' => 0.0,
                'total_payments' => 0.0,
                'net_movement' => 0.0,
                'closing_balance' => 0.0,
                'expenses' => 0.0,
                'salaries' => 0.0,
                'advances' => 0.0,
                'loans' => 0.0,
                'purchases' => 0.0,
                'supplier_payments' => 0.0,
                'customer_refunds' => 0.0,
                'other_payments' => 0.0,
                'transfers_out' => 0.0,
            ];
        }

        // Opening Cash Balance
        $openingBalance = $this->calculateOpeningBalance($cashAccountIds, $startDate);

        // All journal lines for Cash accounts during the period
        $lines = $this->fetchAccountJournalLines($cashAccountIds, $startDate, $endDate, $shiftId);

        $receipts = collect();
        $payments = collect();

        $expenses = 0.0;
        $salaries = 0.0;
        $advances = 0.0;
        $loans = 0.0;
        $purchases = 0.0;
        $supplierPayments = 0.0;
        $customerRefunds = 0.0;
        $otherPayments = 0.0;
        $transfersOut = 0.0;

        foreach ($lines as $line) {
            $debit = (float) $line->debit_amount;
            $credit = (float) $line->credit_amount;

            if ($debit > 0) {
                $receipts->push((object) [
                    'id' => $line->id,
                    'account_name' => $this->resolveOpposingAccountName($line, true),
                    'payment_type' => $line->payment_method ?: 'cash',
                    'amount' => $debit,
                    'category' => $line->voucher_category_name ?: $this->classifyReceiptCategory($line),
                    'description' => $line->line_description ?: ($line->entry_description ?: $line->voucher_description),
                ]);
            }

            if ($credit > 0) {
                $category = $line->voucher_category_name ?: $this->classifyPaymentCategory($line);
                $payments->push((object) [
                    'id' => $line->id,
                    'account_name' => $this->resolveOpposingAccountName($line, false),
                    'payment_type' => $line->payment_method ?: 'cash',
                    'amount' => $credit,
                    'category' => $category,
                    'description' => $line->line_description ?: ($line->entry_description ?: $line->voucher_description),
                ]);

                // Track subcategories for financial summary
                $subType = strtolower((string) ($line->transaction_type_name ?: ''));
                $sourceType = (string) $line->source_type;

                if (str_contains($sourceType, 'FundTransfer')) {
                    $transfersOut += $credit;
                } elseif (str_contains($subType, 'salary')) {
                    $salaries += $credit;
                } elseif (str_contains($subType, 'advance')) {
                    $advances += $credit;
                } elseif (str_contains($subType, 'loan')) {
                    $loans += $credit;
                } elseif (str_contains($subType, 'purchase')) {
                    $purchases += $credit;
                } elseif (str_contains($subType, 'supplier') || str_contains(strtolower((string) $line->voucher_category_name), 'supplier')) {
                    $supplierPayments += $credit;
                } elseif (str_contains($subType, 'deposit') || str_contains($subType, 'refund')) {
                    $customerRefunds += $credit;
                } else {
                    $expenses += $credit;
                }
            }
        }

        $totalReceipts = round((float) $receipts->sum('amount'), 4);
        $totalPayments = round((float) $payments->sum('amount'), 4);
        $netMovement = round($totalReceipts - $totalPayments, 4);
        $closingBalance = round($openingBalance + $netMovement, 4);

        return [
            'opening_balance' => round($openingBalance, 2),
            'receipts' => $receipts,
            'payments' => $payments,
            'total_receipts' => round($totalReceipts, 2),
            'total_payments' => round($totalPayments, 2),
            'net_movement' => round($netMovement, 2),
            'closing_balance' => round($closingBalance, 2),
            'expenses' => round($expenses, 2),
            'salaries' => round($salaries, 2),
            'advances' => round($advances, 2),
            'loans' => round($loans, 2),
            'purchases' => round($purchases, 2),
            'supplier_payments' => round($supplierPayments, 2),
            'customer_refunds' => round($customerRefunds, 2),
            'other_payments' => round($otherPayments, 2),
            'transfers_out' => round($transfersOut, 2),
        ];
    }

    private function bankFlowBreakdown(
        Collection $bankAccountIds,
        string $startDate,
        string $endDate,
        ?int $shiftId
    ): array {
        if ($bankAccountIds->isEmpty()) {
            return [
                'opening_balance' => 0.0,
                'receipts' => collect(),
                'payments' => collect(),
                'total_receipts' => 0.0,
                'total_payments' => 0.0,
                'net_movement' => 0.0,
                'closing_balance' => 0.0,
                'expenses' => 0.0,
                'salaries' => 0.0,
                'purchases' => 0.0,
                'supplier_payments' => 0.0,
                'customer_refunds' => 0.0,
                'other_payments' => 0.0,
                'transfers_out' => 0.0,
            ];
        }

        // Opening Bank Balance
        $openingBalance = $this->calculateOpeningBalance($bankAccountIds, $startDate);

        // All journal lines for Bank accounts during the period
        $lines = $this->fetchAccountJournalLines($bankAccountIds, $startDate, $endDate, $shiftId);

        $receipts = collect();
        $payments = collect();
        $expenses = 0.0;
        $salaries = 0.0;
        $purchases = 0.0;
        $supplierPayments = 0.0;
        $customerRefunds = 0.0;
        $otherPayments = 0.0;
        $transfersOut = 0.0;

        foreach ($lines as $line) {
            $debit = (float) $line->debit_amount;
            $credit = (float) $line->credit_amount;

            if ($debit > 0) {
                $receipts->push((object) [
                    'id' => $line->id,
                    'account_name' => $this->resolveOpposingAccountName($line, true),
                    'payment_type' => $line->payment_method ?: 'bank',
                    'amount' => $debit,
                    'category' => $line->voucher_category_name ?: $this->classifyReceiptCategory($line),
                    'description' => $line->line_description ?: ($line->entry_description ?: $line->voucher_description),
                ]);
            }

            if ($credit > 0) {
                $category = $line->voucher_category_name ?: $this->classifyPaymentCategory($line);
                $payments->push((object) [
                    'id' => $line->id,
                    'account_name' => $this->resolveOpposingAccountName($line, false),
                    'payment_type' => $line->payment_method ?: 'bank',
                    'amount' => $credit,
                    'category' => $category,
                    'description' => $line->line_description ?: ($line->entry_description ?: $line->voucher_description),
                ]);

                $subType = strtolower((string) ($line->transaction_type_name ?: ''));
                $sourceType = (string) $line->source_type;

                if (str_contains($sourceType, 'FundTransfer')) {
                    $transfersOut += $credit;
                } elseif (str_contains($subType, 'salary')) {
                    $salaries += $credit;
                } elseif (str_contains($subType, 'purchase')) {
                    $purchases += $credit;
                } elseif (str_contains($subType, 'supplier') || str_contains(strtolower((string) $line->voucher_category_name), 'supplier')) {
                    $supplierPayments += $credit;
                } elseif (str_contains($subType, 'deposit') || str_contains($subType, 'refund')) {
                    $customerRefunds += $credit;
                } else {
                    $expenses += $credit;
                }
            }
        }

        $totalReceipts = round((float) $receipts->sum('amount'), 4);
        $totalPayments = round((float) $payments->sum('amount'), 4);
        $netMovement = round($totalReceipts - $totalPayments, 4);
        $closingBalance = round($openingBalance + $netMovement, 4);

        return [
            'opening_balance' => round($openingBalance, 2),
            'receipts' => $receipts,
            'payments' => $payments,
            'total_receipts' => round($totalReceipts, 2),
            'total_payments' => round($totalPayments, 2),
            'net_movement' => round($netMovement, 2),
            'closing_balance' => round($closingBalance, 2),
            'expenses' => round($expenses, 2),
            'salaries' => round($salaries, 2),
            'purchases' => round($purchases, 2),
            'supplier_payments' => round($supplierPayments, 2),
            'customer_refunds' => round($customerRefunds, 2),
            'other_payments' => round($otherPayments, 2),
            'transfers_out' => round($transfersOut, 2),
        ];
    }

    private function calculateOpeningBalance(Collection $accountIds, string $startDate): float
    {
        $totals = DB::table('journal_lines as jl')
            ->join('journal_entries as je', 'je.id', '=', 'jl.journal_entry_id')
            ->whereIn('jl.account_id', $accountIds)
            ->whereIn('je.status', ['posted', 'reversed'])
            ->whereDate('je.business_date', '<', $startDate)
            ->selectRaw('COALESCE(SUM(jl.debit_amount - jl.credit_amount), 0) as balance')
            ->first();

        return (float) ($totals?->balance ?? 0);
    }

    private function fetchAccountJournalLines(
        Collection $accountIds,
        string $startDate,
        string $endDate,
        ?int $shiftId
    ): Collection {
        $hasVouchers = Schema::hasTable('vouchers');
        $hasVoucherCategories = Schema::hasTable('voucher_categories');
        $hasVoucherTransactionTypes = Schema::hasTable('voucher_transaction_types');

        $query = DB::table('journal_lines as jl')
            ->join('journal_entries as je', 'je.id', '=', 'jl.journal_entry_id')
            ->join('accounts as a', 'a.id', '=', 'jl.account_id');

        if ($hasVouchers) {
            $query->leftJoin('vouchers as v', function ($join) {
                $join->on('v.journal_entry_id', '=', 'je.id')
                    ->where('v.status', 'posted');
            });
            if ($hasVoucherCategories) {
                $query->leftJoin('voucher_categories as vc', 'vc.id', '=', 'v.voucher_category_id');
            }
            if ($hasVoucherTransactionTypes) {
                $query->leftJoin('voucher_transaction_types as vtt', 'vtt.id', '=', 'v.voucher_transaction_type_id');
            }
        }

        $selects = [
            'jl.id',
            'jl.journal_entry_id',
            'je.entry_no',
            'je.business_date',
            'je.event_type',
            'je.source_type',
            'je.source_id',
            'je.description as entry_description',
            'jl.account_id',
            'a.name as fund_account_name',
            'jl.debit_amount',
            'jl.credit_amount',
            'jl.payment_method',
            'jl.description as line_description',
        ];

        $selects[] = $hasVouchers ? 'v.id as voucher_id' : DB::raw('NULL as voucher_id');
        $selects[] = $hasVouchers ? 'v.voucher_no' : DB::raw('NULL as voucher_no');
        $selects[] = $hasVouchers ? 'v.voucher_type' : DB::raw('NULL as voucher_type');
        $selects[] = $hasVouchers ? 'v.description as voucher_description' : DB::raw('NULL as voucher_description');
        $selects[] = ($hasVouchers && $hasVoucherCategories) ? 'vc.name as voucher_category_name' : DB::raw('NULL as voucher_category_name');
        $selects[] = ($hasVouchers && $hasVoucherTransactionTypes) ? 'vtt.name as transaction_type_name' : DB::raw('NULL as transaction_type_name');

        return $query
            ->whereIn('jl.account_id', $accountIds)
            ->where('je.status', 'posted')
            ->whereDate('je.business_date', '>=', $startDate)
            ->whereDate('je.business_date', '<=', $endDate)
            ->when($shiftId, fn (Builder $query) => $query->where(function ($q) use ($shiftId, $hasVouchers) {
                $q->where('je.shift_id', $shiftId);
                if ($hasVouchers) {
                    $q->orWhere('v.shift_id', $shiftId);
                }
            }))
            ->orderBy('je.business_date')
            ->orderBy('je.occurred_at')
            ->orderBy('jl.id')
            ->get($selects);
    }

    private function resolveOpposingAccountName(object $line, bool $isReceipt): string
    {
        if ($line->transaction_type_name) {
            return $line->transaction_type_name;
        }

        if ($line->voucher_category_name) {
            return $line->voucher_category_name;
        }

        $sourceType = (string) $line->source_type;

        if (str_contains($sourceType, 'Sale')) {
            return 'Product Sales';
        }

        if (str_contains($sourceType, 'ShiftClosing')) {
            return 'Shift Closing Cash Sales';
        }

        if (str_contains($sourceType, 'FundTransfer')) {
            return $isReceipt ? 'Internal Fund Transfer In' : 'Internal Fund Transfer Out';
        }

        // Fallback: look up opposing journal line account name
        $opposingLine = DB::table('journal_lines as jl')
            ->join('accounts as a', 'a.id', '=', 'jl.account_id')
            ->where('jl.journal_entry_id', $line->journal_entry_id)
            ->where('jl.id', '<>', $line->id)
            ->when($isReceipt, fn ($q) => $q->where('jl.credit_amount', '>', 0))
            ->when(! $isReceipt, fn ($q) => $q->where('jl.debit_amount', '>', 0))
            ->select('a.name')
            ->first();

        return $opposingLine?->name ?: ($line->line_description ?: $line->fund_account_name);
    }

    private function classifyReceiptCategory(object $line): string
    {
        $sourceType = (string) $line->source_type;

        if (str_contains($sourceType, 'Sale') || str_contains($sourceType, 'ShiftClosing')) {
            return 'Sales';
        }

        if (str_contains($sourceType, 'FundTransfer')) {
            return 'Transfer';
        }

        if ($line->voucher_category_name) {
            return $line->voucher_category_name;
        }

        return 'Receipt';
    }

    private function classifyPaymentCategory(object $line): string
    {
        $sourceType = (string) $line->source_type;

        if (str_contains($sourceType, 'FundTransfer')) {
            return 'Transfer';
        }

        if ($line->voucher_category_name) {
            return $line->voucher_category_name;
        }

        return 'Expense';
    }
}
