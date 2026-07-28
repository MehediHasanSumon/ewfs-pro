<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OperationalReportService
{
    public function customerSales(
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $customer = null
    ): Collection {
        return DB::table('sales as s')
            ->join('sale_items as si', 'si.sale_id', '=', 's.id')
            ->join('journal_entries as je', function ($join) {
                $join->on('je.id', '=', 's.journal_entry_id')
                    ->where('je.status', 'posted');
            })
            ->leftJoin('shifts as shift', 'shift.id', '=', 's.shift_id')
            ->when($startDate, fn ($query) => $query
                ->whereDate('s.sale_date', '>=', $startDate))
            ->when($endDate, fn ($query) => $query
                ->whereDate('s.sale_date', '<=', $endDate))
            ->when($customer, fn ($query) => $query
                ->where('s.customer_name_snapshot', 'like', '%'.$customer.'%'))
            ->groupBy(
                's.id',
                's.sale_date',
                's.customer_name_snapshot',
                'shift.name',
                's.invoice_no'
            )
            ->orderByDesc('s.sale_date')
            ->orderByDesc('s.id')
            ->selectRaw(
                's.sale_date,
                 s.customer_name_snapshot AS customer,
                 COALESCE(shift.name, "N/A") AS shift_name,
                 s.invoice_no,
                 SUM(si.quantity) AS quantity,
                 SUM(si.line_total) AS total_amount'
            )
            ->get()
            ->map(function (object $sale) {
                return [
                    'sale_date' => $sale->sale_date,
                    'customer' => $sale->customer,
                    'shift_name' => $sale->shift_name,
                    'invoice_no' => $sale->invoice_no,
                    'quantity' => (float) $sale->quantity,
                    'total_amount' => (float) $sale->total_amount,
                ];
            });
    }

    public function saleCustomerNames(): Collection
    {
        return DB::table('sales as s')
            ->join('journal_entries as je', function ($join) {
                $join->on('je.id', '=', 's.journal_entry_id')
                    ->where('je.status', 'posted');
            })
            ->whereNotNull('s.customer_name_snapshot')
            ->where('s.customer_name_snapshot', '<>', '')
            ->distinct()
            ->orderBy('s.customer_name_snapshot')
            ->pluck('s.customer_name_snapshot');
    }

    public function purchaseReport(
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        $purchases = DB::table('purchase_items as pi')
            ->join('purchases as p', 'p.id', '=', 'pi.purchase_id')
            ->join('suppliers as supplier', 'supplier.id', '=', 'p.supplier_id')
            ->join('journal_entries as je', function ($join) {
                $join->on('je.id', '=', 'p.journal_entry_id')
                    ->where('je.status', 'posted');
            })
            ->when($startDate, fn ($query) => $query
                ->whereDate('p.purchase_date', '>=', $startDate))
            ->when($endDate, fn ($query) => $query
                ->whereDate('p.purchase_date', '<=', $endDate))
            ->orderByDesc('p.purchase_date')
            ->orderByDesc('p.id')
            ->orderBy('pi.line_no')
            ->get([
                'p.purchase_date as date',
                'p.invoice_no',
                'p.memo_no',
                'supplier.name as supplier_name',
                'pi.product_name_snapshot as product_name',
                'pi.unit_name_snapshot as unit_name',
                'pi.quantity',
                'pi.unit_cost as price',
                'pi.line_total as total_amount',
            ])
            ->map(function (object $purchase) {
                return [
                    'date' => $purchase->date,
                    'invoice_no' => $purchase->invoice_no,
                    'memo_no' => $purchase->memo_no,
                    'supplier_name' => $purchase->supplier_name,
                    'product_name' => $purchase->product_name,
                    'unit_name' => $purchase->unit_name,
                    'quantity' => (float) $purchase->quantity,
                    'price' => (float) $purchase->price,
                    'total_amount' => (float) $purchase->total_amount,
                ];
            });

        return [
            'purchases' => $purchases->all(),
            'total_quantity' => (float) $purchases->sum('quantity'),
            'total_amount' => (float) $purchases->sum('total_amount'),
        ];
    }
}
