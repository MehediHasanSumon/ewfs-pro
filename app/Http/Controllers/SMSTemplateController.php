<?php

namespace App\Http\Controllers;

use App\Models\SMSTemplate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;

class SMSTemplateController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-s-m-s-template', only: ['index']),
            new Middleware('permission:create-s-m-s-template', only: ['store']),
            new Middleware('permission:update-s-m-s-template', only: ['update']),
            new Middleware('permission:delete-s-m-s-template', only: ['destroy', 'bulkDelete']),
            new Middleware('permission:can-s-m-s-template-download', only: ['downloadPdf']),
        ];
    }

    public function index(Request $request)
    {
        $query = SMSTemplate::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('type', 'like', "%{$search}%")
                  ->orWhere('message', 'like', "%{$search}%");
            });
        }

        if ($request->filled('sort_by')) {
            $sortBy = $request->sort_by;
            $sortOrder = $request->sort_order === 'desc' ? 'desc' : 'asc';
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $smsTemplates = $query->paginate(10)->withQueryString();

        return Inertia::render('SMS/SMSTemplate', [
            'smsTemplates' => $smsTemplates,
            'filters' => $request->only(['search', 'sort_by', 'sort_order'])
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|string|max:255',
            'message' => 'required|string',
            'status' => 'boolean'
        ]);

        SMSTemplate::create($request->all());

        return redirect()->back()->with('success', 'SMS Template created successfully.');
    }

    public function update(Request $request, SMSTemplate $smsTemplate)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|string|max:255',
            'message' => 'required|string',
            'status' => 'boolean'
        ]);

        $smsTemplate->update($request->all());

        return redirect()->back()->with('success', 'SMS Template updated successfully.');
    }

    public function destroy(SMSTemplate $smsTemplate)
    {
        $smsTemplate->delete();
        return redirect()->back()->with('success', 'SMS Template deleted successfully.');
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:sms_templates,id'
        ]);

        SMSTemplate::whereIn('id', $request->ids)->delete();

        return redirect()->back()->with('success', 'SMS Templates deleted successfully.');
    }

    public function downloadPdf(Request $request)
    {
        $query = SMSTemplate::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('type', 'like', "%{$search}%")
                  ->orWhere('message', 'like', "%{$search}%");
            });
        }

        if ($request->filled('sort_by')) {
            $sortBy = $request->sort_by;
            $sortOrder = $request->sort_order === 'desc' ? 'desc' : 'asc';
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $smsTemplates = $query->get();

        $pdf = Pdf::loadView('pdf.sms-templates', compact('smsTemplates'));
        return $pdf->download('sms-templates.pdf');
    }
}
