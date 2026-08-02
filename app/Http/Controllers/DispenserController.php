<?php

namespace App\Http\Controllers;

use App\Helpers\ErpHelper;
use App\Http\Requests\DispenserRequest;
use App\Models\CompanySetting;
use App\Models\Dispenser;
use App\Models\Product;
use App\Services\DispenserService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;

class DispenserController extends Controller implements HasMiddleware
{
    public function __construct(private readonly DispenserService $dispenserService) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-dispenser', only: ['index']),
            new Middleware('permission:view-dispenser|can-dispenser-download', only: ['downloadPdf']),
            new Middleware('permission:create-dispenser', only: ['store']),
            new Middleware('permission:update-dispenser', only: ['update']),
            new Middleware('permission:delete-dispenser', only: ['destroy', 'bulkDelete']),
        ];
    }

    public function index(Request $request)
    {
        $perPage = max(1, min($request->integer('per_page', 10), 100));
        $dispensers = $this->filteredQuery($request)
            ->with([
                'product:id,category_id,product_name',
                'product.category:id,code,name',
                'product.activeRate',
                'latestReading' => fn ($query) => $query->select([
                    'dispenser_readings.id',
                    'dispenser_readings.dispenser_id',
                    'dispenser_readings.start_reading',
                    'dispenser_readings.end_reading',
                    'dispenser_readings.unit_price',
                ]),
            ])
            ->paginate($perPage)
            ->withQueryString()
            ->through(function (Dispenser $dispenser): array {
                $latestReading = $dispenser->latestReading;
                $openingReading = (float) $dispenser->opening_reading;

                return [
                    'id' => $dispenser->id,
                    'dispenser_name' => $dispenser->dispenser_name,
                    'product_id' => $dispenser->product_id,
                    'product_name' => $dispenser->product?->product_name ?? '',
                    'product_category_allowed' => ErpHelper::isDispenserProductCategoryCode(
                        $dispenser->product?->category?->code
                    ),
                    'dispenser_item' => $dispenser->dispenser_item,
                    'item_rate' => $dispenser->product?->activeRate !== null
                        ? (float) $dispenser->product->activeRate->sales_price
                        : ($latestReading !== null ? (float) $latestReading->unit_price : null),
                    'start_reading' => $latestReading !== null
                        ? (float) $latestReading->start_reading
                        : $openingReading,
                    'end_reading' => $latestReading !== null
                        ? (float) $latestReading->end_reading
                        : $openingReading,
                    'status' => $dispenser->status,
                    'created_at' => $dispenser->created_at->format('Y-m-d'),
                ];
            });

        $products = Product::query()
            ->select(['id', 'product_name'])
            ->active()
            ->allowedForDispenser()
            ->orderBy('product_name')
            ->get();

        return Inertia::render('Dispensers/Dispensers', [
            'dispensers' => $dispensers,
            'products' => $products,
            'filters' => $request->only([
                'search',
                'status',
                'product_id',
                'start_date',
                'end_date',
                'sort_by',
                'sort_order',
                'per_page',
            ]),
        ]);
    }

    public function store(DispenserRequest $request)
    {
        $this->dispenserService->create($request->attributesForPersistence());

        return redirect()->back()->with('success', 'Dispenser created successfully.');
    }

    public function update(DispenserRequest $request, Dispenser $dispenser)
    {
        $this->dispenserService->update($dispenser, $request->attributesForPersistence());

        return redirect()->back()->with('success', 'Dispenser updated successfully.');
    }

    public function destroy(Dispenser $dispenser)
    {
        $this->dispenserService->delete($dispenser);

        return redirect()->back()->with('success', 'Dispenser deleted successfully.');
    }

    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'distinct', 'exists:dispensers,id'],
        ]);

        $deleted = $this->dispenserService->deleteMany($validated['ids']);

        return redirect()->back()->with('success', "{$deleted} dispensers deleted successfully.");
    }

    public function downloadPdf(Request $request)
    {
        $dispensers = $this->filteredQuery($request)
            ->with(['product.activeRate', 'latestReading'])
            ->get();
        $companySetting = CompanySetting::first();

        $pdf = Pdf::loadView('pdf.dispensers', compact('dispensers', 'companySetting'));

        return $pdf->stream('dispensers.pdf');
    }

    private function filteredQuery(Request $request): Builder
    {
        $query = Dispenser::query();

        if ($request->filled('search')) {
            $query->where('dispenser_name', 'like', '%'.trim((string) $request->input('search')).'%');
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->boolean('status'));
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->integer('product_id'));
        }

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->date('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->date('end_date'));
        }

        $allowedSorts = ['id', 'dispenser_name', 'product_id', 'status', 'created_at'];
        $sortBy = in_array($request->input('sort_by'), $allowedSorts, true)
            ? $request->input('sort_by')
            : 'dispenser_name';
        $sortOrder = $request->input('sort_order') === 'desc' ? 'desc' : 'asc';

        return $query->orderBy($sortBy, $sortOrder)->orderBy('id');
    }
}
