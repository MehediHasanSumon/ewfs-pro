<?php

namespace App\Services;

use App\Models\CreditSale;
use App\Models\Customer;
use App\Models\Voucher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CustomerReportService
{
    public function ledgerSummary(
        string $startDate,
        string $endDate,
        ?int $customerId = null
    ): Collection {
        return DB::table('customers as c')
            ->join('accounts as a', 'a.id', '=', 'c.account_id')
            ->join('journal_lines as jl', 'jl.account_id', '=', 'a.id')
            ->join('journal_entries as je', 'je.id', '=', 'jl.journal_entry_id')
            ->whereIn('je.status', ['posted', 'reversed'])
            ->whereBetween('je.business_date', [$startDate, $endDate])
            ->when($customerId, fn ($query) => $query
                ->where('c.id', $customerId))
            ->groupBy('c.id', 'c.name', 'c.mobile', 'c.address')
            ->orderBy('c.name')
            ->selectRaw(
                'c.id AS customer_id,
                 c.name AS customer_name,
                 c.mobile AS customer_mobile,
                 c.address AS customer_address,
                 SUM(jl.credit_amount) AS debit,
                 SUM(jl.debit_amount) AS credit,
                 SUM(jl.debit_amount - jl.credit_amount) AS due'
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
                 COALESCE(v.remarks, jl.description, je.description) AS remarks"
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
            $runningBalance += $row->credit - $row->debit;
            $row->due = $runningBalance;

            return $row;
        });

        return [[
            'customer_name' => $customer->name,
            'ac_number' => $customer->account?->ac_number,
            'customer_mobile' => $customer->mobile ?? 'N/A',
            'customer_address' => $customer->address ?? 'N/A',
            'transactions' => $transactions->all(),
            'total_debit' => (float) $transactions->sum('debit'),
            'total_credit' => (float) $transactions->sum('credit'),
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
        ?int $customerId = null
    ): array {
        return $this->salesRows($startDate, $endDate, $customerId)
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

                return [
                    'customer_name' => $items->first()->customer_name,
                    'customer_mobile' => $items->first()->customer_mobile,
                    'customer_address' => $items->first()->customer_address,
                    'vehicle_groups' => $vehicleGroups,
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
        ?int $customerId
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
            ->orderBy('c.name')
            ->orderBy('cs.sale_date')
            ->orderBy('cs.id')
            ->orderBy('csi.line_no')
            ->select([
                'csi.id',
                'c.id as customer_id',
                'c.name as customer_name',
                'c.mobile as customer_mobile',
                'c.address as customer_address',
                'cs.sale_date',
                DB::raw(
                    'COALESCE(csi.vehicle_number_snapshot, vehicle.vehicle_number) AS vehicle_number'
                ),
                'cs.invoice_no',
                'cs.invoice_no as transaction_id',
                'csi.product_name_snapshot as product_name',
                'csi.unit_name_snapshot as unit_name',
                'csi.unit_price as price',
                'csi.quantity',
                'csi.line_total as total_amount',
            ])
            ->get()
            ->map(function (object $sale) {
                $sale->price = (float) $sale->price;
                $sale->quantity = (float) $sale->quantity;
                $sale->total_amount = (float) $sale->total_amount;

                return $sale;
            });
    }
}
