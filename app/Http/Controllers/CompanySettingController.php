<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Validator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;

class CompanySettingController extends Controller implements HasMiddleware
{
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
        $query = CompanySetting::query();

        // Apply search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('company_name', 'like', "%{$search}%")
                  ->orWhere('company_email', 'like', "%{$search}%")
                  ->orWhere('company_mobile', 'like', "%{$search}%")
                  ->orWhere('company_phone', 'like', "%{$search}%")
                  ->orWhere('proprietor_name', 'like', "%{$search}%");
            });
        }

        // Apply status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Apply date range filter
        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $companySettings = $query->orderBy('created_at', 'desc')->get();
        
        return Inertia::render('CompanySettings/CompanySettings', [
            'companySettings' => $companySettings,
            'filters' => $request->only(['search', 'status', 'start_date', 'end_date'])
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'company_name' => ['required', 'string', 'max:255'],
        ]);
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }
        $input = $request->all();

        if ($request->hasfile('company_logo')) {
            $image = $request->file('company_logo');
            $name = date('d-m-Y-H-i-s') . '_' . $image->getClientOriginalName();
            $path = Storage::disk('public')->putFileAs('images/company', $image, $name);
            $input['company_logo'] = '/' . $path;
        }

        CompanySetting::create($input);
        return redirect()->route('company-settings.index');
    }

    public function create()
    {
        return Inertia::render('CompanySettings/Create');
    }

    public function show(CompanySetting $companySetting)
    {
        return Inertia::render('CompanySettings/Show', [
            'companySetting' => $companySetting
        ]);
    }

    public function edit(CompanySetting $companySetting)
    {
        return Inertia::render('CompanySettings/Update', [
            'companySetting' => $companySetting
        ]);
    }

    public function update(Request $request, CompanySetting $companySetting)
    {
        $validator = Validator::make($request->all(), [
            'company_name' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $input = $request->all();
        
        if (isset($input['is_registration'])) {
            $input['is_registration'] = filter_var($input['is_registration'], FILTER_VALIDATE_BOOLEAN);
        }

        if ($request->hasfile('company_logo')) {
            if ($companySetting->company_logo) {
                Storage::disk('public')->delete(ltrim($companySetting->company_logo, '/'));
            }
            $file = $request->file('company_logo');
            $name = date('d-m-Y-H-i-s') . '_' . $file->getClientOriginalName();
            $path = Storage::disk('public')->putFileAs('images/company', $file, $name);
            $input['company_logo'] = '/' . $path;
        }

        $companySetting->update($input);
        return redirect()->route('company-settings.index');
    }

    public function destroy(CompanySetting $companySetting)
    {
        if ($companySetting->company_logo) {
            Storage::disk('public')->delete(ltrim($companySetting->company_logo, '/'));
        }
        
        $companySetting->delete();
        return redirect()->route('company-settings.index');
    }

    public function bulkDelete(Request $request)
    {
        CompanySetting::whereIn('id', $request->ids)->delete();
        return redirect()->route('company-settings.index');
    }

    public function downloadPdf(Request $request)
    {
        $query = CompanySetting::query();

        // Apply same filters as index method
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('company_name', 'like', "%{$search}%")
                  ->orWhere('company_email', 'like', "%{$search}%")
                  ->orWhere('company_mobile', 'like', "%{$search}%")
                  ->orWhere('company_phone', 'like', "%{$search}%")
                  ->orWhere('proprietor_name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $companySettings = $query->orderBy('created_at', 'desc')->get();
        $companySetting = CompanySetting::first(); // For header consistency

        $pdf = Pdf::loadView('pdf.company-settings', compact('companySettings', 'companySetting'));
        return $pdf->stream();
    }
}