<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DailyStatementReportService
{
    public function report(
        string $startDate,
        string $endDate,
        ?int $shiftId = null
    ): array {
        $cashBankSales = $this->cashBankSales(
            $startDate,
            $endDate,
            $shiftId
        );
        $creditSales = $this->creditSales(
            $startDate,
            $endDate,
            $shiftId
        );

        return [
            'productWiseSales' => $this->combineProductSales(
                $cashBankSales,
                $creditSales
            ),
            'cashBankSales' => $cashBankSales,
            'creditSales' => $creditSales,
            'customerWiseSales' => $this->customerWiseSales(
                $startDate,
                $endDate,
                $shiftId
            ),
            'cashReceived' => $this->voucherRows(
                'receipt',
                'credit',
                $startDate,
                $endDate,
                $shiftId
            ),
            'cashPayment' => $this->voucherRows(
                'payment',
                'debit',
                $startDate,
                $endDate,
                $shiftId
            ),
        ];
    }

    private function cashBankSales(
        string $startDate,
        string $endDate,
        ?int $shiftId
    ): Collection {
        return DB::table('sale_items as si')
            ->join('sales as s', 's.id', '=', 'si.sale_id')
            ->join('journal_entries as je', function ($join) {
                $join->on('je.id', '=', 's.journal_entry_id')
                    ->where('je.status', 'posted');
            })
            ->whereBetween('s.sale_date', [$startDate, $endDate])
            ->when($shiftId, fn (Builder $query) => $query
                ->where('s.shift_id', $shiftId))
            ->groupBy(
                'si.product_id',
                'si.product_name_snapshot',
                'si.unit_name_snapshot'
            )
            ->orderBy('si.product_name_snapshot')
            ->selectRaw(
                'si.product_id,
                 si.product_name_snapshot AS product_name,
                 si.unit_name_snapshot AS unit_name,
                 SUM(si.quantity) AS total_quantity,
                 SUM(si.line_total) AS total_amount,
                 CASE WHEN SUM(si.quantity) > 0
                    THEN SUM(si.line_total) / SUM(si.quantity)
                    ELSE 0
                 END AS unit_price'
            )
            ->get()
            ->map(fn (object $sale) => $this->castProductSale($sale));
    }

    private function creditSales(
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
            ->whereBetween('cs.sale_date', [$startDate, $endDate])
            ->when($shiftId, fn (Builder $query) => $query
                ->where('cs.shift_id', $shiftId))
            ->groupBy(
                'csi.product_id',
                'csi.product_name_snapshot',
                'csi.unit_name_snapshot'
            )
            ->orderBy('csi.product_name_snapshot')
            ->selectRaw(
                'csi.product_id,
                 csi.product_name_snapshot AS product_name,
                 csi.unit_name_snapshot AS unit_name,
                 SUM(csi.quantity) AS total_quantity,
                 SUM(csi.line_total) AS total_amount,
                 CASE WHEN SUM(csi.quantity) > 0
                    THEN SUM(csi.line_total) / SUM(csi.quantity)
                    ELSE 0
                 END AS unit_price'
            )
            ->get()
            ->map(fn (object $sale) => $this->castProductSale($sale));
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
            ->whereBetween('cs.sale_date', [$startDate, $endDate])
            ->when($shiftId, fn (Builder $query) => $query
                ->where('cs.shift_id', $shiftId))
            ->orderBy('csc.customer_name_snapshot')
            ->orderBy('cs.sale_date')
            ->orderBy('cs.id')
            ->get([
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
        string $accountSide,
        string $startDate,
        string $endDate,
        ?int $shiftId
    ): Collection {
        return DB::table('vouchers as v')
            ->join('journal_entries as je', function ($join) {
                $join->on('je.id', '=', 'v.journal_entry_id')
                    ->where('je.status', 'posted');
            })
            ->join('voucher_lines as vl', function ($join) use ($accountSide) {
                $join->on('vl.voucher_id', '=', 'v.id')
                    ->where('vl.entry_side', $accountSide);
            })
            ->join('accounts as a', 'a.id', '=', 'vl.account_id')
            ->leftJoinSub(
                DB::table('voucher_lines')
                    ->select('voucher_id', DB::raw('MIN(id) AS id'))
                    ->where('entry_side', 'debit')
                    ->groupBy('voucher_id'),
                'first_debit',
                fn ($join) => $join->on('first_debit.voucher_id', '=', 'v.id')
            )
            ->leftJoin(
                'voucher_payment_details as payment',
                'payment.voucher_line_id',
                '=',
                'first_debit.id'
            )
            ->where('v.status', 'posted')
            ->where(function (Builder $query) use ($voucherType) {
                if (in_array($voucherType, ['receipt', 'received'])) {
                    $query->whereIn('v.voucher_type', ['receipt', 'received']);
                } else {
                    $query->whereIn('v.voucher_type', ['payment', 'office_payment']);
                }
            })
            ->whereDate('v.voucher_date', '>=', $startDate)
            ->whereDate('v.voucher_date', '<=', $endDate)
            ->when($shiftId, fn (Builder $query) => $query
                ->where('v.shift_id', $shiftId))
            ->orderBy('v.voucher_date')
            ->orderBy('v.id')
            ->get([
                'a.name as account_name',
                'payment.payment_method as payment_type',
                'vl.amount',
                'v.description',
            ])
            ->map(function (object $row) {
                $row->amount = (float) $row->amount;

                return $row;
            });
    }

    private function combineProductSales(
        Collection $cashBankSales,
        Collection $creditSales
    ): Collection {
        return $cashBankSales
            ->concat($creditSales)
            ->groupBy('product_id')
            ->map(function (Collection $items) {
                $quantity = (float) $items->sum('total_quantity');
                $amount = (float) $items->sum('total_amount');

                return [
                    'product_name' => $items->first()->product_name,
                    'unit_name' => $items->first()->unit_name,
                    'unit_price' => $quantity > 0 ? $amount / $quantity : 0,
                    'total_quantity' => $quantity,
                    'total_amount' => $amount,
                ];
            })
            ->sortBy('product_name')
            ->values();
    }

    private function castProductSale(object $sale): object
    {
        $sale->unit_price = (float) $sale->unit_price;
        $sale->total_quantity = (float) $sale->total_quantity;
        $sale->total_amount = (float) $sale->total_amount;

        return $sale;
    }
}
