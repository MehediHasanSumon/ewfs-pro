<?php

namespace App\Http\Controllers;

use App\Helpers\SalaryPaymentHelper;
use App\Http\Requests\PayrollPeriodRequest;
use App\Http\Requests\PayrollProcessRequest;
use App\Http\Resources\PayrollItemResource;
use App\Http\Resources\PayrollPeriodResource;
use App\Jobs\ProcessPayrollBatch;
use App\Models\PayrollPeriod;
use App\Services\PayrollService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;

class PayrollPeriodController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly PayrollService $payroll
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware(
                'permission:'.SalaryPaymentHelper::payrollViewPermission(),
                only: ['index', 'processing']
            ),
            new Middleware(
                'permission:'.SalaryPaymentHelper::payrollHistoryPermission(),
                only: ['history']
            ),
            new Middleware(
                'permission:'.SalaryPaymentHelper::payrollCreatePermission(),
                only: ['store', 'start']
            ),
            new Middleware(
                'permission:'.SalaryPaymentHelper::payrollProcessPermission(),
                only: ['process', 'lock']
            ),
        ];
    }

    public function index(Request $request)
    {
        $periods = PayrollPeriod::query()
            ->when(
                $request->integer('employee_id'),
                fn ($query, int $employeeId) => $query->whereHas(
                    'items',
                    fn ($items) => $items->where('employee_id', $employeeId)
                )
            )
            ->withCount(['snapshots', 'items'])
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->paginate(12)
            ->withQueryString();
        $periods->through(
            fn (PayrollPeriod $period) => (new PayrollPeriodResource($period))
                ->resolve($request)
        );

        return Inertia::render('Payroll/Periods', [
            'periods' => $periods,
            'filters' => $request->only(['page', 'employee_id']),
        ]);
    }

    public function store(PayrollPeriodRequest $request)
    {
        $this->payroll->createPeriod($request->validated());

        return back()->with('success', 'Payroll period created successfully.');
    }

    public function start(PayrollPeriod $period)
    {
        $this->payroll->start($period);

        return redirect()
            ->route('payroll.processing', $period)
            ->with('success', 'Payroll snapshots created successfully.');
    }

    public function processing(PayrollPeriod $period)
    {
        abort_if($period->status === PayrollPeriod::STATUS_DRAFT, 404);

        $period->load([
            'items' => fn ($query) => $query
                ->with([
                    'snapshot.paymentAccount:id,name,ac_number',
                    'employee.department:id,name',
                    'employee.designation:id,name',
                    'paymentVoucher:id,voucher_no',
                    'advanceAdjustmentVoucher:id,voucher_no',
                ])
                ->orderBy('employee_id'),
        ]);

        return Inertia::render('Payroll/Processing', [
            'period' => PayrollPeriodResource::make($period)->resolve(),
            'items' => PayrollItemResource::collection($period->items)->resolve(),
            'canProcess' => $period->status === PayrollPeriod::STATUS_PROCESSING,
        ]);
    }

    public function process(PayrollProcessRequest $request, PayrollPeriod $period)
    {
        $data = $request->validated();
        $queueThreshold = max(
            1,
            (int) config('erp.payroll.queue_threshold', 100)
        );

        if (count($data['employee_ids']) >= $queueThreshold) {
            ProcessPayrollBatch::dispatch(
                $period->id,
                $data,
                $request->user()?->id
            );

            return back()->with(
                'success',
                'Payroll batch queued for processing successfully.'
            );
        }

        $this->payroll->process($period, $data);

        return back()->with('success', 'Selected payroll items processed successfully.');
    }

    public function lock(PayrollPeriod $period)
    {
        $this->payroll->lock($period);

        return back()->with('success', 'Payroll period locked successfully.');
    }

    public function history(Request $request)
    {
        $periods = PayrollPeriod::query()
            ->whereIn('status', [
                PayrollPeriod::STATUS_COMPLETED,
                PayrollPeriod::STATUS_LOCKED,
            ])
            ->withCount(['snapshots', 'items'])
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->paginate(12)
            ->withQueryString();
        $periods->through(
            fn (PayrollPeriod $period) => (new PayrollPeriodResource($period))
                ->resolve($request)
        );

        return Inertia::render('Payroll/History', [
            'periods' => $periods,
        ]);
    }
}
