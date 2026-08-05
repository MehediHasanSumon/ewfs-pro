<?php

namespace App\Http\Controllers;

use App\Http\Requests\SMSTemplateRequest;
use App\Models\SMSTemplate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;

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
        $perPage = max(1, min($request->integer('per_page', 10), 100));
        $smsTemplates = $this->filteredQuery($request)
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('SMS/SMSTemplate', [
            'smsTemplates' => $smsTemplates,
            'filters' => $request->only(['search', 'sort_by', 'sort_order', 'per_page']),
        ]);
    }

    public function store(SMSTemplateRequest $request)
    {
        SMSTemplate::create($request->validated());

        return redirect()->back()->with('success', 'SMS Template created successfully.');
    }

    public function update(SMSTemplateRequest $request, SMSTemplate $smsTemplate)
    {
        $smsTemplate->update($request->validated());

        return redirect()->back()->with('success', 'SMS Template updated successfully.');
    }

    public function destroy(SMSTemplate $smsTemplate)
    {
        $smsTemplate->delete();

        return redirect()->back()->with('success', 'SMS Template deleted successfully.');
    }

    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'distinct', 'exists:sms_templates,id'],
        ]);

        $deleted = SMSTemplate::query()->whereKey($validated['ids'])->delete();

        return redirect()->back()->with('success', "{$deleted} SMS templates deleted successfully.");
    }

    public function downloadPdf(Request $request)
    {
        $smsTemplates = $this->filteredQuery($request)->get();
        $pdf = Pdf::loadView('pdf.sms-templates', compact('smsTemplates'));

        return $pdf->stream('sms-templates.pdf');
    }

    private function filteredQuery(Request $request): Builder
    {
        $query = SMSTemplate::query();

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('title', 'like', "%{$search}%")
                    ->orWhere('type', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%");
            });
        }

        $allowedSorts = ['id', 'title', 'type', 'status', 'created_at'];
        $sortBy = in_array($request->input('sort_by'), $allowedSorts, true)
            ? $request->input('sort_by')
            : 'created_at';
        $sortOrder = $request->input('sort_order') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sortBy, $sortOrder)->orderByDesc('id');
    }
}
