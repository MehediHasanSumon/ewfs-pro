<?php

namespace App\Http\Controllers;

use App\Models\SMSLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;

class SMSLogController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-s-m-s-log', only: ['index']),
            new Middleware('permission:delete-s-m-s-log', only: ['destroy', 'bulkDelete']),
        ];
    }

    public function index(Request $request)
    {
        $query = SMSLog::with(['smsTemplate:id,title', 'smsSetting:id,sender_id'])
            ->select('id', 'phone_number', 'message', 'sms_template_id', 'sms_setting_id', 'status', 'sent_at', 'error_message', 'created_at');

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('phone_number', 'like', '%' . $request->search . '%')
                    ->orWhere('message', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->status && $request->status !== 'all') {
            $query->where('status', $request->status);
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

        $perPage = $request->get('per_page', 10);
        $logs = $query->paginate($perPage)->withQueryString()->through(function ($log) {
            return [
                'id' => $log->id,
                'phone_number' => $log->phone_number,
                'message' => $log->message,
                'template' => $log->smsTemplate?->title,
                'sender_id' => $log->smsSetting?->sender_id,
                'status' => $log->status,
                'sent_at' => $log->sent_at?->format('Y-m-d H:i:s'),
                'error_message' => $log->error_message,
                'created_at' => $log->created_at->format('Y-m-d H:i:s'),
            ];
        });

        return Inertia::render('SMS/SMSLogs', [
            'logs' => $logs,
            'filters' => $request->only(['search', 'status', 'start_date', 'end_date', 'sort_by', 'sort_order', 'per_page'])
        ]);
    }

    public function destroy(SMSLog $smsLog)
    {
        $smsLog->delete();
        return redirect()->back()->with('success', 'SMS Log deleted successfully.');
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:sms_logs,id'
        ]);

        SMSLog::whereIn('id', $request->ids)->delete();

        return redirect()->back()->with('success', count($request->ids) . ' SMS logs deleted successfully.');
    }
}
