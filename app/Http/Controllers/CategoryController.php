<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use App\Models\CompanySetting;
use App\Services\CatalogReferenceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;

class CategoryController extends Controller implements HasMiddleware
{
    public function __construct(private readonly CatalogReferenceService $catalogReferenceService) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-category|can-category-download', only: ['index', 'downloadPdf']),
            new Middleware('permission:create-category', only: ['store']),
            new Middleware('permission:update-category', only: ['update']),
            new Middleware('permission:delete-category', only: ['destroy', 'bulkDelete']),
        ];
    }

    public function index(Request $request)
    {
        $perPage = max(1, min($request->integer('per_page', 10), 100));
        $categories = $this->filteredQuery($request)
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'code' => $category->code,
                'status' => $category->status,
                'created_at' => $category->created_at->format('Y-m-d'),
            ]);

        return Inertia::render('Categories/Categories', [
            'categories' => $categories,
            'filters' => $request->only([
                'search',
                'status',
                'start_date',
                'end_date',
                'sort_by',
                'sort_order',
                'per_page',
            ]),
        ]);
    }

    public function store(CategoryRequest $request)
    {
        Category::create($request->validated());

        return redirect()->back()->with('success', 'Category created successfully.');
    }

    public function update(CategoryRequest $request, Category $category)
    {
        $category->update($request->validated());

        return redirect()->back()->with('success', 'Category updated successfully.');
    }

    public function destroy(Category $category)
    {
        $this->catalogReferenceService->deleteCategory($category);

        return redirect()->back()->with('success', 'Category deleted successfully.');
    }

    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'distinct', 'exists:categories,id'],
        ]);

        $deleted = $this->catalogReferenceService->deleteManyCategories($validated['ids']);

        return redirect()->back()->with('success', "{$deleted} categories deleted successfully.");
    }

    public function downloadPdf(Request $request)
    {
        $categories = $this->filteredQuery($request)->get();
        $companySetting = CompanySetting::first();
        $pdf = Pdf::loadView('pdf.categories', compact('categories', 'companySetting'));

        return $pdf->stream();
    }

    private function filteredQuery(Request $request): Builder
    {
        $query = Category::query();

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.trim((string) $request->input('search')).'%');
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->boolean('status'));
        }

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->date('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->date('end_date'));
        }

        $allowedSorts = ['id', 'name', 'code', 'status', 'created_at'];
        $sortBy = in_array($request->input('sort_by'), $allowedSorts, true)
            ? $request->input('sort_by')
            : 'name';
        $sortOrder = $request->input('sort_order') === 'desc' ? 'desc' : 'asc';

        return $query->orderBy($sortBy, $sortOrder)->orderBy('id');
    }
}
