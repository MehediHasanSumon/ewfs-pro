<?php

namespace App\Http\Controllers;

use App\Helpers\SalaryPaymentHelper;
use App\Http\Requests\PayrollProcessRequest;
use App\Http\Resources\PayrollItemResource;
use App\Http\Resources\PayrollPeriodResource;
use App\Models\PayrollPeriod;
use App\Services\PayrollService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;

class SalaryPaymentController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly PayrollService $payroll
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware(
                'permission:'.SalaryPaymentHelper::viewPermission(),
                only: ['index']
            ),
            new Middleware(
                'permission:'.SalaryPaymentHelper::createPermission(),
                only: ['store']
            ),
        ];
    }

    public function index(Request $request)
    {
        $payrolls = PayrollPeriod::query()
            ->payable()
            ->withCount([
                'items',
                'items as pending_items_count' => fn ($query) => $query
                    ->where('status', 'pending'),
            ])
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get();
        $selectedId = $request->integer('payroll_id')
            ?: $payrolls->first()?->id;
        $period = $selectedId
            ? $payrolls->firstWhere('id', $selectedId)
            : null;

        if ($period) {
            $period->load([
                'items' => fn ($query) => $query
                    ->with([
                        'snapshot.paymentAccount:id,name,ac_number',
                        'employee.department:id,name',
                        'employee.designation:id,name',
                        'deductions',
                        'extras.voucherTransactionType:id,name,code',
                        'paymentVoucher:id,voucher_no,voucher_date',
                        'advanceAdjustmentVoucher:id,voucher_no,voucher_date',
                    ])
                    ->orderBy('employee_id'),
            ]);
        }

        return Inertia::render('SalaryPayments/Index', [
            'payrolls' => $payrolls
                ->map(fn (PayrollPeriod $payroll): array => [
                    'id' => $payroll->id,
                    'payroll_code' => $payroll->payroll_code,
                    'label' => $payroll->label(),
                    'month' => $payroll->month,
                    'year' => $payroll->year,
                    'status' => $payroll->status,
                    'items_count' => $payroll->items_count,
                    'pending_items_count' => $payroll->pending_items_count,
                ])
                ->values(),
            'payroll' => $period
                ? PayrollPeriodResource::make($period)->resolve($request)
                : null,
            'items' => $period
                ? PayrollItemResource::collection($period->items)->resolve($request)
                : [],
            'filters' => [
                'payroll_id' => $selectedId,
                'date' => $request->input('date', now()->toDateString()),
            ],
        ]);
    }

    public function store(PayrollProcessRequest $request)
    {
        $data = $request->validated();
        $period = PayrollPeriod::query()->findOrFail($data['payroll_id']);
        $this->payroll->pay($period, $data);

        return back()->with(
            'success',
            'Salary payment vouchers created successfully.'
        );
    }
}
