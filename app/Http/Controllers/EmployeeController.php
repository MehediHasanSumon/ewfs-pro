<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Account;
use App\Models\Group;
use App\Models\EmpType;
use App\Models\EmpDepartment;
use App\Models\EmpDesignation;
use App\Models\CompanySetting;
use App\Helpers\AccountHelper;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;

class EmployeeController extends Controller implements HasMiddleware
{
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
        $query = Employee::with('account:id,name,ac_number', 'empType:id,name', 'department:id,name', 'designation:id,name');

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('employee_name', 'like', '%' . $request->search . '%')
                    ->orWhere('employee_code', 'like', '%' . $request->search . '%')
                    ->orWhere('email', 'like', '%' . $request->search . '%')
                    ->orWhere('mobile', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->status && $request->status !== 'all') {
            $query->where('status', $request->status === 'active');
        }

        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 10);
        $employees = $query->paginate($perPage)->withQueryString()->through(function ($employee) {
            return [
                'id' => $employee->id,
                'employee_code' => $employee->employee_code,
                'employee_name' => $employee->employee_name,
                'email' => $employee->email,
                'mobile' => $employee->mobile,
                'joining_date' => $employee->joining_date,
                'job_status' => $employee->job_status,
                'status' => $employee->status,
                'emp_type' => $employee->empType,
                'department' => $employee->department,
                'designation' => $employee->designation,
                'account' => $employee->account,
                'created_at' => $employee->created_at->format('Y-m-d'),
            ];
        });

        $empTypes = EmpType::where('status', true)->get(['id', 'name']);
        $departments = EmpDepartment::where('status', true)->get(['id', 'name']);
        $designations = EmpDesignation::where('status', true)->get(['id', 'name']);
        $groups = Group::where('status', true)->get(['id', 'code', 'name']);

        return Inertia::render('Employee/Index', [
            'employees' => $employees,
            'empTypes' => $empTypes,
            'departments' => $departments,
            'designations' => $designations,
            'groups' => $groups,
            'filters' => $request->only(['search', 'status', 'sort_by', 'sort_order', 'per_page'])
        ]);
    }

    public function create()
    {
        $empTypes = EmpType::where('status', true)->get(['id', 'name']);
        $departments = EmpDepartment::where('status', true)->get(['id', 'name']);
        $designations = EmpDesignation::where('status', true)->get(['id', 'name']);
        $groups = Group::where('status', true)->get(['id', 'code', 'name']);


        $lastEmployeeGroup = null;
        $lastEmployee = Employee::with('account.group')->latest()->first();
        if ($lastEmployee && $lastEmployee->account && $lastEmployee->account->group) {
            $lastEmployeeGroup = [
                'id' => $lastEmployee->account->group->id,
                'code' => $lastEmployee->account->group->code
            ];
        }

        return Inertia::render('Employee/Create', [
            'empTypes' => $empTypes,
            'departments' => $departments,
            'designations' => $designations,
            'groups' => $groups,
            'lastEmployeeGroup' => $lastEmployeeGroup,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'employee_code' => 'nullable|string|max:50',
            'employee_name' => 'required|string|max:100',
            'email' => 'nullable|email|max:100',
            'emp_type_id' => 'nullable|exists:emp_types,id',
            'department_id' => 'nullable|exists:emp_departments,id',
            'designation_id' => 'nullable|exists:emp_designations,id',

            'mobile' => 'nullable|string|max:100',
            'mobile_two' => 'nullable|string|max:20',
            'dob' => 'nullable|date',
            'gender' => 'nullable|string|max:10',
            'blood_group' => 'nullable|string|max:10',
            'marital_status' => 'nullable|string|max:20',
            'religion' => 'nullable|string|max:100',
            'nid' => 'nullable|string|max:100',
            'emergency_contact_person' => 'nullable|string|max:100',
            'emergency_contact_number' => 'nullable|string|max:100',
            'father_name' => 'nullable|string|max:100',
            'mother_name' => 'nullable|string|max:100',
            'present_address' => 'nullable|string|max:250',
            'permanent_address' => 'nullable|string|max:350',
            'job_status' => 'nullable|string|max:50',
            'salary' => 'nullable|numeric|min:0',
            'joining_date' => 'nullable|date',
            'order' => 'nullable|integer',
            'status' => 'boolean'
        ]);

        // Create account first
        $account = Account::create([
            'name' => $request->employee_name,
            'ac_number' => AccountHelper::generateAccountNumber(),
            'group_id' => 16,
            'group_code' => '40002',
            'status' => $request->status ?? true,
        ]);

        Employee::create([
            'account_id' => $account->id,
            'emp_type_id' => $request->emp_type_id,
            'department_id' => $request->department_id,
            'designation_id' => $request->designation_id,
            'employee_code' => $request->employee_code,
            'employee_name' => $request->employee_name,
            'email' => $request->email,
            'order' => $request->order ?? 1,
            'dob' => $request->dob,
            'gender' => $request->gender,
            'blood_group' => $request->blood_group,
            'marital_status' => $request->marital_status,
            'emergency_contact_person' => $request->emergency_contact_person,
            'religion' => $request->religion,
            'nid' => $request->nid,
            'mobile' => $request->mobile,
            'mobile_two' => $request->mobile_two,
            'emergency_contact_number' => $request->emergency_contact_number,
            'father_name' => $request->father_name,
            'mother_name' => $request->mother_name,
            'present_address' => $request->present_address,
            'permanent_address' => $request->permanent_address,
            'job_status' => $request->job_status,
            'salary' => $request->salary,
            'joining_date' => $request->joining_date,
            'status' => $request->status ?? true,
        ]);

        return redirect()->route('employees.index')->with('success', 'Employee created successfully.');
    }

    public function show(Employee $employee)
    {
        $employee->load('account', 'empType', 'department', 'designation');

        // Get recent salary payments from vouchers using payment_sub_type_id codes
        $recentSalaryPayments = DB::table('vouchers')
            ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->join('payment_sub_types', 'vouchers.payment_sub_type_id', '=', 'payment_sub_types.id')
            ->where('vouchers.to_account_id', $employee->account_id)
            ->where('vouchers.voucher_type', 'Payment')
            ->whereIn('payment_sub_types.code', ['1001', '1004', '1005', '1006', '1007', '1014']) // Monthly Salary, Bonus, Overtime, Medical, Travel, Salary & Allowances
            ->select(
                'vouchers.id',
                'vouchers.voucher_no',
                'vouchers.date',
                'transactions.amount',
                'transactions.payment_type as type',
                'payment_sub_types.name as sub_type',
                'vouchers.description',
                DB::raw("'Paid' as status")
            )
            ->orderBy('vouchers.date', 'desc')
            ->limit(5)
            ->get();
        
        // Get recent advanced payments from vouchers using payment_sub_type_id codes
        $recentAdvancedPayments = DB::table('vouchers')
            ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->join('payment_sub_types', 'vouchers.payment_sub_type_id', '=', 'payment_sub_types.id')
            ->where('vouchers.to_account_id', $employee->account_id)
            ->where('vouchers.voucher_type', 'Payment')
            ->whereIn('payment_sub_types.code', ['1002', '1003']) // Salary Advance, Personal Loan
            ->select(
                'vouchers.id',
                'vouchers.voucher_no',
                'vouchers.date',
                'transactions.amount',
                'transactions.payment_type as type',
                'payment_sub_types.name as sub_type',
                'vouchers.description',
                DB::raw("'Given' as status")
            )
            ->orderBy('vouchers.date', 'desc')
            ->limit(5)
            ->get();
        
        // Calculate totals using payment_sub_type_id codes
        $totalPaidSalary = DB::table('vouchers')
            ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->join('payment_sub_types', 'vouchers.payment_sub_type_id', '=', 'payment_sub_types.id')
            ->where('vouchers.to_account_id', $employee->account_id)
            ->where('vouchers.voucher_type', 'Payment')
            ->whereIn('payment_sub_types.code', ['1001', '1004', '1005', '1006', '1007', '1014'])
            ->sum('transactions.amount');
            
        $salaryPaymentCount = DB::table('vouchers')
            ->join('payment_sub_types', 'vouchers.payment_sub_type_id', '=', 'payment_sub_types.id')
            ->where('vouchers.to_account_id', $employee->account_id)
            ->where('vouchers.voucher_type', 'Payment')
            ->whereIn('payment_sub_types.code', ['1001', '1004', '1005', '1006', '1007', '1014'])
            ->count();
            
        $totalAdvanced = DB::table('vouchers')
            ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->join('payment_sub_types', 'vouchers.payment_sub_type_id', '=', 'payment_sub_types.id')
            ->where('vouchers.to_account_id', $employee->account_id)
            ->where('vouchers.voucher_type', 'Payment')
            ->whereIn('payment_sub_types.code', ['1002', '1003'])
            ->sum('transactions.amount');
            
        $advancedCount = DB::table('vouchers')
            ->join('payment_sub_types', 'vouchers.payment_sub_type_id', '=', 'payment_sub_types.id')
            ->where('vouchers.to_account_id', $employee->account_id)
            ->where('vouchers.voucher_type', 'Payment')
            ->whereIn('payment_sub_types.code', ['1002', '1003'])
            ->count();
            
        // Calculate advanced returns (receipts from employee)
        $totalAdvancedReturns = DB::table('vouchers')
            ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->join('payment_sub_types', 'vouchers.payment_sub_type_id', '=', 'payment_sub_types.id')
            ->where('vouchers.from_account_id', $employee->account_id)
            ->where('vouchers.voucher_type', 'Receipt')
            ->whereIn('payment_sub_types.code', ['1002', '1003', '1008']) // Salary Advance, Personal Loan, Advance Return
            ->sum('transactions.amount');
            
        $advancedReturnCount = DB::table('vouchers')
            ->join('payment_sub_types', 'vouchers.payment_sub_type_id', '=', 'payment_sub_types.id')
            ->where('vouchers.from_account_id', $employee->account_id)
            ->where('vouchers.voucher_type', 'Receipt')
            ->whereIn('payment_sub_types.code', ['1002', '1003', '1008']) // Salary Advance, Personal Loan, Advance Return
            ->count();
            
        $netAdvanced = $totalAdvanced - $totalAdvancedReturns;
        
        // Calculate months since joining
        $monthsWorked = 0;
        if ($employee->created_at) {
            $createdDate = new \DateTime($employee->created_at);
            $currentDate = new \DateTime();
            $interval = $createdDate->diff($currentDate);
            $monthsWorked = ($interval->y * 12) + $interval->m;
        }
        
        $expectedSalary = ($employee->salary ?? 0) * $monthsWorked;
        $salaryDue = $expectedSalary - $totalPaidSalary;
        $netBalance = $salaryDue - $netAdvanced;

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
            'netBalance' => $netBalance,
            'monthsWorked' => $monthsWorked
        ]);
    }

    public function edit(Employee $employee)
    {
        $employee->load('account', 'empType', 'department', 'designation');

        $empTypes = EmpType::where('status', true)->get(['id', 'name']);
        $departments = EmpDepartment::where('status', true)->get(['id', 'name']);
        $designations = EmpDesignation::where('status', true)->get(['id', 'name']);
        $groups = Group::where('status', true)->get(['id', 'code', 'name']);

        return Inertia::render('Employee/Update', [
            'employee' => $employee,
            'empTypes' => $empTypes,
            'departments' => $departments,
            'designations' => $designations,
            'groups' => $groups,
        ]);
    }

    public function update(Request $request, Employee $employee)
    {
        $request->validate([
            'employee_code' => 'nullable|string|max:50',
            'employee_name' => 'required|string|max:100',
            'email' => 'nullable|email|max:100',
            'emp_type_id' => 'nullable|exists:emp_types,id',
            'department_id' => 'nullable|exists:emp_departments,id',
            'designation_id' => 'nullable|exists:emp_designations,id',

            'mobile' => 'nullable|string|max:100',
            'mobile_two' => 'nullable|string|max:20',
            'dob' => 'nullable|date',
            'gender' => 'nullable|string|max:10',
            'blood_group' => 'nullable|string|max:10',
            'marital_status' => 'nullable|string|max:20',
            'religion' => 'nullable|string|max:100',
            'nid' => 'nullable|string|max:100',
            'emergency_contact_person' => 'nullable|string|max:100',
            'emergency_contact_number' => 'nullable|string|max:100',
            'father_name' => 'nullable|string|max:100',
            'mother_name' => 'nullable|string|max:100',
            'present_address' => 'nullable|string|max:250',
            'permanent_address' => 'nullable|string|max:350',
            'job_status' => 'nullable|string|max:50',
            'salary' => 'nullable|numeric|min:0',
            'joining_date' => 'nullable|date',
            'order' => 'nullable|integer',
            'status' => 'boolean'
        ]);

        // Update account
        if ($employee->account) {
            $employee->account->update([
                'name' => $request->employee_name,
                'status' => $request->status ?? true,
            ]);
        }

        $updateData = [
            'emp_type_id' => $request->emp_type_id,
            'department_id' => $request->department_id,
            'designation_id' => $request->designation_id,
            'employee_code' => $request->employee_code,
            'employee_name' => $request->employee_name,
            'email' => $request->email,
            'order' => $request->order ?? 1,
            'dob' => $request->dob,
            'gender' => $request->gender,
            'blood_group' => $request->blood_group,
            'marital_status' => $request->marital_status,
            'emergency_contact_person' => $request->emergency_contact_person,
            'religion' => $request->religion,
            'nid' => $request->nid,
            'mobile' => $request->mobile,
            'mobile_two' => $request->mobile_two,
            'emergency_contact_number' => $request->emergency_contact_number,
            'father_name' => $request->father_name,
            'mother_name' => $request->mother_name,
            'present_address' => $request->present_address,
            'permanent_address' => $request->permanent_address,
            'job_status' => $request->job_status,
            'salary' => $request->salary,
            'joining_date' => $request->joining_date,
            'status' => $request->status ?? true,
        ];

        $employee->update($updateData);

        return redirect()->route('employees.index')->with('success', 'Employee updated successfully.');
    }

    public function destroy(Employee $employee)
    {
        $employee->delete();
        return redirect()->back()->with('success', 'Employee deleted successfully.');
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:employees,id'
        ]);

        Employee::whereIn('id', $request->ids)->delete();

        return redirect()->back()->with('success', 'Selected employees deleted successfully.');
    }

    public function statement(Request $request, Employee $employee)
    {
        $employee->load('account:id,name,ac_number');

        // Get all payments TO employee (company pays employee)
        $payments = [];
        if ($employee->account) {
            $query = DB::table('vouchers')
                ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
                ->join('payment_sub_types', 'vouchers.payment_sub_type_id', '=', 'payment_sub_types.id')
                ->where('vouchers.to_account_id', $employee->account->id)
                ->where('vouchers.voucher_type', 'Payment')
                ->select('vouchers.*', 'transactions.amount', 'transactions.payment_type', 'payment_sub_types.name as sub_type_name');
            
            if ($request->start_date) {
                $query->whereDate('vouchers.date', '>=', $request->start_date);
            }
            if ($request->end_date) {
                $query->whereDate('vouchers.date', '<=', $request->end_date);
            }
            
            $payments = $query->orderBy('vouchers.date', 'desc')
                ->paginate(10)
                ->withQueryString()
                ->through(function ($voucher) {
                    return [
                        'id' => $voucher->id,
                        'voucher_no' => $voucher->voucher_no,
                        'date' => $voucher->date,
                        'amount' => $voucher->amount,
                        'payment_type' => $voucher->payment_type,
                        'sub_type' => $voucher->sub_type_name,
                        'description' => $voucher->description,
                    ];
                });
        }

        // Get all receipts FROM employee (employee pays company)
        $receipts = [];
        if ($employee->account) {
            $query = DB::table('vouchers')
                ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
                ->join('payment_sub_types', 'vouchers.payment_sub_type_id', '=', 'payment_sub_types.id')
                ->where('vouchers.from_account_id', $employee->account->id)
                ->where('vouchers.voucher_type', 'Receipt')
                ->select('vouchers.*', 'transactions.amount', 'transactions.payment_type', 'payment_sub_types.name as sub_type_name');
            
            if ($request->start_date) {
                $query->whereDate('vouchers.date', '>=', $request->start_date);
            }
            if ($request->end_date) {
                $query->whereDate('vouchers.date', '<=', $request->end_date);
            }
            
            $receipts = $query->orderBy('vouchers.date', 'desc')
                ->get()
                ->map(function ($voucher) {
                    return [
                        'id' => $voucher->id,
                        'voucher_no' => $voucher->voucher_no,
                        'date' => $voucher->date,
                        'amount' => $voucher->amount,
                        'payment_type' => $voucher->payment_type,
                        'sub_type' => $voucher->sub_type_name,
                        'description' => $voucher->description,
                    ];
                });
        }

        // Calculate current balance
        $totalPayments = DB::table('vouchers')
            ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->where('vouchers.to_account_id', $employee->account_id)
            ->where('vouchers.voucher_type', 'Payment')
            ->sum('transactions.amount');
            
        $totalReceipts = DB::table('vouchers')
            ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->where('vouchers.from_account_id', $employee->account_id)
            ->where('vouchers.voucher_type', 'Receipt')
            ->sum('transactions.amount');
            
        $currentBalance = $totalPayments - $totalReceipts;

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
            'currentBalance' => $currentBalance,
        ]);
    }

    public function downloadPaymentsPdf(Request $request, Employee $employee)
    {
        $employee->load('account');
        
        $query = DB::table('vouchers')
            ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->join('payment_sub_types', 'vouchers.payment_sub_type_id', '=', 'payment_sub_types.id')
            ->where('vouchers.to_account_id', $employee->account->id)
            ->where('vouchers.voucher_type', 'Payment')
            ->select('vouchers.*', 'transactions.amount', 'transactions.payment_type', 'payment_sub_types.name as sub_type_name');
        
        if ($request->start_date) {
            $query->whereDate('vouchers.date', '>=', $request->start_date);
        }
        if ($request->end_date) {
            $query->whereDate('vouchers.date', '<=', $request->end_date);
        }
        
        $payments = $query->orderBy('vouchers.date', 'desc')
            ->get()
            ->map(function ($voucher) {
                return [
                    'voucher_no' => $voucher->voucher_no,
                    'date' => $voucher->date,
                    'amount' => $voucher->amount,
                    'payment_type' => $voucher->payment_type,
                    'sub_type' => $voucher->sub_type_name,
                    'description' => $voucher->description,
                ];
            });

        $companySetting = CompanySetting::first();

        $pdf = Pdf::loadView('pdf.employee-payments', compact('employee', 'payments', 'companySetting'));
        return $pdf->stream('employee-payments.pdf');
    }

    public function downloadReceiptsPdf(Request $request, Employee $employee)
    {
        $employee->load('account');
        
        $query = DB::table('vouchers')
            ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->join('payment_sub_types', 'vouchers.payment_sub_type_id', '=', 'payment_sub_types.id')
            ->where('vouchers.from_account_id', $employee->account->id)
            ->where('vouchers.voucher_type', 'Receipt')
            ->select('vouchers.*', 'transactions.amount', 'transactions.payment_type', 'payment_sub_types.name as sub_type_name');
        
        if ($request->start_date) {
            $query->whereDate('vouchers.date', '>=', $request->start_date);
        }
        if ($request->end_date) {
            $query->whereDate('vouchers.date', '<=', $request->end_date);
        }
        
        $receipts = $query->orderBy('vouchers.date', 'desc')
            ->get()
            ->map(function ($voucher) {
                return [
                    'voucher_no' => $voucher->voucher_no,
                    'date' => $voucher->date,
                    'amount' => $voucher->amount,
                    'payment_type' => $voucher->payment_type,
                    'sub_type' => $voucher->sub_type_name,
                    'description' => $voucher->description,
                ];
            });

        $companySetting = CompanySetting::first();

        $pdf = Pdf::loadView('pdf.employee-receipts', compact('employee', 'receipts', 'companySetting'));
        return $pdf->stream('employee-receipts.pdf');
    }

    public function downloadPdf()
    {
        $employees = Employee::with('empType', 'department', 'designation')->get();
        $companySetting = CompanySetting::first();

        $pdf = Pdf::loadView('pdf.employees', compact('employees', 'companySetting'));
        return $pdf->stream('employees.pdf');
    }
}
