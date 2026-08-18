<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DailyStatementReportService
{
    public function report(
        string $dateOrStartDate,
        string|int|null $endDateOrShiftId = null,
        ?int $shiftId = null
    ): array {
        if (is_int($endDateOrShiftId) && $shiftId === null) {
            $startDate = $dateOrStartDate;
            $endDate = $dateOrStartDate;
            $shiftId = $endDateOrShiftId;
        } elseif (is_string($endDateOrShiftId) && ! empty($endDateOrShiftId)) {
            $startDate = $dateOrStartDate;
            $endDate = $endDateOrShiftId;
        } else {
            $startDate = $dateOrStartDate;
            $endDate = $dateOrStartDate;
        }

        $cashSales = $this->cashSales(
            $startDate,
            $endDate,
            $shiftId
        );
        $bankSales = $this->bankSales(
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
                $cashSales,
                $bankSales,
                $creditSales
            ),
            'cashSales' => $cashSales,
            'bankSales' => $bankSales,
            'cashBankSales' => $cashSales->concat($bankSales)->sortBy('product_name')->values(),
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
            'officePayment' => $this->voucherRows(
                'office_payment',
                'debit',
                $startDate,
                $endDate,
                $shiftId
            ),
        ];
    }

    private function cashSales(
        string $startDate,
        string $endDate,
        ?int $shiftId
    ): Collection {
        $legacyPaymentMethods = DB::table('journal_lines')
            ->select('journal_entry_id')
            ->selectRaw('MAX(payment_method) AS payment_method')
            ->where('debit_amount', '>', 0)
            ->whereNotNull('payment_method')
            ->groupBy('journal_entry_id');

        $bankMethods = ['bank', 'mobile_bank', 'cheque', 'online', 'mobile bank'];
        $bankGroupCodes = ['100020003', '100020004'];

        return DB::table('sale_items as si')
            ->join('sales as s', 's.id', '=', 'si.sale_id')
            ->join('journal_entries as je', function ($join) {
                $join->on('je.id', '=', 's.journal_entry_id')
                    ->where('je.status', 'posted');
            })
            ->leftJoin('sale_payment_details as spd', 'spd.sale_id', '=', 's.id')
            ->leftJoin('accounts as spd_acc', 'spd_acc.id', '=', 'spd.account_id')
            ->leftJoin('groups as spd_grp', 'spd_grp.id', '=', 'spd_acc.group_id')
            ->leftJoinSub($legacyPaymentMethods, 'jl_pay', fn ($join) => $join
                ->on('jl_pay.journal_entry_id', '=', 's.journal_entry_id'))
            ->when(
                $startDate === $endDate,
                fn (Builder $query) => $query->whereDate('s.sale_date', $startDate),
                fn (Builder $query) => $query->whereDate('s.sale_date', '>=', $startDate)->whereDate('s.sale_date', '<=', $endDate)
            )
            ->when($shiftId, fn (Builder $query) => $query
                ->where('s.shift_id', $shiftId))
            ->where(function (Builder $query) use ($bankMethods, $bankGroupCodes) {
                $query->where('s.sale_type', 'white')
                    ->orWhere(function (Builder $q) use ($bankMethods, $bankGroupCodes) {
                        $q->where('s.sale_type', 'regular')
                            ->where(function (Builder $sub) use ($bankMethods, $bankGroupCodes) {
                                $sub->whereRaw("LOWER(COALESCE(spd.payment_method, jl_pay.payment_method, 'cash')) NOT IN (?, ?, ?, ?, ?)", $bankMethods)
                                    ->where(function (Builder $grpSub) use ($bankGroupCodes) {
                                        $grpSub->whereNull('spd_grp.code')
                                            ->orWhereRaw("spd_grp.code NOT IN (?, ?)", $bankGroupCodes);
                                    });
                            });
                    });
            })
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

    private function bankSales(
        string $startDate,
        string $endDate,
        ?int $shiftId
    ): Collection {
        $legacyPaymentMethods = DB::table('journal_lines')
            ->select('journal_entry_id')
            ->selectRaw('MAX(payment_method) AS payment_method')
            ->where('debit_amount', '>', 0)
            ->whereNotNull('payment_method')
            ->groupBy('journal_entry_id');

        $bankMethods = ['bank', 'mobile_bank', 'cheque', 'online', 'mobile bank'];
        $bankGroupCodes = ['100020003', '100020004'];

        return DB::table('sale_items as si')
            ->join('sales as s', 's.id', '=', 'si.sale_id')
            ->join('journal_entries as je', function ($join) {
                $join->on('je.id', '=', 's.journal_entry_id')
                    ->where('je.status', 'posted');
            })
            ->leftJoin('sale_payment_details as spd', 'spd.sale_id', '=', 's.id')
            ->leftJoin('accounts as spd_acc', 'spd_acc.id', '=', 'spd.account_id')
            ->leftJoin('groups as spd_grp', 'spd_grp.id', '=', 'spd_acc.group_id')
            ->leftJoinSub($legacyPaymentMethods, 'jl_pay', fn ($join) => $join
                ->on('jl_pay.journal_entry_id', '=', 's.journal_entry_id'))
            ->when(
                $startDate === $endDate,
                fn (Builder $query) => $query->whereDate('s.sale_date', $startDate),
                fn (Builder $query) => $query->whereDate('s.sale_date', '>=', $startDate)->whereDate('s.sale_date', '<=', $endDate)
            )
            ->when($shiftId, fn (Builder $query) => $query
                ->where('s.shift_id', $shiftId))
            ->where('s.sale_type', 'regular')
            ->where(function (Builder $query) use ($bankMethods, $bankGroupCodes) {
                $query->whereRaw("LOWER(COALESCE(spd.payment_method, jl_pay.payment_method, '')) IN (?, ?, ?, ?, ?)", $bankMethods)
                    ->orWhereRaw("spd_grp.code IN (?, ?)", $bankGroupCodes);
            })
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
            ->when(
                $startDate === $endDate,
                fn (Builder $query) => $query->whereDate('cs.sale_date', $startDate),
                fn (Builder $query) => $query->whereDate('cs.sale_date', '>=', $startDate)->whereDate('cs.sale_date', '<=', $endDate)
            )
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
            ->when(
                $startDate === $endDate,
                fn (Builder $query) => $query->whereDate('cs.sale_date', $startDate),
                fn (Builder $query) => $query->whereDate('cs.sale_date', '>=', $startDate)->whereDate('cs.sale_date', '<=', $endDate)
            )
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
                } elseif ($voucherType === 'office_payment') {
                    $query->where('v.voucher_type', 'office_payment');
                } else {
                    $query->where('v.voucher_type', 'payment');
                }
            })
            ->when(
                $startDate === $endDate,
                fn (Builder $query) => $query->whereDate('v.voucher_date', $startDate),
                fn (Builder $query) => $query->whereDate('v.voucher_date', '>=', $startDate)->whereDate('v.voucher_date', '<=', $endDate)
            )
            ->when($shiftId, fn (Builder $query) => $query
                ->where('v.shift_id', $shiftId))
            ->orderBy('v.voucher_date')
            ->orderBy('v.id')
            ->get([
                'a.name as account_name',
                DB::raw("COALESCE(payment.payment_method, 'cash') as payment_type"),
                'vl.amount',
                'v.description',
            ])
            ->map(function (object $row) {
                $row->amount = (float) $row->amount;

                return $row;
            });
    }

    private function combineProductSales(
        Collection ...$salesCollections
    ): Collection {
        $combined = collect();
        foreach ($salesCollections as $collection) {
            $combined = $combined->concat($collection);
        }

        return $combined
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
