<?php

namespace App\Http\Controllers;

use App\Helpers\SalaryPaymentHelper;
use App\Helpers\VoucherCategoryHelper;
use App\Helpers\VoucherTransactionTypeHelper;
use App\Http\Requests\PayrollGenerateRequest;
use App\Http\Requests\PayrollPeriodRequest;
use App\Http\Requests\PayrollProcessRequest;
use App\Http\Resources\PayrollItemResource;
use App\Http\Resources\PayrollPeriodResource;
use App\Jobs\ProcessPayrollBatch;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\VoucherTransactionType;
use App\Services\PaymentAccountService;
use App\Services\PayrollService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;

class PayrollPeriodController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly PayrollService $payroll,
        private readonly PaymentAccountService $paymentAccounts
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
                only: ['store', 'update', 'generate', 'start', 'cancel', 'destroy']
            ),
            new Middleware(
                'permission:'.SalaryPaymentHelper::payrollProcessPermission(),
                only: ['process']
            ),
        ];
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->input('search', ''));
        $status = in_array($request->input('status'), [
            PayrollPeriod::STATUS_DRAFT,
            PayrollPeriod::STATUS_PROCESSING,
            PayrollPeriod::STATUS_GENERATED,
            PayrollPeriod::STATUS_PAID,
            PayrollPeriod::STATUS_CANCELLED,
        ], true)
            ? (string) $request->input('status')
            : 'all';
        $month = $request->integer('month');
        $month = $month >= 1 && $month <= 12 ? $month : null;
        $year = $request->integer('year');
        $year = $year >= 2000 && $year <= 2100 ? $year : null;
        $perPage = max(1, min($request->integer('per_page', 10), 100));

        $periods = PayrollPeriod::query()
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query
                    ->where('payroll_code', 'like', '%'.$search.'%')
                    ->orWhere('year', 'like', '%'.$search.'%');
            }))
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($month, fn ($query, int $month) => $query->where('month', $month))
            ->when($year, fn ($query, int $year) => $query->where('year', $year))
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
            ->paginate($perPage)
            ->withQueryString();
        $periods->through(
            fn (PayrollPeriod $period) => (new PayrollPeriodResource($period))
                ->resolve($request)
        );

        return Inertia::render('Payroll/Periods', [
            'periods' => $periods,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'month' => $month,
                'year' => $year,
                'per_page' => $perPage,
                'employee_id' => $request->integer('employee_id') ?: null,
            ],
        ]);
    }

    public function store(PayrollPeriodRequest $request)
    {
        $this->payroll->createPeriod($request->validated());

        return back()->with('success', 'Payroll created successfully.');
    }

    public function update(
        PayrollPeriodRequest $request,
        PayrollPeriod $period
    ) {
        $this->payroll->updatePeriod($period, $request->validated());

        return back()->with('success', 'Payroll updated successfully.');
    }

    public function start(PayrollPeriod $period)
    {
        $this->payroll->start($period);

        return redirect()
            ->route('payroll.processing', $period)
            ->with('success', 'Payroll generated successfully.');
    }

    public function generate(
        PayrollGenerateRequest $request,
        PayrollPeriod $period
    ) {
        $this->payroll->generate($period, $request->validated());

        return redirect()
            ->route('payroll.processing', $period)
            ->with('success', 'Payroll generated successfully.');
    }

    public function processing(PayrollPeriod $period)
    {
        $period->loadCount(['snapshots', 'items']);

        $employees = collect();
        if ($period->status === PayrollPeriod::STATUS_DRAFT) {
            $employees = Employee::query()
                ->where('status', true)
                ->whereHas('salaryStructure')
                ->with([
                    'department:id,name',
                    'designation:id,name',
                    'paymentAccount:id,name,ac_number',
                ])
                ->orderBy('employee_name')
                ->get()
                ->map(fn (Employee $employee): array => [
                    'id' => $employee->id,
                    'employee_code' => $employee->employee_code,
                    'employee_name' => $employee->employee_name,
                    'department' => $employee->department?->name,
                    'designation' => $employee->designation?->name,
                    'monthly_salary' => (float) (
                        $employee->salaryStructure?->gross_salary ?? 0
                    ),
                    'payment_method' => $employee->paymentAccount
                        ? $this->paymentAccounts->methodFor(
                            $employee->paymentAccount
                        )
                        : null,
                    'payment_account' => $employee->paymentAccount ? [
                        'id' => $employee->paymentAccount->id,
                        'name' => $employee->paymentAccount->name,
                        'ac_number' => $employee->paymentAccount->ac_number,
                    ] : null,
                ]);
        }

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
        $canGenerate = in_array($period->status, [
            PayrollPeriod::STATUS_DRAFT,
            PayrollPeriod::STATUS_GENERATED,
        ], true) && ! $period->hasVoucherHistory();

        if (
            $period->status === PayrollPeriod::STATUS_GENERATED
            && $canGenerate
        ) {
            $employees = $period->items
                ->map(function ($item): array {
                    $snapshot = $item->snapshot;

                    return [
                        'id' => $item->employee_id,
                        'employee_code' => $snapshot?->employee_code,
                        'employee_name' => $snapshot?->employee_name,
                        'department' => $snapshot?->department_name,
                        'designation' => $snapshot?->designation_name,
                        'monthly_salary' => (float) (
                            $snapshot?->monthly_salary
                            ?? $item->monthly_salary
                        ),
                        'payment_method' => $snapshot?->payment_method,
                        'payment_account' => $snapshot?->paymentAccount ? [
                            'id' => $snapshot->paymentAccount->id,
                            'name' => $snapshot->paymentAccount->name,
                            'ac_number' => $snapshot->paymentAccount->ac_number,
                        ] : null,
                        'deductions' => $item->deductions
                            ->map(fn ($deduction): array => [
                                'amount' => (string) $deduction->amount,
                                'reason' => $deduction->reason,
                            ])
                            ->values(),
                        'extras' => $item->extras
                            ->map(fn ($extra): array => [
                                'voucher_transaction_type_id' => (string) $extra->voucher_transaction_type_id,
                                'amount' => (string) $extra->amount,
                                'remarks' => (string) ($extra->remarks ?? ''),
                            ])
                            ->values(),
                    ];
                })
                ->values();
        }

        return Inertia::render('Payroll/Processing', [
            'period' => PayrollPeriodResource::make($period)->resolve(),
            'employees' => $employees,
            'items' => PayrollItemResource::collection($period->items)->resolve(),
            'extraTypes' => $this->extraTypes(),
            'canGenerate' => $canGenerate,
            'canProcess' => false,
        ]);
    }

    public function process(
        PayrollProcessRequest $request,
        PayrollPeriod $period
    ) {
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
                'Salary payment batch queued successfully.'
            );
        }

        $this->payroll->pay($period, $data);

        return back()->with('success', 'Salary payment vouchers created successfully.');
    }

    public function cancel(PayrollPeriod $period)
    {
        $this->payroll->cancel($period);

        return back()->with('success', 'Payroll cancelled successfully.');
    }

    public function destroy(PayrollPeriod $period)
    {
        $this->payroll->delete($period);

        return redirect()
            ->route('payroll.periods.index')
            ->with('success', 'Payroll deleted successfully.');
    }

    public function history(Request $request)
    {
        $search = trim((string) $request->input('search', ''));
        $status = in_array($request->input('status'), [
            PayrollPeriod::STATUS_PAID,
            PayrollPeriod::STATUS_CANCELLED,
        ], true)
            ? (string) $request->input('status')
            : 'all';
        $month = $request->integer('month');
        $month = $month >= 1 && $month <= 12 ? $month : null;
        $year = $request->integer('year');
        $year = $year >= 2000 && $year <= 2100 ? $year : null;
        $perPage = max(1, min($request->integer('per_page', 10), 100));

        $periods = PayrollPeriod::query()
            ->whereIn('status', [
                PayrollPeriod::STATUS_PAID,
                PayrollPeriod::STATUS_CANCELLED,
            ])
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query
                    ->where('payroll_code', 'like', '%'.$search.'%')
                    ->orWhere('year', 'like', '%'.$search.'%');
            }))
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($month, fn ($query, int $month) => $query->where('month', $month))
            ->when($year, fn ($query, int $year) => $query->where('year', $year))
            ->withCount(['snapshots', 'items'])
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->paginate($perPage)
            ->withQueryString();
        $periods->through(
            fn (PayrollPeriod $period) => (new PayrollPeriodResource($period))
                ->resolve($request)
        );

        return Inertia::render('Payroll/History', [
            'periods' => $periods,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'month' => $month,
                'year' => $year,
                'per_page' => $perPage,
            ],
        ]);
    }

    private function extraTypes()
    {
        return VoucherTransactionType::query()
            ->whereHas(
                'voucherCategory',
                fn (Builder $query) => $query
                    ->where('code', VoucherCategoryHelper::employeeCode())
                    ->where('status', true)
            )
            ->active()
            ->forVoucherType(
                VoucherTransactionTypeHelper::paymentVoucherType()
            )
            ->where(
                'code',
                '!=',
                VoucherTransactionTypeHelper::monthlySalaryCode()
            )
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'sort_order']);
    }
}
