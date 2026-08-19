<?php

namespace App\Services;

use App\Helpers\VoucherCategoryHelper;
use App\Helpers\VoucherTransactionTypeHelper;
use App\Models\CreditSale;
use App\Models\Customer;
use App\Models\Voucher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CustomerReportService
{
    public function ledgerSummary(
        string $startDate,
        string $endDate,
        ?int $customerId = null
    ): Collection {
        $customerCategoryId = $this->customerCategoryId() ?? 0;
        $duePaidCode = VoucherTransactionTypeHelper::customerDuePaidCode();
        $legacyAdvancePaymentCode = VoucherTransactionTypeHelper::legacyCustomerAdvancePaymentCode();
        $advanceReturnCode = VoucherTransactionTypeHelper::customerAdvanceReturnCode();
        $receiptType = VoucherTransactionTypeHelper::receiptVoucherType();
        $paymentType = VoucherTransactionTypeHelper::paymentVoucherType();
        $ordinaryReceiptCondition = '(vtt.id IS NULL OR (
            vtt.voucher_category_id = ?
            AND vtt.code IN (?, ?)
            AND vtt.voucher_type = ?
        ))';
        $advanceReturnCondition = '(
            vtt.voucher_category_id = ?
            AND vtt.code = ?
            AND vtt.voucher_type = ?
        )';

        return DB::table('customers as c')
            ->join('accounts as a', 'a.id', '=', 'c.account_id')
            ->join('journal_lines as jl', 'jl.account_id', '=', 'a.id')
            ->join('journal_entries as je', 'je.id', '=', 'jl.journal_entry_id')
            ->leftJoin('vouchers as v', function ($join) {
                $join->on('v.id', '=', 'je.source_id')
                    ->where('je.source_type', Voucher::class);
            })
            ->leftJoin(
                'voucher_transaction_types as vtt',
                'vtt.id',
                '=',
                'v.voucher_transaction_type_id'
            )
            ->whereIn('je.status', ['posted', 'reversed'])
            ->whereBetween('je.business_date', [$startDate, $endDate])
            ->when($customerId, fn ($query) => $query
                ->where('c.id', $customerId))
            ->groupBy('c.id', 'c.name', 'c.mobile', 'c.address')
            ->orderBy('c.name')
            ->selectRaw(
                "c.id AS customer_id,
                 c.name AS customer_name,
                 c.mobile AS customer_mobile,
                 c.address AS customer_address,
                 SUM(
                    CASE
                        WHEN je.event_type LIKE 'receipt_voucher%'
                            AND {$ordinaryReceiptCondition}
                            THEN jl.credit_amount - jl.debit_amount
                        WHEN je.event_type LIKE 'payment_voucher%'
                            AND {$advanceReturnCondition}
                            THEN jl.credit_amount - jl.debit_amount
                        ELSE 0
                    END
                 ) AS debit,
                 SUM(
                    CASE
                        WHEN je.event_type LIKE 'credit_sale%'
                            THEN jl.debit_amount - jl.credit_amount
                        ELSE 0
                    END
                 ) AS credit,
                 SUM(
                    CASE
                        WHEN je.event_type LIKE 'credit_sale%'
                            THEN jl.debit_amount - jl.credit_amount
                        WHEN je.event_type LIKE 'receipt_voucher%'
                            AND {$ordinaryReceiptCondition}
                            THEN jl.debit_amount - jl.credit_amount
                        WHEN je.event_type LIKE 'payment_voucher%'
                            AND {$advanceReturnCondition}
                            THEN jl.debit_amount - jl.credit_amount
                        ELSE 0
                    END
                 ) AS due",
                [
                    $customerCategoryId,
                    $duePaidCode,
                    $legacyAdvancePaymentCode,
                    $receiptType,
                    $customerCategoryId,
                    $advanceReturnCode,
                    $paymentType,
                    $customerCategoryId,
                    $duePaidCode,
                    $legacyAdvancePaymentCode,
                    $receiptType,
                    $customerCategoryId,
                    $advanceReturnCode,
                    $paymentType,
                ]
            )
            ->get()
            ->map(function (object $ledger) {
                $ledger->debit = (float) $ledger->debit;
                $ledger->credit = (float) $ledger->credit;
                $ledger->due = (float) $ledger->due;

                return $ledger;
            });
    }

    public function ledgerDetails(
        Customer $customer,
        string $startDate,
        string $endDate
    ): array {
        $customer->loadMissing('account:id,ac_number');
        $firstCreditItems = DB::table('credit_sale_items as item')
            ->groupBy('item.credit_sale_customer_id')
            ->selectRaw(
                'item.credit_sale_customer_id, MIN(item.id) AS item_id'
            );

        $rows = DB::table('journal_lines as jl')
            ->join('journal_entries as je', 'je.id', '=', 'jl.journal_entry_id')
            ->leftJoin('shifts as s', 's.id', '=', 'je.shift_id')
            ->leftJoin('vouchers as v', function ($join) {
                $join->on('v.id', '=', 'je.source_id')
                    ->where('je.source_type', Voucher::class);
            })
            ->leftJoin(
                'voucher_transaction_types as vtt',
                'vtt.id',
                '=',
                'v.voucher_transaction_type_id'
            )
            ->leftJoin('credit_sale_customers as csc', function ($join) {
                $join->on('csc.journal_entry_id', '=', 'je.id')
                    ->where('je.source_type', CreditSale::class);
            })
            ->leftJoin('credit_sales as cs', 'cs.id', '=', 'csc.credit_sale_id')
            ->leftJoinSub(
                $firstCreditItems,
                'first_credit_item',
                'first_credit_item.credit_sale_customer_id',
                '=',
                'csc.id'
            )
            ->leftJoin(
                'credit_sale_items as csi',
                'csi.id',
                '=',
                'first_credit_item.item_id'
            )
            ->leftJoin('vehicles as vehicle', 'vehicle.id', '=', 'csi.vehicle_id')
            ->where('jl.account_id', $customer->account_id)
            ->whereIn('je.status', ['posted', 'reversed'])
            ->whereBetween('je.business_date', [$startDate, $endDate])
            ->orderBy('je.business_date')
            ->orderBy('je.occurred_at')
            ->orderBy('jl.id')
            ->selectRaw(
                "jl.id,
                 je.business_date AS date,
                 je.event_type,
                 s.name AS shift,
                 COALESCE(v.voucher_no, je.reference_no, je.entry_no) AS transaction_id,
                 jl.credit_amount AS debit,
                 jl.debit_amount AS credit,
                 CASE
                    WHEN jl.debit_amount >= jl.credit_amount
                        THEN jl.debit_amount
                    ELSE jl.credit_amount
                 END AS balance,
                 COALESCE(
                    csi.vehicle_number_snapshot,
                    vehicle.vehicle_number,
                    '-'
                 ) AS vehicle_no,
                 COALESCE(
                    cs.memo_no,
                    v.external_reference,
                    v.voucher_no,
                    je.reference_no,
                    'N/A'
                 ) AS memo_no,
                 COALESCE(v.remarks, jl.description, je.description) AS remarks,
                 vtt.code AS transaction_type_code,
                 vtt.name AS transaction_type_name,
                 vtt.voucher_category_id AS transaction_category_id"
            )
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $runningBalance = 0.0;
        $transactions = $rows->map(function (object $row) use (&$runningBalance) {
            $row->debit = (float) $row->debit;
            $row->credit = (float) $row->credit;
            $row->balance = (float) $row->balance;

            if ($this->customerDueDelta($row)) {
                $runningBalance += $row->credit - $row->debit;
            }

            $row->due = $runningBalance;

            return $row;
        });
        $operationalTransactions = $transactions->filter(
            fn (object $row) => $this->customerDueDelta($row)
        );

        return [[
            'customer_name' => $customer->name,
            'ac_number' => $customer->account?->ac_number,
            'customer_mobile' => $customer->mobile ?? 'N/A',
            'customer_address' => $customer->address ?? 'N/A',
            'transactions' => $transactions->all(),
            'total_debit' => (float) $operationalTransactions->sum('debit'),
            'total_credit' => (float) $operationalTransactions->sum('credit'),
            'total_due' => $runningBalance,
        ]];
    }

    public function summaryBills(
        string $startDate,
        string $endDate,
        ?int $customerId = null
    ): array {
        return $this->salesRows($startDate, $endDate, $customerId)
            ->groupBy('customer_id')
            ->map(fn (Collection $items) => [
                'customer_name' => $items->first()->customer_name,
                'sales' => $items->values()->all(),
                'total_quantity' => (float) $items->sum('quantity'),
                'total_amount' => (float) $items->sum('total_amount'),
            ])
            ->values()
            ->all();
    }

    public function detailBills(
        string $startDate,
        string $endDate,
        ?int $customerId = null,
        ?int $vehicleId = null
    ): array {
        return $this->salesRows($startDate, $endDate, $customerId, $vehicleId)
            ->groupBy('customer_id')
            ->map(function (Collection $items) {
                $vehicleGroups = $items
                    ->groupBy(fn (object $item) => $item->vehicle_number ?? '')
                    ->map(fn (Collection $vehicleItems) => [
                        'vehicle_number' => $vehicleItems->first()->vehicle_number,
                        'sales' => $vehicleItems->values()->all(),
                        'total_quantity' => (float) $vehicleItems->sum('quantity'),
                        'total_amount' => (float) $vehicleItems->sum('total_amount'),
                    ])
                    ->values()
                    ->all();

                $productSummary = $items
                    ->groupBy(fn (object $item) => ($item->product_id ?? $item->product_name) . '_' . sprintf('%.4f', (float) $item->price) . '_' . ($item->unit_name ?? ''))
                    ->map(function (Collection $productItems) {
                        $firstItem = $productItems->first();

                        return [
                            'product_id' => $firstItem->product_id ?? null,
                            'product_name' => $firstItem->product_name,
                            'unit_name' => $firstItem->unit_name,
                            'price' => (float) $firstItem->price,
                            'quantity' => (float) $productItems->sum('quantity'),
                            'total_amount' => (float) $productItems->sum('total_amount'),
                        ];
                    })
                    ->values()
                    ->all();

                return [
                    'customer_name' => $items->first()->customer_name,
                    'customer_mobile' => $items->first()->customer_mobile,
                    'customer_address' => $items->first()->customer_address,
                    'vehicle_groups' => $vehicleGroups,
                    'product_summary' => $productSummary,
                    'total_quantity' => (float) $items->sum('quantity'),
                    'total_amount' => (float) $items->sum('total_amount'),
                ];
            })
            ->values()
            ->all();
    }

    private function salesRows(
        string $startDate,
        string $endDate,
        ?int $customerId,
        ?int $vehicleId = null
    ): Collection {
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
            ->when($customerId, fn ($query) => $query
                ->where('c.id', $customerId))
            ->when($vehicleId, fn ($query) => $query
                ->where('csi.vehicle_id', $vehicleId))
            ->orderBy('c.name')
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
            ->map(function (object $sale) {
                $sale->memo_no = ! empty($sale->memo_no) ? (string) $sale->memo_no : 'N/A';
                $sale->price = (float) $sale->price;
                $sale->quantity = (float) $sale->quantity;
                $sale->total_amount = (float) $sale->total_amount;

                return $sale;
            });
    }

    private function customerDueDelta(object $row): bool
    {
        if (str_starts_with($row->event_type, 'credit_sale')) {
            return true;
        }

        return str_starts_with($row->event_type, 'receipt_voucher')
            && (
                $row->transaction_type_code === null
                || (
                    (int) ($row->transaction_category_id ?? 0)
                        === (int) ($this->customerCategoryId() ?? -1)
                    && in_array($row->transaction_type_code, [
                        VoucherTransactionTypeHelper::customerDuePaidCode(),
                        VoucherTransactionTypeHelper::legacyCustomerAdvancePaymentCode(),
                    ], true)
                )
            )
            || (
                str_starts_with($row->event_type, 'payment_voucher')
                && (int) ($row->transaction_category_id ?? 0)
                    === (int) ($this->customerCategoryId() ?? -1)
                && $row->transaction_type_code
                    === VoucherTransactionTypeHelper::customerAdvanceReturnCode()
            );
    }

    private function customerCategoryId(): ?int
    {
        if (! Schema::hasTable('voucher_categories')) {
            return null;
        }

        $query = DB::table('voucher_categories');

        if (Schema::hasColumn('voucher_categories', 'code')) {
            $query->where('code', VoucherCategoryHelper::customerCode());
        } else {
            $query->where(
                'name',
                VoucherCategoryHelper::getCategoryDefaultName('customer')
            );
        }

        return $query->value('id');
    }
}
