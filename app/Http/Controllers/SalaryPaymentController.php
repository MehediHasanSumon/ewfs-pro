<?php

namespace App\Http\Controllers;

use App\Helpers\SalaryPaymentHelper;
use App\Helpers\VoucherCategoryHelper;
use App\Helpers\VoucherTransactionTypeHelper;
use App\Http\Requests\SalaryPaymentRequest;
use App\Http\Resources\SalaryPaymentEmployeeResource;
use App\Models\EmpDepartment;
use App\Models\EmpDesignation;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftClosing;
use App\Models\VoucherTransactionType;
use App\Services\SalaryPaymentService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Collection;
use Inertia\Inertia;

class SalaryPaymentController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly SalaryPaymentService $salaryPayments
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
        $month = $this->month($request);
        $year = $this->year($request);
        $date = $this->date($request);
        $transactionTypes = $this->paymentTransactionTypes();
        $selectedTransactionType = $this->selectedTransactionType(
            $request,
            $transactionTypes
        );
        $isMonthlySalary = $selectedTransactionType?->code
            === VoucherTransactionTypeHelper::monthlySalaryCode();
        $query = Employee::query()
            ->where('status', true)
            ->with([
                'account:id,name,ac_number,status',
                'paymentAccount.group:id,code,name,account_class,status',
                'salaryStructure',
                'department:id,name',
                'designation:id,name',
                'salaryPayments' => fn ($salaryPayments) => (
                    $selectedTransactionType
                        ? $salaryPayments->where(
                            'voucher_transaction_type_id',
                            $selectedTransactionType->id
                        )
                        : $salaryPayments->whereRaw('1 = 0')
                )
                    ->forPeriod($month, $year)
                    ->with([
                        'paymentVoucher:id,voucher_no,voucher_date,journal_entry_id',
                        'paymentVoucher.journalEntry:id,status',
                    ]),
            ])
            ->when(
                $request->filled('search'),
                fn (Builder $employeeQuery) => $employeeQuery
                    ->where(function (Builder $searchQuery) use ($request) {
                        $search = trim((string) $request->input('search'));
                        $searchQuery
                            ->where('employee_code', 'like', "%{$search}%")
                            ->orWhere(
                                'employee_name',
                                'like',
                                "%{$search}%"
                            );
                    })
            )
            ->when(
                $request->integer('department_id'),
                fn (Builder $employeeQuery, int $departmentId) => $employeeQuery
                    ->where('department_id', $departmentId)
            )
            ->when(
                $request->integer('designation_id'),
                fn (Builder $employeeQuery, int $designationId) => $employeeQuery
                    ->where('designation_id', $designationId)
            )
            ->orderBy('employee_name')
            ->orderBy('id');

        $employees = $query
            ->paginate($this->perPage($request))
            ->withQueryString()
            ->through(
                fn (Employee $employee) => (
                    new SalaryPaymentEmployeeResource(
                        $employee,
                        $isMonthlySalary
                    )
                )->resolve($request)
            );

        return Inertia::render('SalaryPayments/Index', [
            'employees' => $employees,
            'departments' => EmpDepartment::query()
                ->active()
                ->orderBy('name')
                ->get(['id', 'name']),
            'designations' => EmpDesignation::query()
                ->active()
                ->orderBy('name')
                ->get(['id', 'name']),
            'shifts' => Shift::query()
                ->where('status', true)
                ->orderBy('display_order')
                ->orderBy('name')
                ->get(['id', 'name']),
            'closedShiftIds' => ShiftClosing::query()
                ->posted()
                ->whereDate('business_date', $date)
                ->pluck('shift_id'),
            'transactionTypes' => $transactionTypes
                ->map(fn (VoucherTransactionType $transactionType) => [
                    'id' => $transactionType->id,
                    'code' => $transactionType->code,
                    'name' => $transactionType->name,
                    'is_monthly_salary' => $transactionType->code
                        === VoucherTransactionTypeHelper::monthlySalaryCode(),
                ])
                ->values(),
            'filters' => [
                'search' => (string) $request->input('search', ''),
                'department_id' => $request->integer('department_id') ?: null,
                'designation_id' => $request->integer('designation_id') ?: null,
                'salary_month' => $month,
                'salary_year' => $year,
                'date' => $date,
                'shift_id' => $request->integer('shift_id') ?: null,
                'voucher_transaction_type_id' => $selectedTransactionType?->id,
                'per_page' => $this->perPage($request),
            ],
        ]);
    }

    public function store(SalaryPaymentRequest $request)
    {
        $payments = $this->salaryPayments->pay($request->validated());

        return back()->with(
            'success',
            "{$payments->count()} employee payment voucher(s) created successfully."
        );
    }

    /**
     * @return Collection<int, VoucherTransactionType>
     */
    private function paymentTransactionTypes(): Collection
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
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'sort_order']);
    }

    /**
     * @param  Collection<int, VoucherTransactionType>  $transactionTypes
     */
    private function selectedTransactionType(
        Request $request,
        Collection $transactionTypes
    ): ?VoucherTransactionType {
        $selected = $transactionTypes->firstWhere(
            'id',
            $request->integer('voucher_transaction_type_id')
        );

        return $selected
            ?? $transactionTypes->firstWhere(
                'code',
                VoucherTransactionTypeHelper::monthlySalaryCode()
            )
            ?? $transactionTypes->first();
    }

    private function month(Request $request): int
    {
        $month = $request->integer('salary_month', (int) now()->format('n'));

        return $month >= 1 && $month <= 12
            ? $month
            : (int) now()->format('n');
    }

    private function year(Request $request): int
    {
        $year = $request->integer('salary_year', (int) now()->format('Y'));

        return $year >= 2000 && $year <= 2100
            ? $year
            : (int) now()->format('Y');
    }

    private function date(Request $request): string
    {
        try {
            return Carbon::parse(
                $request->input('date', now()->toDateString())
            )->toDateString();
        } catch (\Throwable) {
            return now()->toDateString();
        }
    }

    private function perPage(Request $request): int
    {
        return min(100, max(10, $request->integer('per_page', 25)));
    }
}
