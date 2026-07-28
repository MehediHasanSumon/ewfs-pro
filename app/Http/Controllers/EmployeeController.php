<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\EmpDepartment;
use App\Models\EmpDesignation;
use App\Models\Employee;
use App\Models\EmpType;
use App\Models\Group;
use App\Services\DocumentNumberService;
use App\Services\PartyAccountService;
use App\Services\PartyLedgerService;
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
    private const SALARY_CODES = ['1001', '1004', '1005', '1006', '1007', '1014'];
    private const ADVANCE_CODES = ['1002', '1003'];
    private const ADVANCE_RETURN_CODES = ['1002', '1003', '1008'];

    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly PartyAccountService $partyAccounts,
        private readonly PartyLedgerService $partyLedger
    ) {
    }

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

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());

        DB::transaction(function () use ($validated, $request) {
            $status = $request->boolean('status', true);
            $account = $this->partyAccounts->createEmployeeAccount(
                $validated['employee_name'],
                $status
            );

            Employee::query()->create([
                ...$this->employeePayload($validated, $status),
                'account_id' => $account->id,
                'employee_code' => $validated['employee_code']
                    ?? $this->numbers->next('employee', 'EMP', null, 4),
            ]);
        });

        return redirect()->route('employees.index')->with('success', 'Employee created successfully.');
    }

    public function show(Employee $employee)
    {
        $employee->load('account', 'empType', 'department', 'designation');

        $salaryQuery = $this->employeeVouchers($employee, 'payment', self::SALARY_CODES);
        $advanceQuery = $this->employeeVouchers($employee, 'payment', self::ADVANCE_CODES);
        $returnQuery = $this->employeeVouchers($employee, 'receipt', self::ADVANCE_RETURN_CODES);

        $salaryPaymentCount = (clone $salaryQuery)->count();
        $advancedCount = (clone $advanceQuery)->count();
        $advancedReturnCount = (clone $returnQuery)->count();
        $totalPaidSalary = $this->voucherTotal($employee, 'payment', self::SALARY_CODES);
        $totalAdvanced = $this->voucherTotal($employee, 'payment', self::ADVANCE_CODES);
        $totalAdvancedReturns = $this->voucherTotal(
            $employee,
            'receipt',
            self::ADVANCE_RETURN_CODES
        );

        $recentSalaryPayments = $this->partyLedger->voucherRows(
            $salaryQuery->orderByDesc('voucher_date')->orderByDesc('id')->limit(5)->get(),
            'Paid'
        );
        $recentAdvancedPayments = $this->partyLedger->voucherRows(
            $advanceQuery->orderByDesc('voucher_date')->orderByDesc('id')->limit(5)->get(),
            'Given'
        );

        $monthsWorked = $this->monthsWorked($employee);
        $netAdvanced = $totalAdvanced - $totalAdvancedReturns;
        $salaryDue = max(0, (float) ($employee->salary ?? 0) * $monthsWorked - $totalPaidSalary);

        return Inertia::render('Employee/Show', [
            'employee' => $employee,
            'recentSalaryPayments' => $recentSalaryPayments,
            'recentAdvancedPayments' => $recentAdvancedPayments,
            'totalPaidSalary' => $totalPaidSalary,
            'salaryPaymentCount' => $salaryPaymentCount,
            'totalAdvanced' => $totalAdvanced,
            'advancedCount' => $advancedCount,
            'totalAdvancedReturns' => $totalAdvancedReturns,
            'advancedReturnCount' => $advancedReturnCount,
            'netAdvanced' => $netAdvanced,
            'salaryDue' => $salaryDue,
            'netBalance' => $salaryDue - $netAdvanced,
            'monthsWorked' => $monthsWorked,
        ]);
    }

    public function edit(Employee $employee)
    {
        $employee->load('account', 'empType', 'department', 'designation');

        return Inertia::render('Employee/Update', [
            'employee' => $employee,
            ...$this->formOptions(),
        ]);
    }

    public function update(Request $request, Employee $employee)
    {
        $validated = $request->validate($this->rules());

        DB::transaction(function () use ($validated, $request, $employee) {
            $status = $request->boolean('status', true);
            $employee->loadMissing('account');
            $employee->account?->update([
                'name' => $validated['employee_name'],
                'status' => $status,
            ]);
            $employee->update([
                ...$this->employeePayload($validated, $status),
                'employee_code' => $validated['employee_code'] ?? $employee->employee_code,
            ]);
        });

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

        return redirect()->back()->with('success', 'Selected employees deleted successfully.');
    }

    public function statement(Request $request, Employee $employee)
    {
        $employee->load('account:id,name,ac_number');
        $payments = $this->partyLedger->paginatedVoucherRows(
            $this->employeeVouchers(
                $employee,
                'payment',
                null,
                $request->start_date,
                $request->end_date
            ),
            10,
            'Paid'
        );
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
            'currentBalance' => $this->voucherTotal($employee, 'payment')
                - $this->voucherTotal($employee, 'receipt'),
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
            ->when($subTypeCodes, fn (Builder $query) => $query
                ->whereHas('paymentSubType', fn (Builder $subType) => $subType
                    ->whereIn('code', $subTypeCodes)));
    }

    private function voucherTotal(
        Employee $employee,
        string $type,
        ?array $subTypeCodes = null
    ): float {
        return (float) DB::table('voucher_lines as vl')
            ->join('vouchers as v', 'v.id', '=', 'vl.voucher_id')
            ->leftJoin('payment_sub_types as pst', 'pst.id', '=', 'v.payment_sub_type_id')
            ->where('vl.employee_id', $employee->id)
            ->where('v.voucher_type', $type)
            ->where('v.status', 'posted')
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
        ];
    }

    private function rules(): array
    {
        return [
            'employee_code' => ['nullable', 'string', 'max:50'],
            'employee_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:150'],
            'emp_type_id' => ['nullable', 'exists:emp_types,id'],
            'department_id' => ['nullable', 'exists:emp_departments,id'],
            'designation_id' => ['nullable', 'exists:emp_designations,id'],
            'mobile' => ['nullable', 'string', 'max:100'],
            'mobile_two' => ['nullable', 'string', 'max:20'],
            'dob' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'max:10'],
            'blood_group' => ['nullable', 'string', 'max:10'],
            'marital_status' => ['nullable', 'string', 'max:20'],
            'religion' => ['nullable', 'string', 'max:100'],
            'nid' => ['nullable', 'string', 'max:100'],
            'emergency_contact_person' => ['nullable', 'string', 'max:100'],
            'emergency_contact_number' => ['nullable', 'string', 'max:100'],
            'father_name' => ['nullable', 'string', 'max:100'],
            'mother_name' => ['nullable', 'string', 'max:100'],
            'present_address' => ['nullable', 'string', 'max:250'],
            'permanent_address' => ['nullable', 'string', 'max:350'],
            'job_status' => ['nullable', 'string', 'max:50'],
            'salary' => ['nullable', 'numeric', 'min:0'],
            'joining_date' => ['nullable', 'date'],
            'order' => ['nullable', 'integer'],
            'highest_education' => ['nullable', 'string', 'max:100'],
            'status' => ['boolean'],
        ];
    }

    private function employeePayload(array $validated, bool $status): array
    {
        return [
            'emp_type_id' => $validated['emp_type_id'] ?? null,
            'department_id' => $validated['department_id'] ?? null,
            'designation_id' => $validated['designation_id'] ?? null,
            'employee_name' => $validated['employee_name'],
            'email' => $validated['email'] ?? null,
            'order' => $validated['order'] ?? 1,
            'dob' => $validated['dob'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'blood_group' => $validated['blood_group'] ?? null,
            'marital_status' => $validated['marital_status'] ?? null,
            'emergency_contact_person' => $validated['emergency_contact_person'] ?? null,
            'religion' => $validated['religion'] ?? null,
            'nid' => $validated['nid'] ?? null,
            'mobile' => $validated['mobile'] ?? null,
            'mobile_two' => $validated['mobile_two'] ?? null,
            'emergency_contact_number' => $validated['emergency_contact_number'] ?? null,
            'father_name' => $validated['father_name'] ?? null,
            'mother_name' => $validated['mother_name'] ?? null,
            'present_address' => $validated['present_address'] ?? null,
            'permanent_address' => $validated['permanent_address'] ?? null,
            'job_status' => $validated['job_status'] ?? null,
            'salary' => $validated['salary'] ?? null,
            'joining_date' => $validated['joining_date'] ?? null,
            'highest_education' => $validated['highest_education'] ?? null,
            'status' => $status,
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

    private function monthsWorked(Employee $employee): int
    {
        $startedAt = $employee->joining_date ?? $employee->created_at;

        return max(0, (int) $startedAt->diffInMonths(now()));
    }

    private function deleteEmployee(Employee $employee): void
    {
        DB::transaction(function () use ($employee) {
            $employee->loadMissing('account');
            $this->assertEmployeeCanBeDeleted($employee);
            $account = $employee->account;
            $employee->delete();
            $account?->delete();
        });
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
