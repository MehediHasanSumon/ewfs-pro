<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\FinancialReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __construct(
        private readonly FinancialReportService $reports
    ) {
    }

    public function index()
    {
        $today = today()->toDateString();
        $stockData = $this->reports->stockData($today);

        return Inertia::render('dashboard', [
            'stats' => [
                'cashInHand' => $this->reports->cashBalance($today),
                'outstandingBalance' => $this->reports->totalOutstanding($today),
                'cashSale' => $this->reports->cashSales($today),
                'officeExpenses' => $this->reports->officeExpenses($today),
            ],
            'chartData' => [
                'monthlySales' => $this->reports->monthlySales(),
                'monthlyPurchases' => $this->reports->monthlyPurchases(),
                'stockData' => $stockData->map(fn (object $stock) => [
                    'product_name' => $stock->product_name,
                    'current_stock' => $stock->current_stock,
                ])->values(),
                'totalStock' => (float) $stockData->sum('current_stock'),
                'outstandingCustomers' => $this->reports
                    ->outstandingCustomers(5, $today)
                    ->map(fn (object $customer) => [
                        'customer' => $customer->customer,
                        'mobile_number' => $customer->mobile_number,
                        'balance' => $customer->balance,
                    ])
                    ->values(),
            ],
            'products' => Product::query()
                ->orderBy('product_name')
                ->get(['id', 'product_name']),
        ]);
    }

    public function getChartData(Request $request)
    {
        $request->validate([
            'type' => ['required', 'in:sale,purchase'],
            'product_id' => ['nullable'],
        ]);

        $productId = $request->input('product_id');
        $productId = $productId === null || $productId === '' || $productId === 'all'
            ? null
            : (int) $productId;

        if ($productId !== null) {
            $request->merge(['product_id' => $productId]);
            $request->validate([
                'product_id' => ['integer', 'exists:products,id'],
            ]);
        }

        $data = $request->input('type') === 'sale'
            ? $this->reports->monthlySales(6, $productId)
            : $this->reports->monthlyPurchases(6, $productId);

        return response()->json($data);
    }
}
