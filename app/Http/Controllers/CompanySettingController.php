<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompanySettingRequest;
use App\Models\CompanySetting;
use App\Services\CompanySettingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;

class CompanySettingController extends Controller implements HasMiddleware
{
    public function __construct(private readonly CompanySettingService $companySettingService) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-company-setting|can-company-setting-download', only: ['index', 'show', 'downloadPdf']),
            new Middleware('permission:create-company-setting', only: ['create', 'store']),
            new Middleware('permission:update-company-setting', only: ['edit', 'update']),
            new Middleware('permission:delete-company-setting', only: ['destroy', 'bulkDelete']),
        ];
    }

    public function index(Request $request)
    {
        return Inertia::render('CompanySettings/CompanySettings', [
            'companySettings' => $this->filteredQuery($request)->get(),
            'filters' => $request->only(['search', 'status', 'start_date', 'end_date']),
        ]);
    }

    public function create()
    {
        return Inertia::render('CompanySettings/Create');
    }

    public function store(CompanySettingRequest $request)
    {
        $this->companySettingService->create(
            $request->attributesForPersistence(),
            $request->file('company_logo'),
            $request->file('pdf_watermark_image')
        );

        return redirect()
            ->route('company-settings.index')
            ->with('success', 'Company setting created successfully.');
    }

    public function show(CompanySetting $companySetting)
    {
        return Inertia::render('CompanySettings/Show', [
            'companySetting' => $companySetting,
        ]);
    }

    public function edit(CompanySetting $companySetting)
    {
        return Inertia::render('CompanySettings/Update', [
            'companySetting' => $companySetting,
        ]);
    }

    public function update(CompanySettingRequest $request, CompanySetting $companySetting)
    {
        $this->companySettingService->update(
            $companySetting,
            $request->attributesForPersistence(),
            $request->file('company_logo'),
            $request->file('pdf_watermark_image'),
            $request->boolean('remove_pdf_watermark')
        );

        return redirect()
            ->route('company-settings.index')
            ->with('success', 'Company setting updated successfully.');
    }

    public function destroy(CompanySetting $companySetting)
    {
        $this->companySettingService->delete($companySetting);

        return redirect()
            ->route('company-settings.index')
            ->with('success', 'Company setting deleted successfully.');
    }

    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'distinct', 'exists:company_settings,id'],
        ]);

        $deleted = $this->companySettingService->deleteMany($validated['ids']);

        return redirect()
            ->route('company-settings.index')
            ->with('success', "{$deleted} company settings deleted successfully.");
    }

    public function downloadPdf(Request $request)
    {
        $companySettings = $this->filteredQuery($request)->get();
        $companySetting = CompanySetting::first();

        $pdf = Pdf::loadView('pdf.company-settings', compact('companySettings', 'companySetting'));

        return $pdf->stream();
    }

    private function filteredQuery(Request $request): Builder
    {
        $query = CompanySetting::query();

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('company_name', 'like', "%{$search}%")
                    ->orWhere('company_email', 'like', "%{$search}%")
                    ->orWhere('company_mobile', 'like', "%{$search}%")
                    ->orWhere('company_phone', 'like', "%{$search}%")
                    ->orWhere('proprietor_name', 'like', "%{$search}%");
            });
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

        return $query->orderByDesc('created_at')->orderByDesc('id');
    }
}
