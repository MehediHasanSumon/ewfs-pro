<?php

namespace App\Http\Controllers;

use App\Models\SMSSetting;
use App\Models\CompanySetting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;

class SMSConfigController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-s-m-s-setting', only: ['index']),
            new Middleware('permission:create-s-m-s-setting', only: ['store']),
            new Middleware('permission:update-s-m-s-setting', only: ['edit', 'update']),
            new Middleware('permission:delete-s-m-s-setting', only: ['destroy', 'bulkDelete']),
            new Middleware('permission:can-s-m-s-setting-download', only: ['downloadPdf']),
        ];
    }

    public function index(Request $request)
    {
        $query = SMSSetting::select('id', 'url', 'api_key', 'sender_id', 'status', 'created_at');

        // Apply filters
        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('url', 'like', '%' . $request->search . '%')
                  ->orWhere('sender_id', 'like', '%' . $request->search . '%')
                  ->orWhere('api_key', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->status && $request->status !== 'all') {
            if ($request->status === 'active') {
                $query->where('status', true);
            } elseif ($request->status === 'inactive') {
                $query->where('status', false);
            }
        }

        if ($request->start_date) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->end_date) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginate
        $perPage = $request->get('per_page', 10);
        $configs = $query->paginate($perPage)->withQueryString()->through(function ($config) {
            return [
                'id' => $config->id,
                'url' => $config->url,
                'api_key' => $config->api_key,
                'sender_id' => $config->sender_id,
                'status' => $config->status,
                'created_at' => $config->created_at->format('Y-m-d'),
            ];
        });

        return Inertia::render('SMS/SMSConfig', [
            'configs' => $configs,
            'filters' => $request->only(['search', 'status', 'start_date', 'end_date', 'sort_by', 'sort_order', 'per_page'])
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'url' => 'required|url|max:255',
            'api_key' => 'required|string|max:255',
            'sender_id' => 'required|string|max:255',
            'status' => 'boolean'
        ]);

        SMSSetting::create($validated);
        return redirect()->back()->with('success', 'SMS Config created successfully.');
    }

    public function edit(SMSSetting $smsConfig)
    {
        return response()->json($smsConfig);
    }

    public function show(SMSSetting $smsConfig)
    {
        return response()->json($smsConfig);
    }

    public function update(Request $request, SMSSetting $smsConfig)
    {
        $validated = $request->validate([
            'url' => 'required|url|max:255',
            'api_key' => 'required|string|max:255',
            'sender_id' => 'required|string|max:255',
            'status' => 'boolean'
        ]);

        $smsConfig->update($validated);
        return redirect()->back()->with('success', 'SMS Config updated successfully.');
    }

    public function destroy(SMSSetting $smsConfig)
    {
        $smsConfig->delete();
        return redirect()->back()->with('success', 'SMS Config deleted successfully.');
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:sms_settings,id'
        ]);

        SMSSetting::whereIn('id', $request->ids)->delete();

        return redirect()->back()->with('success', count($request->ids) . ' SMS configs deleted successfully.');
    }

    public function downloadPdf(Request $request)
    {
        $query = SMSSetting::select('id', 'url', 'api_key', 'sender_id', 'status', 'created_at');

        // Apply same filters as index method
        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('url', 'like', '%' . $request->search . '%')
                  ->orWhere('sender_id', 'like', '%' . $request->search . '%')
                  ->orWhere('api_key', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->status && $request->status !== 'all') {
            if ($request->status === 'active') {
                $query->where('status', true);
            } elseif ($request->status === 'inactive') {
                $query->where('status', false);
            }
        }

        if ($request->start_date) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->end_date) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $configs = $query->get();
        $companySetting = CompanySetting::first();

        $pdf = Pdf::loadView('pdf.sms-configs', compact('configs', 'companySetting'));
        return $pdf->stream('sms-configs.pdf');
    }
}