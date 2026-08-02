<?php

namespace App\Http\Controllers;

use App\Http\Requests\VoucherCategoryRequest;
use App\Models\CompanySetting;
use App\Models\VoucherCategory;
use App\Services\VoucherCategoryService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;

class VoucherCategoryController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly VoucherCategoryService $voucherCategories
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:voucher-category-view', only: ['index', 'downloadPdf']),
            new Middleware('permission:voucher-category-create', only: ['store']),
            new Middleware('permission:voucher-category-update', only: ['update']),
            new Middleware('permission:voucher-category-delete', only: ['destroy', 'bulkDelete']),
        ];
    }

    public function index(Request $request)
    {
        $perPage = max(1, min($request->integer('per_page', 10), 100));
        $voucherCategories = $this->filteredQuery($request)
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (VoucherCategory $voucherCategory) => [
                'id' => $voucherCategory->id,
                'code' => $voucherCategory->code,
                'name' => $voucherCategory->name,
                'description' => $voucherCategory->description,
                'status' => $voucherCategory->status,
                'sort_order' => $voucherCategory->sort_order,
                'is_system' => $voucherCategory->isSystemCategory(),
                'created_at' => $voucherCategory->created_at->format('Y-m-d'),
            ]);

        return Inertia::render('VoucherCategories/Index', [
            'voucherCategories' => $voucherCategories,
            'filters' => $request->only([
                'search',
                'status',
                'system',
                'sort_by',
                'sort_order',
                'per_page',
            ]),
        ]);
    }

    public function store(VoucherCategoryRequest $request)
    {
        $this->voucherCategories->create($request->validated());

        return back()->with('success', 'Voucher category created successfully.');
    }

    public function update(
        VoucherCategoryRequest $request,
        VoucherCategory $voucherCategory
    ) {
        $this->voucherCategories->update($voucherCategory, $request->validated());

        return back()->with('success', 'Voucher category updated successfully.');
    }

    public function destroy(VoucherCategory $voucherCategory)
    {
        $this->voucherCategories->delete($voucherCategory);

        return back()->with('success', 'Voucher category deleted successfully.');
    }

    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'distinct', 'exists:voucher_categories,id'],
        ]);

        $deleted = $this->voucherCategories->deleteMany($validated['ids']);

        return back()->with('success', "{$deleted} voucher categories deleted successfully.");
    }

    public function downloadPdf(Request $request)
    {
        return Pdf::loadView('pdf.voucher-categories', [
            'voucherCategories' => $this->filteredQuery($request)->get(),
            'companySetting' => CompanySetting::query()->first(),
        ])->stream('voucher-categories.pdf');
    }

    private function filteredQuery(Request $request): Builder
    {
        $query = VoucherCategory::query();

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->boolean('status'));
        }

        if ($request->filled('system') && $request->input('system') !== 'all') {
            $query->where('is_system', $request->input('system') === 'system');
        }

        $allowedSorts = [
            'code',
            'name',
            'sort_order',
            'status',
            'is_system',
            'created_at',
        ];
        $sortBy = in_array($request->input('sort_by'), $allowedSorts, true)
            ? $request->input('sort_by')
            : 'sort_order';
        $sortOrder = $request->input('sort_order') === 'desc' ? 'desc' : 'asc';

        return $query->orderBy($sortBy, $sortOrder)->orderBy('id');
    }
}
