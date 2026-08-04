import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Pagination } from '@/components/ui/pagination';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import {
    Banknote,
    CalendarDays,
    Filter,
    LoaderCircle,
    Search,
    Users,
    X,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

interface NamedOption {
    id: number;
    name: string;
}

interface SalaryEmployee {
    id: number;
    employee_code: string;
    employee_name: string;
    department: NamedOption | null;
    designation: NamedOption | null;
    payment_method: string | null;
    payment_account: {
        id: number;
        name: string;
        ac_number: string;
    } | null;
    monthly_salary: number;
    pay_amount: number;
    remarks: string;
    payment_status: 'pending' | 'already_paid';
    can_select: boolean;
    configuration_error: string | null;
    payment_voucher: {
        id: number;
        voucher_no: string;
        date: string;
    } | null;
}

interface SalaryPaymentFilters {
    search: string;
    department_id: number | null;
    designation_id: number | null;
    salary_month: number;
    salary_year: number;
    date: string;
    shift_id: number | null;
    per_page: number;
}

interface SalaryPaymentPageProps {
    employees: {
        data: SalaryEmployee[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
    };
    departments: NamedOption[];
    designations: NamedOption[];
    shifts: NamedOption[];
    closedShiftIds: number[];
    filters: SalaryPaymentFilters;
}

interface FilterOverrides {
    search?: string;
    departmentId?: string;
    designationId?: string;
    salaryMonth?: string;
    salaryYear?: string;
    date?: string;
    shiftId?: string;
    perPage?: number;
    page?: number;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
    {
        title: 'Salary Payment',
        href: '/salary-payments',
    },
];

const months = Array.from({ length: 12 }, (_, index) => ({
    value: index + 1,
    label: new Intl.DateTimeFormat('en-US', { month: 'long' }).format(
        new Date(2026, index, 1),
    ),
}));

const formatAmount = (amount: number) =>
    new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(amount);

export default function SalaryPaymentIndex({
    employees,
    departments,
    designations,
    shifts,
    closedShiftIds,
    filters,
}: SalaryPaymentPageProps) {
    const [search, setSearch] = useState(filters.search);
    const [departmentId, setDepartmentId] = useState(
        filters.department_id?.toString() ?? 'all',
    );
    const [designationId, setDesignationId] = useState(
        filters.designation_id?.toString() ?? 'all',
    );
    const [salaryMonth, setSalaryMonth] = useState(
        filters.salary_month.toString(),
    );
    const [salaryYear, setSalaryYear] = useState(
        filters.salary_year.toString(),
    );
    const [date, setDate] = useState(filters.date);
    const [shiftId, setShiftId] = useState(
        filters.shift_id?.toString() ?? '',
    );
    const [perPage, setPerPage] = useState(filters.per_page);
    const [selectedEmployeeIds, setSelectedEmployeeIds] = useState<number[]>(
        [],
    );
    const [remarks, setRemarks] = useState<Record<number, string>>({});
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const availableShifts = useMemo(
        () => shifts.filter((shift) => !closedShiftIds.includes(shift.id)),
        [closedShiftIds, shifts],
    );
    const payableEmployeeIds = useMemo(
        () =>
            employees.data
                .filter((employee) => employee.can_select)
                .map((employee) => employee.id),
        [employees.data],
    );
    const allPayableSelected =
        payableEmployeeIds.length > 0 &&
        payableEmployeeIds.every((id) => selectedEmployeeIds.includes(id));
    const somePayableSelected = payableEmployeeIds.some((id) =>
        selectedEmployeeIds.includes(id),
    );
    const yearOptions = useMemo(() => {
        const currentYear = new Date().getFullYear();
        return Array.from(
            new Set([
                Number(salaryYear),
                ...Array.from({ length: 8 }, (_, index) => currentYear + 1 - index),
            ]),
        ).sort((first, second) => second - first);
    }, [salaryYear]);
    const firstError = Object.values(errors)[0];

    const visit = (overrides: FilterOverrides = {}) => {
        const nextSearch = overrides.search ?? search;
        const nextDepartment = overrides.departmentId ?? departmentId;
        const nextDesignation = overrides.designationId ?? designationId;
        const nextMonth = overrides.salaryMonth ?? salaryMonth;
        const nextYear = overrides.salaryYear ?? salaryYear;
        const nextDate = overrides.date ?? date;
        const nextShift = overrides.shiftId ?? shiftId;
        const nextPerPage = overrides.perPage ?? perPage;

        router.get(
            '/salary-payments',
            {
                search: nextSearch || undefined,
                department_id:
                    nextDepartment === 'all' ? undefined : nextDepartment,
                designation_id:
                    nextDesignation === 'all' ? undefined : nextDesignation,
                salary_month: nextMonth,
                salary_year: nextYear,
                date: nextDate,
                shift_id: nextShift || undefined,
                per_page: nextPerPage,
                page: overrides.page,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onBefore: () => {
                    setSelectedEmployeeIds([]);
                    setErrors({});
                },
            },
        );
    };

    useEffect(() => {
        if (search === filters.search) return;

        const timer = window.setTimeout(() => visit({ search, page: 1 }), 450);

        return () => window.clearTimeout(timer);
        // The remaining filter state is intentionally read when the debounce runs.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const toggleEmployee = (employeeId: number) => {
        setSelectedEmployeeIds((current) =>
            current.includes(employeeId)
                ? current.filter((id) => id !== employeeId)
                : [...current, employeeId],
        );
    };

    const toggleAll = () => {
        setSelectedEmployeeIds((current) =>
            allPayableSelected
                ? current.filter((id) => !payableEmployeeIds.includes(id))
                : Array.from(new Set([...current, ...payableEmployeeIds])),
        );
    };

    const clearFilters = () => {
        const currentMonth = (new Date().getMonth() + 1).toString();
        const currentYear = new Date().getFullYear().toString();

        setSearch('');
        setDepartmentId('all');
        setDesignationId('all');
        setSalaryMonth(currentMonth);
        setSalaryYear(currentYear);
        visit({
            search: '',
            departmentId: 'all',
            designationId: 'all',
            salaryMonth: currentMonth,
            salaryYear: currentYear,
            page: 1,
        });
    };

    const submitPayments = () => {
        setErrors({});
        setProcessing(true);

        router.post(
            '/salary-payments',
            {
                date,
                shift_id: shiftId || null,
                salary_month: Number(salaryMonth),
                salary_year: Number(salaryYear),
                employee_ids: selectedEmployeeIds,
                remarks: Object.fromEntries(
                    selectedEmployeeIds.map((employeeId) => {
                        const employee = employees.data.find(
                            (row) => row.id === employeeId,
                        );

                        return [
                            employeeId,
                            remarks[employeeId] ?? employee?.remarks ?? '',
                        ];
                    }),
                ),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSelectedEmployeeIds([]);
                    setErrors({});
                },
                onError: (responseErrors) =>
                    setErrors(responseErrors as Record<string, string>),
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Salary Payment" />

            <div className="space-y-6 p-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h1 className="text-3xl font-bold dark:text-white">
                            Salary Payment
                        </h1>
                        <p className="text-gray-600 dark:text-gray-400">
                            Process employee salaries through standard payment
                            vouchers
                        </p>
                    </div>
                    <Button
                        type="button"
                        onClick={submitPayments}
                        disabled={
                            processing ||
                            selectedEmployeeIds.length === 0 ||
                            !date ||
                            !shiftId
                        }
                    >
                        {processing ? (
                            <LoaderCircle
                                className="h-4 w-4 animate-spin"
                                aria-hidden="true"
                            />
                        ) : (
                            <Banknote className="h-4 w-4" aria-hidden="true" />
                        )}
                        Pay Selected ({selectedEmployeeIds.length})
                    </Button>
                </div>

                {firstError && (
                    <Alert variant="destructive">
                        <AlertTitle>Salary payment could not be processed</AlertTitle>
                        <AlertDescription>{firstError}</AlertDescription>
                    </Alert>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <CalendarDays className="h-5 w-5" aria-hidden="true" />
                            Payment Period
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            <div>
                                <Label htmlFor="salary-payment-date">Date</Label>
                                <Input
                                    id="salary-payment-date"
                                    type="date"
                                    value={date}
                                    error={Boolean(errors.date)}
                                    onChange={(event) => {
                                        const value = event.target.value;
                                        setDate(value);
                                        setShiftId('');
                                        visit({
                                            date: value,
                                            shiftId: '',
                                            page: 1,
                                        });
                                    }}
                                />
                            </div>
                            <div>
                                <Label htmlFor="salary-payment-shift">Shift</Label>
                                <Select
                                    value={shiftId}
                                    onValueChange={setShiftId}
                                    disabled={!date}
                                >
                                    <SelectTrigger
                                        id="salary-payment-shift"
                                        aria-invalid={Boolean(errors.shift_id)}
                                    >
                                        <SelectValue placeholder="Choose shift" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {availableShifts.map((shift) => (
                                            <SelectItem
                                                key={shift.id}
                                                value={shift.id.toString()}
                                            >
                                                {shift.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <Label>Salary Month</Label>
                                <Select
                                    value={salaryMonth}
                                    onValueChange={(value) => {
                                        setSalaryMonth(value);
                                        visit({
                                            salaryMonth: value,
                                            page: 1,
                                        });
                                    }}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {months.map((month) => (
                                            <SelectItem
                                                key={month.value}
                                                value={month.value.toString()}
                                            >
                                                {month.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <Label>Salary Year</Label>
                                <Select
                                    value={salaryYear}
                                    onValueChange={(value) => {
                                        setSalaryYear(value);
                                        visit({
                                            salaryYear: value,
                                            page: 1,
                                        });
                                    }}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {yearOptions.map((year) => (
                                            <SelectItem
                                                key={year}
                                                value={year.toString()}
                                            >
                                                {year}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Filter className="h-5 w-5" aria-hidden="true" />
                            Employee Filters
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                            <div>
                                <Label htmlFor="salary-employee-search">
                                    Search Employee
                                </Label>
                                <div className="relative">
                                    <Search
                                        className="pointer-events-none absolute top-1/2 left-2.5 h-4 w-4 -translate-y-1/2 text-gray-400"
                                        aria-hidden="true"
                                    />
                                    <Input
                                        id="salary-employee-search"
                                        value={search}
                                        onChange={(event) =>
                                            setSearch(event.target.value)
                                        }
                                        placeholder="Code or employee name"
                                        className="pl-8"
                                    />
                                </div>
                            </div>
                            <div>
                                <Label>Department</Label>
                                <Select
                                    value={departmentId}
                                    onValueChange={(value) => {
                                        setDepartmentId(value);
                                        visit({
                                            departmentId: value,
                                            page: 1,
                                        });
                                    }}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            All departments
                                        </SelectItem>
                                        {departments.map((department) => (
                                            <SelectItem
                                                key={department.id}
                                                value={department.id.toString()}
                                            >
                                                {department.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <Label>Designation</Label>
                                <Select
                                    value={designationId}
                                    onValueChange={(value) => {
                                        setDesignationId(value);
                                        visit({
                                            designationId: value,
                                            page: 1,
                                        });
                                    }}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            All designations
                                        </SelectItem>
                                        {designations.map((designation) => (
                                            <SelectItem
                                                key={designation.id}
                                                value={designation.id.toString()}
                                            >
                                                {designation.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="flex items-end">
                                <Button
                                    type="button"
                                    variant="secondary"
                                    onClick={clearFilters}
                                >
                                    <X className="h-4 w-4" aria-hidden="true" />
                                    Clear Filters
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[1320px]">
                                <thead>
                                    <tr className="border-b dark:border-gray-700">
                                        <th className="w-14 p-3 text-left">
                                            <Checkbox
                                                checked={
                                                    allPayableSelected
                                                        ? true
                                                        : somePayableSelected
                                                          ? 'indeterminate'
                                                          : false
                                                }
                                                onCheckedChange={toggleAll}
                                                disabled={
                                                    payableEmployeeIds.length === 0
                                                }
                                                aria-label="Select all payable employees on this page"
                                            />
                                        </th>
                                        {[
                                            'Employee Code',
                                            'Employee Name',
                                            'Department',
                                            'Designation',
                                            'Payment Method',
                                            'Account',
                                            'Monthly Salary',
                                            'Pay Amount',
                                            'Remarks',
                                            'Status',
                                        ].map((heading) => (
                                            <th
                                                key={heading}
                                                className="p-3 text-left text-[13px] font-medium whitespace-nowrap text-gray-700 dark:text-gray-300"
                                            >
                                                {heading}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {employees.data.length > 0 ? (
                                        employees.data.map((employee) => {
                                            const isSelected =
                                                selectedEmployeeIds.includes(
                                                    employee.id,
                                                );

                                            return (
                                                <tr
                                                    key={employee.id}
                                                    className="border-b align-top hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700/60"
                                                >
                                                    <td className="p-3">
                                                        <Checkbox
                                                            checked={isSelected}
                                                            onCheckedChange={() =>
                                                                toggleEmployee(
                                                                    employee.id,
                                                                )
                                                            }
                                                            disabled={
                                                                !employee.can_select
                                                            }
                                                            aria-label={`Select ${employee.employee_name}`}
                                                        />
                                                    </td>
                                                    <td className="p-3 text-[13px] whitespace-nowrap dark:text-gray-200">
                                                        {employee.employee_code}
                                                    </td>
                                                    <td className="p-3 text-[13px] font-medium whitespace-nowrap dark:text-white">
                                                        {employee.employee_name}
                                                    </td>
                                                    <td className="p-3 text-[13px] whitespace-nowrap dark:text-gray-300">
                                                        {employee.department
                                                            ?.name ?? 'N/A'}
                                                    </td>
                                                    <td className="p-3 text-[13px] whitespace-nowrap dark:text-gray-300">
                                                        {employee.designation
                                                            ?.name ?? 'N/A'}
                                                    </td>
                                                    <td className="p-3">
                                                        <Badge variant="secondary">
                                                            {employee.payment_method ??
                                                                'Not configured'}
                                                        </Badge>
                                                    </td>
                                                    <td className="p-3 text-[13px] dark:text-gray-300">
                                                        <div className="max-w-52">
                                                            <p>
                                                                {employee
                                                                    .payment_account
                                                                    ?.name ??
                                                                    'Not configured'}
                                                            </p>
                                                            {employee
                                                                .payment_account
                                                                ?.ac_number && (
                                                                <p className="text-xs text-gray-500 dark:text-gray-400">
                                                                    {
                                                                        employee
                                                                            .payment_account
                                                                            .ac_number
                                                                    }
                                                                </p>
                                                            )}
                                                        </div>
                                                    </td>
                                                    <td className="p-3 text-right text-[13px] whitespace-nowrap tabular-nums dark:text-gray-300">
                                                        {formatAmount(
                                                            employee.monthly_salary,
                                                        )}
                                                    </td>
                                                    <td className="p-3 text-right text-[13px] font-semibold whitespace-nowrap tabular-nums dark:text-white">
                                                        {formatAmount(
                                                            employee.pay_amount,
                                                        )}
                                                    </td>
                                                    <td className="w-80 p-3">
                                                        <Input
                                                            value={
                                                                remarks[
                                                                    employee.id
                                                                ] ??
                                                                employee.remarks
                                                            }
                                                            onChange={(event) =>
                                                                setRemarks(
                                                                    (current) => ({
                                                                        ...current,
                                                                        [employee.id]:
                                                                            event
                                                                                .target
                                                                                .value,
                                                                    }),
                                                                )
                                                            }
                                                            disabled={
                                                                !employee.can_select
                                                            }
                                                            aria-label={`Remarks for ${employee.employee_name}`}
                                                        />
                                                    </td>
                                                    <td className="p-3">
                                                        {employee.payment_status ===
                                                        'already_paid' ? (
                                                            <div className="space-y-1">
                                                                <Badge variant="success">
                                                                    Already Paid
                                                                </Badge>
                                                                {employee
                                                                    .payment_voucher
                                                                    ?.voucher_no && (
                                                                    <p className="text-xs whitespace-nowrap text-gray-500 dark:text-gray-400">
                                                                        {
                                                                            employee
                                                                                .payment_voucher
                                                                                .voucher_no
                                                                        }
                                                                    </p>
                                                                )}
                                                            </div>
                                                        ) : (
                                                            <div className="space-y-1">
                                                                <Badge variant="warning">
                                                                    Pending
                                                                </Badge>
                                                                {employee.configuration_error && (
                                                                    <p className="max-w-52 text-xs text-red-600 dark:text-red-400">
                                                                        {
                                                                            employee.configuration_error
                                                                        }
                                                                    </p>
                                                                )}
                                                            </div>
                                                        )}
                                                    </td>
                                                </tr>
                                            );
                                        })
                                    ) : (
                                        <tr>
                                            <td
                                                colSpan={11}
                                                className="p-10 text-center text-gray-500 dark:text-gray-400"
                                            >
                                                <Users
                                                    className="mx-auto mb-3 h-10 w-10"
                                                    aria-hidden="true"
                                                />
                                                No employees found.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <Pagination
                            currentPage={employees.current_page}
                            lastPage={employees.last_page}
                            from={employees.from}
                            to={employees.to}
                            total={employees.total}
                            perPage={perPage}
                            onPageChange={(page) => visit({ page })}
                            onPerPageChange={(value) => {
                                setPerPage(value);
                                visit({ perPage: value, page: 1 });
                            }}
                        />
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
