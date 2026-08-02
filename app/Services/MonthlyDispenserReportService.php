<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ShiftClosing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MonthlyDispenserReportService
{
    public function paginated(array $filters): LengthAwarePaginator
    {
        $paginator = $this->query($filters)
            ->paginate($filters['per_page'] ?? 10)
            ->withQueryString();
        $startIndex = ($paginator->currentPage() - 1) * $paginator->perPage();

        $paginator->setCollection(
            $this->rows(
                $paginator->getCollection(),
                $startIndex,
                $filters['product_id'] ?? null
            )
        );

        return $paginator;
    }

    public function all(array $filters): Collection
    {
        return $this->rows(
            $this->query($filters)->get(),
            0,
            $filters['product_id'] ?? null
        );
    }

    public function products(): Collection
    {
        return Product::query()
            ->active()
            ->allowedForDispenser()
            ->orderBy('product_name')
            ->get(['id', 'product_name']);
    }

    private function query(array $filters): Builder
    {
        $sortBy = match ($filters['sort_by'] ?? null) {
            'close_date', 'date', 'business_date' => 'business_date',
            'shift_id' => 'shift_id',
            'created_at' => 'created_at',
            default => 'business_date',
        };
        $sortOrder = ($filters['sort_order'] ?? null) === 'asc'
            ? 'asc'
            : 'desc';

        return ShiftClosing::query()
            ->posted()
            ->with([
                'shift:id,name',
                'summary',
                'dispenserReadings.product:id,product_name',
                'productItems:id,shift_closing_id,product_id,product_name_snapshot,unit_price,quantity,line_total',
            ])
            ->when(! empty($filters['search']), function (Builder $query) use ($filters) {
                $search = $filters['search'];
                $query->where(function (Builder $closing) use ($search) {
                    $closing->where('business_date', 'like', '%'.$search.'%')
                        ->orWhereHas('shift', fn (Builder $shift) => $shift
                            ->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->when(! empty($filters['start_date']), fn (Builder $query) => $query
                ->whereDate('business_date', '>=', $filters['start_date']))
            ->when(! empty($filters['end_date']), fn (Builder $query) => $query
                ->whereDate('business_date', '<=', $filters['end_date']))
            ->orderBy($sortBy, $sortOrder)
            ->orderBy('id', $sortOrder);
    }

    private function rows(
        Collection $closings,
        int $startIndex,
        ?int $productId
    ): Collection {
        $purchases = $this->purchaseTotals($closings);

        return $closings->values()->map(function (
            ShiftClosing $closing,
            int $index
        ) use ($startIndex, $productId, $purchases) {
            $productSales = $closing->dispenserReadings
                ->map(fn ($reading) => [
                    'product_id' => $reading->product_id,
                    'product_name' => $reading->product?->product_name ?? 'Unknown',
                    'total_sale' => (float) $reading->net_quantity,
                    'amount' => (float) $reading->gross_amount,
                ])
                ->concat($closing->productItems->map(fn ($item) => [
                    'product_id' => $item->product_id,
                    'product_name' => $item->product_name_snapshot,
                    'total_sale' => (float) $item->quantity,
                    'amount' => (float) $item->line_total,
                ]))
                ->when($productId, fn (Collection $sales) => $sales
                    ->where('product_id', $productId))
                ->groupBy('product_id')
                ->map(function (Collection $sales) {
                    $quantity = (float) $sales->sum('total_sale');
                    $amount = (float) $sales->sum('amount');

                    return [
                        'product_id' => (int) $sales->first()['product_id'],
                        'product_name' => $sales->first()['product_name'],
                        'total_sale' => $quantity,
                        'price' => $quantity > 0 ? $amount / $quantity : 0,
                        'amount' => $amount,
                    ];
                })
                ->values()
                ->all();

            $summary = $closing->summary;
            $received = (float) ($summary?->cash_receipts ?? 0)
                + (float) ($summary?->bank_receipts ?? 0);
            $expenses = (float) ($summary?->cash_payments ?? 0)
                + (float) ($summary?->bank_payments ?? 0)
                + (float) ($summary?->office_payments ?? 0);
            $amount = (float) ($summary?->expected_cash
                ?? $closing->expected_cash);
            $creditSale = (float) ($summary?->credit_sales ?? 0);
            $bankSale = (float) ($summary?->bank_sales ?? 0);
            $date = $closing->business_date->format('Y-m-d');
            $purchase = (float) ($purchases->get(
                $this->closingKey($date, $closing->shift_id)
            ) ?? 0);

            return [
                'id' => $closing->id,
                'sl' => $startIndex + $index + 1,
                'date' => $date,
                'shift' => $closing->shift?->name ?? 'N/A',
                'product_sales' => $productSales,
                'received_due_paid' => $received,
                'amount' => $amount,
                'credit_sale' => $creditSale,
                'bank_sale' => $bankSale,
                'expenses' => $expenses,
                'purchase' => $purchase,
                'cash_in_hand' => (float) $closing->actual_cash,
                'total_balance' => $amount
                    - $creditSale
                    - $bankSale
                    - $expenses,
            ];
        });
    }

    private function purchaseTotals(Collection $closings): Collection
    {
        if ($closings->isEmpty()) {
            return collect();
        }

        return DB::table('purchases as p')
            ->join('journal_entries as je', function ($join) {
                $join->on('je.id', '=', 'p.journal_entry_id')
                    ->where('je.status', 'posted');
            })
            ->whereIn(
                'p.purchase_date',
                $closings->pluck('business_date')
                    ->map(fn ($date) => $date->format('Y-m-d'))
                    ->unique()
            )
            ->whereIn('p.shift_id', $closings->pluck('shift_id')->unique())
            ->groupBy('p.purchase_date', 'p.shift_id')
            ->selectRaw(
                'p.purchase_date,
                 p.shift_id,
                 SUM(p.grand_total) AS total'
            )
            ->get()
            ->mapWithKeys(fn (object $purchase) => [
                $this->closingKey(
                    $purchase->purchase_date,
                    (int) $purchase->shift_id
                ) => (float) $purchase->total,
            ]);
    }

    private function closingKey(string $date, int $shiftId): string
    {
        return $date.'|'.$shiftId;
    }
}
