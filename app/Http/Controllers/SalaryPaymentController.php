<?php

namespace App\Http\Controllers;

use App\Helpers\SalaryPaymentHelper;
use App\Http\Requests\PayrollProcessRequest;
use App\Http\Resources\PayrollItemResource;
use App\Http\Resources\PayrollPeriodResource;
use App\Models\PayrollPeriod;
use App\Services\PayrollService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
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
        $search = trim((string) $request->input('search', ''));
        $department = trim((string) $request->input('department', 'all'));
        $status = in_array($request->input('status'), ['pending', 'paid'], true)
            ? (string) $request->input('status')
            : 'all';
        $perPage = max(1, min($request->integer('per_page', 10), 100));

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
            $items = $period->items()
                ->with([
                    'snapshot.paymentAccount:id,name,ac_number',
                    'employee.department:id,name',
                    'employee.designation:id,name',
                    'deductions',
                    'extras.voucherTransactionType:id,name,code',
                    'paymentVoucher:id,voucher_no,voucher_date',
                    'advanceAdjustmentVoucher:id,voucher_no,voucher_date',
                ])
                ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                    $query
                        ->whereHas('snapshot', fn ($snapshot) => $snapshot
                            ->where('employee_name', 'like', '%'.$search.'%')
                            ->orWhere('employee_code', 'like', '%'.$search.'%'))
                        ->orWhereHas('employee', fn ($employee) => $employee
                            ->where('employee_name', 'like', '%'.$search.'%')
                            ->orWhere('employee_code', 'like', '%'.$search.'%'));
                }))
                ->when($department !== 'all', fn ($query) => $query->whereHas(
                    'snapshot',
                    fn ($snapshot) => $snapshot->where('department_name', $department)
                ))
                ->when($status !== 'all', fn ($query) => $query->where('status', $status))
                ->orderBy('employee_id')
                ->paginate($perPage)
                ->withQueryString();
            $items->through(
                fn ($item) => (new PayrollItemResource($item))->resolve($request)
            );
            $departments = $period->items()
                ->join(
                    'payroll_snapshots',
                    'payroll_snapshots.id',
                    '=',
                    'payroll_items.payroll_snapshot_id'
                )
                ->whereNotNull('payroll_snapshots.department_name')
                ->distinct()
                ->orderBy('payroll_snapshots.department_name')
                ->pluck('payroll_snapshots.department_name')
                ->values();
        } else {
            $items = new LengthAwarePaginator(
                [],
                0,
                $perPage,
                1,
                [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ]
            );
            $departments = collect();
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
            'items' => $items,
            'departments' => $departments,
            'filters' => [
                'payroll_id' => $selectedId,
                'search' => $search,
                'department' => $department,
                'status' => $status,
                'per_page' => $perPage,
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
