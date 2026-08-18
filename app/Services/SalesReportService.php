<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Shift;
use App\Models\Vehicle;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SalesReportService
{
    /**
     * Generate detailed customer-wise sales report data.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function report(array $filters = []): array
    {
        $startDate = ! empty($filters['start_date'])
            ? (string) $filters['start_date']
            : now()->startOfMonth()->toDateString();

        $endDate = ! empty($filters['end_date'])
            ? (string) $filters['end_date']
            : today()->toDateString();

        $customerId = ! empty($filters['customer_id']) ? (int) $filters['customer_id'] : null;
        $customerSearch = ! empty($filters['customer']) ? (string) $filters['customer'] : null;
        $vehicleId = ! empty($filters['vehicle_id']) ? (int) $filters['vehicle_id'] : null;
        $vehicleSearch = ! empty($filters['vehicle']) ? (string) $filters['vehicle'] : null;
        $productId = ! empty($filters['product_id']) ? (int) $filters['product_id'] : null;
        $paymentType = ! empty($filters['payment_type']) ? (string) $filters['payment_type'] : null;
        $shiftId = ! empty($filters['shift_id']) ? (int) $filters['shift_id'] : null;

        $sanitizedFilters = [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'customer_id' => $customerId,
            'customer' => $customerSearch,
            'vehicle_id' => $vehicleId,
            'vehicle' => $vehicleSearch,
            'product_id' => $productId,
            'payment_type' => $paymentType,
            'shift_id' => $shiftId,
        ];

        $regularRows = $this->queryRegularSales($sanitizedFilters);
        $creditRows = $this->queryCreditSales($sanitizedFilters);

        $allRows = $regularRows->concat($creditRows);

        // Group customer-wise
        $groupedCustomers = $allRows
            ->groupBy(function (object $row) {
                return (string) ($row->customer_name ?: 'Walk-in Customer');
            })
            ->map(function (Collection $rows, string $customerName) {
                $sortedRows = $rows
                    ->sort(function (object $a, object $b) {
                        // Sort by sale_date DESC, then invoice_no DESC, then id DESC
                        if ($a->date !== $b->date) {
                            return strcmp($b->date, $a->date);
                        }
                        if ($a->invoice_no !== $b->invoice_no) {
                            return strcmp($b->invoice_no, $a->invoice_no);
                        }

                        return $b->item_id <=> $a->item_id;
                    })
                    ->values();

                $totalQty = (float) $sortedRows->sum('quantity');
                $totalAmt = (float) $sortedRows->sum('total_amount');

                return [
                    'customer_name' => $customerName,
                    'sales' => $sortedRows->all(),
                    'total_quantity' => $totalQty,
                    'total_amount' => $totalAmt,
                ];
            })
            ->sortBy('customer_name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $grandTotalQty = (float) $groupedCustomers->sum('total_quantity');
        $grandTotalAmt = (float) $groupedCustomers->sum('total_amount');
        $totalInvoices = $allRows->pluck('invoice_no')->unique()->count();

        return [
            'filters' => $sanitizedFilters,
            'customers' => $groupedCustomers->all(),
            'grand_total_quantity' => $grandTotalQty,
            'grand_total_amount' => $grandTotalAmt,
            'total_customers' => $groupedCustomers->count(),
            'total_invoices' => $totalInvoices,
            'total_rows' => $allRows->count(),
        ];
    }

    /**
     * Filter options for dropdowns.
     *
     * @return array<string, mixed>
     */
    public function filterOptions(): array
    {
        $customers = Customer::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name']);

        $vehicles = Vehicle::query()
            ->active()
            ->orderBy('vehicle_number')
            ->get(['id', 'vehicle_number']);

        $products = Product::query()
            ->active()
            ->orderBy('product_name')
            ->get(['id', 'product_name']);

        $shifts = Shift::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name']);

        $paymentTypes = [
            ['value' => 'Cash', 'label' => 'Cash'],
            ['value' => 'Bank', 'label' => 'Bank'],
            ['value' => 'Credit', 'label' => 'Credit'],
            ['value' => 'Mobile Bank', 'label' => 'Mobile Bank'],
        ];

        return [
            'customers' => $customers,
            'vehicles' => $vehicles,
            'products' => $products,
            'shifts' => $shifts,
            'paymentTypes' => $paymentTypes,
        ];
    }

    /**
     * Query regular and white sales items.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, object>
     */
    private function queryRegularSales(array $filters): Collection
    {
        if ($filters['payment_type'] === 'Credit') {
            return collect();
        }

        if (! Schema::hasTable('sales') || ! Schema::hasTable('sale_items')) {
            return collect();
        }

        $query = DB::table('sale_items as si')
            ->join('sales as s', 's.id', '=', 'si.sale_id')
            ->join('journal_entries as je', function ($join) {
                $join->on('je.id', '=', 's.journal_entry_id')
                    ->where('je.status', 'posted');
            })
            ->leftJoin('customers as c', 'c.id', '=', 's.customer_id')
            ->leftJoin('vehicles as v', 'v.id', '=', 's.vehicle_id')
            ->leftJoin('products as p', 'p.id', '=', 'si.product_id')
            ->leftJoin('units as u', 'u.id', '=', 'p.unit_id')
            ->leftJoin('users as user', 'user.id', '=', 's.created_by')
            ->leftJoin('sale_payment_details as spd', 'spd.sale_id', '=', 's.id')
            ->leftJoin('accounts as pa', 'pa.id', '=', 'spd.account_id')
            ->leftJoin('groups as pag', 'pag.id', '=', 'pa.group_id')
            ->whereIn('s.status', ['posted', 'partially_paid', 'paid'])
            ->when($filters['start_date'], fn (Builder $q) => $q->whereDate('s.sale_date', '>=', $filters['start_date']))
            ->when($filters['end_date'], fn (Builder $q) => $q->whereDate('s.sale_date', '<=', $filters['end_date']))
            ->when($filters['customer_id'], fn (Builder $q) => $q->where('s.customer_id', $filters['customer_id']))
            ->when($filters['customer'], fn (Builder $q) => $q->where(function (Builder $sq) use ($filters) {
                $sq->where('s.customer_name_snapshot', 'like', '%'.$filters['customer'].'%')
                    ->orWhere('c.name', 'like', '%'.$filters['customer'].'%');
            }))
            ->when($filters['vehicle_id'], fn (Builder $q) => $q->where('s.vehicle_id', $filters['vehicle_id']))
            ->when($filters['vehicle'], fn (Builder $q) => $q->where(function (Builder $sq) use ($filters) {
                $sq->where('s.vehicle_number_snapshot', 'like', '%'.$filters['vehicle'].'%')
                    ->orWhere('v.vehicle_number', 'like', '%'.$filters['vehicle'].'%');
            }))
            ->when($filters['product_id'], fn (Builder $q) => $q->where('si.product_id', $filters['product_id']))
            ->when($filters['shift_id'], fn (Builder $q) => $q->where('s.shift_id', $filters['shift_id']));

        // Filter payment type
        if ($filters['payment_type']) {
            $type = $filters['payment_type'];
            if ($type === 'Cash') {
                $query->where(function (Builder $q) {
                    $q->where('s.sale_type', 'white')
                        ->orWhereRaw("LOWER(COALESCE(spd.payment_method, '')) = 'cash'")
                        ->orWhere('pag.code', '100020002');
                });
            } elseif ($type === 'Bank') {
                $query->where('s.sale_type', '!=', 'white')
                    ->where(function (Builder $q) {
                        $q->whereIn(DB::raw("LOWER(COALESCE(spd.payment_method, ''))"), ['bank', 'cheque', 'online', 'card'])
                            ->orWhere('pag.code', '100020004');
                    });
            } elseif ($type === 'Mobile Bank') {
                $query->where('s.sale_type', '!=', 'white')
                    ->where(function (Builder $q) {
                        $q->whereIn(DB::raw("LOWER(COALESCE(spd.payment_method, ''))"), ['mobile_bank', 'mobile bank', 'bkash', 'nagad', 'rocket'])
                            ->orWhere('pag.code', '100020003');
                    });
            }
        }

        return $query->select([
            'si.id as item_id',
            's.id as sale_id',
            's.sale_date as date',
            DB::raw("COALESCE(NULLIF(s.customer_name_snapshot, ''), NULLIF(c.name, ''), 'Walk-in Customer') as customer_name"),
            DB::raw("COALESCE(NULLIF(s.vehicle_number_snapshot, ''), NULLIF(v.vehicle_number, ''), 'N/A') as vehicle_no"),
            DB::raw("COALESCE(NULLIF(s.invoice_no, ''), CONCAT('INV-', s.id)) as invoice_no"),
            DB::raw("COALESCE(NULLIF(s.memo_no, ''), 'N/A') as memo_no"),
            DB::raw("COALESCE(NULLIF(user.name, ''), 'N/A') as done_by"),
            DB::raw("COALESCE(NULLIF(si.product_name_snapshot, ''), NULLIF(p.product_name, ''), 'Unknown Product') as product_name"),
            DB::raw("COALESCE(NULLIF(si.unit_name_snapshot, ''), NULLIF(u.name, ''), 'Litre') as unit_name"),
            'si.quantity',
            'si.unit_price',
            'si.line_total as total_amount',
            's.sale_type',
            'spd.payment_method',
            'pag.code as group_code',
        ])
            ->get()
            ->map(function (object $row) {
                $row->type = $this->resolveRegularSaleType($row->sale_type, $row->payment_method, $row->group_code);
                $row->quantity = (float) $row->quantity;
                $row->unit_price = (float) $row->unit_price;
                $row->total_amount = (float) $row->total_amount;

                return $row;
            });
    }

    /**
     * Query credit sales items.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, object>
     */
    private function queryCreditSales(array $filters): Collection
    {
        if ($filters['payment_type'] && $filters['payment_type'] !== 'Credit') {
            return collect();
        }

        if (! Schema::hasTable('credit_sales')
            || ! Schema::hasTable('credit_sale_customers')
            || ! Schema::hasTable('credit_sale_items')) {
            return collect();
        }

        $query = DB::table('credit_sale_items as csi')
            ->join('credit_sale_customers as csc', 'csc.id', '=', 'csi.credit_sale_customer_id')
            ->join('credit_sales as cs', 'cs.id', '=', 'csc.credit_sale_id')
            ->join('journal_entries as je', function ($join) {
                $join->on('je.id', '=', 'csc.journal_entry_id')
                    ->where('je.status', 'posted');
            })
            ->leftJoin('customers as c', 'c.id', '=', 'csc.customer_id')
            ->leftJoin('vehicles as v', 'v.id', '=', 'csi.vehicle_id')
            ->leftJoin('products as p', 'p.id', '=', 'csi.product_id')
            ->leftJoin('units as u', 'u.id', '=', 'p.unit_id')
            ->leftJoin('users as user', 'user.id', '=', 'cs.created_by')
            ->whereIn('cs.status', ['posted', 'partially_paid', 'paid'])
            ->when($filters['start_date'], fn (Builder $q) => $q->whereDate('cs.sale_date', '>=', $filters['start_date']))
            ->when($filters['end_date'], fn (Builder $q) => $q->whereDate('cs.sale_date', '<=', $filters['end_date']))
            ->when($filters['customer_id'], fn (Builder $q) => $q->where('csc.customer_id', $filters['customer_id']))
            ->when($filters['customer'], fn (Builder $q) => $q->where(function (Builder $sq) use ($filters) {
                $sq->where('csc.customer_name_snapshot', 'like', '%'.$filters['customer'].'%')
                    ->orWhere('c.name', 'like', '%'.$filters['customer'].'%');
            }))
            ->when($filters['vehicle_id'], fn (Builder $q) => $q->where('csi.vehicle_id', $filters['vehicle_id']))
            ->when($filters['vehicle'], fn (Builder $q) => $q->where(function (Builder $sq) use ($filters) {
                $sq->where('csi.vehicle_number_snapshot', 'like', '%'.$filters['vehicle'].'%')
                    ->orWhere('v.vehicle_number', 'like', '%'.$filters['vehicle'].'%');
            }))
            ->when($filters['product_id'], fn (Builder $q) => $q->where('csi.product_id', $filters['product_id']))
            ->when($filters['shift_id'], fn (Builder $q) => $q->where('cs.shift_id', $filters['shift_id']));

        return $query->select([
            'csi.id as item_id',
            'cs.id as sale_id',
            'cs.sale_date as date',
            DB::raw("COALESCE(NULLIF(csc.customer_name_snapshot, ''), NULLIF(c.name, ''), 'Walk-in Customer') as customer_name"),
            DB::raw("COALESCE(NULLIF(csi.vehicle_number_snapshot, ''), NULLIF(v.vehicle_number, ''), 'N/A') as vehicle_no"),
            DB::raw("COALESCE(NULLIF(cs.invoice_no, ''), CONCAT('CS-', cs.id)) as invoice_no"),
            DB::raw("COALESCE(NULLIF(cs.memo_no, ''), 'N/A') as memo_no"),
            DB::raw("COALESCE(NULLIF(user.name, ''), 'N/A') as done_by"),
            DB::raw("COALESCE(NULLIF(csi.product_name_snapshot, ''), NULLIF(p.product_name, ''), 'Unknown Product') as product_name"),
            DB::raw("COALESCE(NULLIF(csi.unit_name_snapshot, ''), NULLIF(u.name, ''), 'Litre') as unit_name"),
            'csi.quantity',
            'csi.unit_price',
            'csi.line_total as total_amount',
        ])
            ->get()
            ->map(function (object $row) {
                $row->type = 'Credit';
                $row->quantity = (float) $row->quantity;
                $row->unit_price = (float) $row->unit_price;
                $row->total_amount = (float) $row->total_amount;

                return $row;
            });
    }

    /**
     * Resolve normalized payment type label.
     */
    private function resolveRegularSaleType(?string $saleType, ?string $paymentMethod, ?string $groupCode): string
    {
        if ($saleType === 'white') {
            return 'Cash';
        }

        $method = strtolower(trim((string) $paymentMethod));

        if ($method === 'cash' || $groupCode === '100020002') {
            return 'Cash';
        }

        if (in_array($method, ['mobile_bank', 'mobile bank', 'bkash', 'nagad', 'rocket'], true)
            || $groupCode === '100020003') {
            return 'Mobile Bank';
        }

        if (in_array($method, ['bank', 'cheque', 'online', 'card'], true)
            || $groupCode === '100020004') {
            return 'Bank';
        }

        return 'Cash';
    }
}
