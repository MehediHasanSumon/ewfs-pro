<?php

namespace App\Http\Controllers;

use App\Helpers\VoucherTransactionTypeHelper;
use App\Http\Requests\EmployeeRequest;
use App\Http\Resources\EmployeeResource;
use App\Models\CompanySetting;
use App\Models\EmpDepartment;
use App\Models\EmpDesignation;
use App\Models\Employee;
use App\Models\EmpType;
use App\Models\Group;
use App\Services\EmployeeProfileService;
use App\Services\PartyLedgerService;
use App\Services\PaymentAccountService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class EmployeeController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly PartyLedgerService $partyLedger,
        private readonly EmployeeProfileService $employeeProfiles,
        private readonly PaymentAccountService $paymentAccounts
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-employee|can-employee-download', only: ['index', 'show', 'statement', 'downloadPaymentsPdf', 'downloadReceiptsPdf', 'downloadPdf']),
            new Middleware('permission:create-employee', only: ['create', 'store']),
            new Middleware('permission:update-employee', only: ['edit', 'update']),
            new Middleware('permission:delete-employee', only: ['destroy', 'bulkDelete']),
        ];
    }

    public function index(Request $request)
    {
        $query = Employee::query()->with([
            'account:id,name,ac_number',
            'empType:id,name',
            'department:id,name',
            'designation:id,name',
        ]);

        if ($request->search) {
            $query->where(function ($builder) use ($request) {
                $builder->where('employee_name', 'like', '%'.$request->search.'%')
                    ->orWhere('employee_code', 'like', '%'.$request->search.'%')
                    ->orWhere('email', 'like', '%'.$request->search.'%')
                    ->orWhere('mobile', 'like', '%'.$request->search.'%');
            });
        }

        if ($request->status && $request->status !== 'all') {
            $query->where('status', $request->status === 'active');
        }

        [$sortBy, $sortOrder] = $this->sorting($request);
        $employees = $query
            ->orderBy($sortBy, $sortOrder)
            ->paginate(max(1, min((int) $request->get('per_page', 10), 100)))
            ->withQueryString()
            ->through(fn (Employee $employee) => [
                'id' => $employee->id,
                'employee_code' => $employee->employee_code,
                'employee_name' => $employee->employee_name,
                'email' => $employee->email,
                'mobile' => $employee->mobile,
                'joining_date' => $employee->joining_date?->format('Y-m-d'),
                'job_status' => $employee->job_status,
                'status' => $employee->status,
                'emp_type' => $employee->empType,
                'department' => $employee->department,
                'designation' => $employee->designation,
                'account' => $employee->account,
                'created_at' => $employee->created_at->format('Y-m-d'),
            ]);

        return Inertia::render('Employee/Index', [
            'employees' => $employees,
            ...$this->formOptions(),
            'filters' => $request->only(['search', 'status', 'sort_by', 'sort_order', 'per_page']),
        ]);
    }

    public function create()
    {
        $lastEmployee = Employee::query()
            ->with('account.group:id,code')
            ->latest('id')
            ->first();
        $lastEmployeeGroup = $lastEmployee?->account?->group
            ? [
                'id' => $lastEmployee->account->group->id,
                'code' => $lastEmployee->account->group->code,
            ]
            : null;

        return Inertia::render('Employee/Create', [
            ...$this->formOptions(),
            'lastEmployeeGroup' => $lastEmployeeGroup,
        ]);
    }

    public function store(EmployeeRequest $request)
    {
        $this->employeeProfiles->create($request->validated());

        return redirect()->route('employees.index')->with('success', 'Employee created successfully.');
    }

    public function show(Employee $employee)
    {
        $employee->load(
            'account',
            'paymentAccount.group',
            'empType',
            'department',
            'designation',
            'salaryStructure'
        );

        $salaryQuery = $this->employeeVouchers(
            $employee,
            'payment',
            [VoucherTransactionTypeHelper::monthlySalaryCode()]
        );
        $advanceQuery = $this->employeeVouchers(
            $employee,
            'payment',
            [VoucherTransactionTypeHelper::employeeSalaryAdvanceCode()]
        );
        $metrics = $this->partyLedger->employeeFinancialMetric($employee);

        $recentSalaryPayments = $this->partyLedger->voucherRows(
            $salaryQuery->orderByDesc('voucher_date')->orderByDesc('id')->limit(5)->get(),
            'Paid'
        );
        $recentAdvancedPayments = $this->partyLedger->voucherRows(
            $advanceQuery->orderByDesc('voucher_date')->orderByDesc('id')->limit(5)->get(),
            'Given'
        );

        return Inertia::render('Employee/Show', [
            'employee' => EmployeeResource::make($employee)->resolve(),
            'recentSalaryPayments' => $recentSalaryPayments,
            'recentAdvancedPayments' => $recentAdvancedPayments,
            'financialMetrics' => $metrics,
        ]);
    }

    public function edit(Employee $employee)
    {
        $employee->load(
            'account',
            'paymentAccount.group',
            'empType',
            'department',
            'designation',
            'salaryStructure'
        );

        return Inertia::render('Employee/Update', [
            'employee' => EmployeeResource::make($employee)->resolve(),
            ...$this->formOptions(),
        ]);
    }

    public function update(EmployeeRequest $request, Employee $employee)
    {
        $this->employeeProfiles->update($employee, $request->validated());

        return redirect()->route('employees.index')->with('success', 'Employee updated successfully.');
    }

    public function destroy(Employee $employee)
    {
        $this->deleteEmployee($employee);

        return redirect()->back()->with('success', 'Employee deleted successfully.');
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['exists:employees,id'],
        ]);

        $employees = Employee::query()
            ->whereIn('id', $request->ids)
            ->with('account')
            ->get();
        $files = $employees
            ->flatMap(fn (Employee $employee) => $this->employeeProfiles->filePaths($employee))
            ->all();

        DB::transaction(function () use ($employees) {
            foreach ($employees as $employee) {
                $this->assertEmployeeCanBeDeleted($employee);
            }

            foreach ($employees as $employee) {
                $account = $employee->account;
                $employee->delete();
                $account?->delete();
            }
        });

        $this->employeeProfiles->deleteStoredFiles($files);

        return redirect()->back()->with('success', 'Selected employees deleted successfully.');
    }

    public function statement(Request $request, Employee $employee)
    {
        $employee->load('account:id,name,ac_number');
        $view = in_array($request->input('view'), [
            'salary',
            'advance',
            'loan',
        ], true)
            ? (string) $request->input('view')
            : 'all';
        $perPage = max(1, min($request->integer('per_page', 10), 100));
        [$paymentCodes, $receiptCodes] = match ($view) {
            'salary' => [
                [VoucherTransactionTypeHelper::monthlySalaryCode()],
                [],
            ],
            'advance' => [
                [VoucherTransactionTypeHelper::employeeSalaryAdvanceCode()],
                [VoucherTransactionTypeHelper::employeeAdvanceReturnCode()],
            ],
            'loan' => [
                [VoucherTransactionTypeHelper::employeePersonalLoanCode()],
                [VoucherTransactionTypeHelper::employeeLoanRecoveryCode()],
            ],
            default => [null, null],
        };
        $payments = $this->partyLedger->paginatedVoucherRows(
            $this->employeeVouchers(
                $employee,
                'payment',
                $paymentCodes,
                $request->start_date,
                $request->end_date
            ),
            $perPage,
            'Paid',
            'payment_page'
        );
        $receipts = $this->partyLedger->paginatedVoucherRows(
            $this->employeeVouchers(
                $employee,
                'receipt',
                $receiptCodes,
                $request->start_date,
                $request->end_date
            ),
            $perPage,
            'Received',
            'receipt_page'
        );

        return Inertia::render('Employee/EmployeeStatement', [
            'employee' => [
                'id' => $employee->id,
                'employee_name' => $employee->employee_name,
                'mobile' => $employee->mobile,
                'present_address' => $employee->present_address,
                'account' => $employee->account,
            ],
            'payments' => $payments,
            'receipts' => $receipts,
            'currentBalance' => $this->statementBalance(
                $employee,
                $view
            ),
            'view' => $view,
            'filters' => [
                'start_date' => (string) $request->input('start_date', ''),
                'end_date' => (string) $request->input('end_date', ''),
                'per_page' => $perPage,
            ],
        ]);
    }

    public function downloadPaymentsPdf(Request $request, Employee $employee)
    {
        $employee->load('account');
        $payments = $this->partyLedger->voucherRows(
            $this->employeeVouchers(
                $employee,
                'payment',
                null,
                $request->start_date,
                $request->end_date
            )
                ->orderByDesc('voucher_date')
                ->orderByDesc('id')
                ->get(),
            'Paid'
        );
        $companySetting = CompanySetting::first();
        $pdf = Pdf::loadView(
            'pdf.employee-payments',
            compact('employee', 'payments', 'companySetting')
        );

        return $pdf->stream('employee-payments.pdf');
    }

    public function downloadReceiptsPdf(Request $request, Employee $employee)
    {
        $employee->load('account');
        $receipts = $this->partyLedger->voucherRows(
            $this->employeeVouchers(
                $employee,
                'receipt',
                null,
                $request->start_date,
                $request->end_date
            )
                ->orderByDesc('voucher_date')
                ->orderByDesc('id')
                ->get(),
            'Received'
        );
        $companySetting = CompanySetting::first();
        $pdf = Pdf::loadView(
            'pdf.employee-receipts',
            compact('employee', 'receipts', 'companySetting')
        );

        return $pdf->stream('employee-receipts.pdf');
    }

    public function downloadPdf()
    {
        $employees = Employee::query()
            ->with('empType', 'department', 'designation')
            ->orderBy('employee_name')
            ->get();
        $companySetting = CompanySetting::first();
        $pdf = Pdf::loadView('pdf.employees', compact('employees', 'companySetting'));

        return $pdf->stream('employees.pdf');
    }

    private function employeeVouchers(
        Employee $employee,
        string $type,
        ?array $subTypeCodes = null,
        ?string $startDate = null,
        ?string $endDate = null
    ): Builder {
        return $this->partyLedger
            ->vouchers('employee_id', $employee->id, $type, $startDate, $endDate)
            ->when($subTypeCodes !== null, fn (Builder $query) => $query
                ->whereHas('voucherTransactionType', fn (Builder $subType) => $subType
                    ->whereIn('code', $subTypeCodes)));
    }

    private function statementBalance(Employee $employee, string $view): float
    {
        $metrics = $this->partyLedger->employeeFinancialMetric($employee);

        return match ($view) {
            'salary' => (float) ($metrics['paid_salary'] ?? 0),
            'advance' => (float) ($metrics['net_advance'] ?? 0),
            'loan' => (float) ($metrics['loan_balance'] ?? 0),
            default => $this->voucherTotal($employee, 'payment')
                - $this->voucherTotal($employee, 'receipt'),
        };
    }

    private function voucherTotal(
        Employee $employee,
        string $type,
        ?array $subTypeCodes = null
    ): float {
        return (float) DB::table('voucher_lines as vl')
            ->join('vouchers as v', 'v.id', '=', 'vl.voucher_id')
            ->join('journal_entries as je', 'je.id', '=', 'v.journal_entry_id')
            ->leftJoin(
                'voucher_transaction_types as pst',
                'pst.id',
                '=',
                'v.voucher_transaction_type_id'
            )
            ->where('vl.employee_id', $employee->id)
            ->where('v.voucher_type', $type)
            ->where('v.status', 'posted')
            ->where('je.status', 'posted')
            ->when($subTypeCodes, fn ($query) => $query->whereIn('pst.code', $subTypeCodes))
            ->sum('vl.amount');
    }

    private function formOptions(): array
    {
        return [
            'empTypes' => EmpType::query()->where('status', true)->get(['id', 'name']),
            'departments' => EmpDepartment::query()->where('status', true)->get(['id', 'name']),
            'designations' => EmpDesignation::query()->where('status', true)->get(['id', 'name']),
            'groups' => Group::query()->active()->get(['id', 'code', 'name']),
            ...$this->paymentAccounts->formOptions(),
            'employeeUploadLimits' => [
                'image_max_kb' => (int) config('erp.employee_uploads.image_max_kb', 5120),
                'nid_max_kb' => (int) config('erp.employee_uploads.nid_max_kb', 10240),
            ],
        ];
    }

    private function sorting(Request $request): array
    {
        $sortBy = in_array(
            $request->get('sort_by'),
            ['id', 'employee_code', 'employee_name', 'email', 'joining_date', 'status', 'created_at'],
            true
        ) ? $request->get('sort_by') : 'created_at';

        return [$sortBy, $request->get('sort_order') === 'asc' ? 'asc' : 'desc'];
    }

    private function deleteEmployee(Employee $employee): void
    {
        $files = $this->employeeProfiles->filePaths($employee);

        DB::transaction(function () use ($employee) {
            $employee->loadMissing('account');
            $this->assertEmployeeCanBeDeleted($employee);
            $account = $employee->account;
            $employee->delete();
            $account?->delete();
        });

        $this->employeeProfiles->deleteStoredFiles($files);
    }

    private function assertEmployeeCanBeDeleted(Employee $employee): void
    {
        if (
            $employee->journalLines()->exists()
            || $employee->shiftClosingProductItems()->exists()
        ) {
            throw ValidationException::withMessages([
                'employee' => 'This employee has financial or shift records and cannot be deleted.',
            ]);
        }
    }
}
