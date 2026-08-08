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
import { Head, router, useForm } from '@inertiajs/react';
import { Banknote, CalendarDays, Filter, Users, X } from 'lucide-react';
import { useMemo, useState } from 'react';

interface Payroll {
    id: number;
    payroll_code: string | null;
    label: string;
    status: string;
    items_count: number;
    pending_items_count: number;
}

interface Item {
    id: number;
    employee_id: number;
    employee_name: string;
    employee_code: string | null;
    department: string | null;
    monthly_salary: number;
    total_deduction: number;
    advance_applied: number;
    total_bonus: number;
    net_payable: number;
    payment_method: string | null;
    payment_account: { name: string; ac_number: string } | null;
    status: string;
}

interface ItemPaginator {
    data: Item[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
}

interface Props {
    payrolls: Payroll[];
    payroll: { id: number; label: string; status: string } | null;
    items: ItemPaginator;
    departments: string[];
    filters: {
        payroll_id: number | null;
        date: string;
        search?: string;
        department?: string;
        status?: string;
        per_page?: number;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Salary Payment', href: '/salary-payments' },
];

const amount = (value: number) =>
    Number(value ?? 0).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

export default function SalaryPaymentIndex({
    payrolls,
    payroll,
    items,
    departments = [],
    filters,
}: Props) {
    const form = useForm({
        payroll_id: filters.payroll_id ?? 0,
        date: filters.date,
        employee_ids: [] as number[],
    });
    const [search, setSearch] = useState(filters.search ?? '');
    const [department, setDepartment] = useState(
        filters.department ?? 'all',
    );
    const [status, setStatus] = useState(filters.status ?? 'all');
    const [perPage, setPerPage] = useState(
        filters.per_page ?? items.per_page ?? 10,
    );
    const pendingIds = useMemo(
        () =>
            items.data
                .filter((item) => item.status === 'pending')
                .map((item) => item.employee_id),
        [items.data],
    );
    const allSelected =
        pendingIds.length > 0 &&
        pendingIds.every((id) => form.data.employee_ids.includes(id));

    const query = (
        page?: number,
        overrides: Record<string, string | number | undefined> = {},
    ) => ({
        payroll_id:
            overrides.payroll_id ?? (form.data.payroll_id || undefined),
        search: overrides.search ?? (search || undefined),
        department:
            overrides.department ??
            (department === 'all' ? undefined : department),
        status:
            overrides.status ?? (status === 'all' ? undefined : status),
        per_page: overrides.per_page ?? perPage,
        date: form.data.date,
        page,
    });

    const selectPayroll = (value: string) => {
        const payrollId = Number(value);

        form.setData('payroll_id', payrollId);
        form.setData('employee_ids', []);
        router.get('/salary-payments', query(1, { payroll_id: payrollId }), {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const applyFilters = () => {
        router.get('/salary-payments', query(1), {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const clearFilters = () => {
        setSearch('');
        setDepartment('all');
        setStatus('all');
        router.get(
            '/salary-payments',
            query(1, {
                search: '',
                department: 'all',
                status: 'all',
            }),
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    const submit = () => {
        form.post('/salary-payments', {
            preserveScroll: true,
            onSuccess: () => form.setData('employee_ids', []),
        });
    };

    const toggleAllPending = () => {
        form.setData(
            'employee_ids',
            allSelected
                ? form.data.employee_ids.filter(
                      (id) => !pendingIds.includes(id),
                  )
                : Array.from(
                      new Set([...form.data.employee_ids, ...pendingIds]),
                  ),
        );
    };

    const toggleEmployee = (employeeId: number) => {
        const selected = form.data.employee_ids.includes(employeeId);

        form.setData(
            'employee_ids',
            selected
                ? form.data.employee_ids.filter((id) => id !== employeeId)
                : [...form.data.employee_ids, employeeId],
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Salary Payment" />

            <div className="space-y-6 p-6">
                <div>
                    <h1 className="text-3xl font-bold dark:text-white">
                        Salary Payment
                    </h1>
                    <p className="text-gray-600 dark:text-gray-400">
                        Pay generated payroll employees through the Payment
                        Voucher workflow
                    </p>
                </div>

                <Card className="dark:border-gray-700 dark:bg-gray-800">
                    <CardHeader className="mb-4">
                        <CardTitle className="flex items-center gap-2 dark:text-white">
                            <CalendarDays className="h-5 w-5" />
                            Payroll Payment
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 items-end gap-4 md:grid-cols-[minmax(0,1fr)_220px_auto]">
                            <div className="space-y-1.5">
                                <Label
                                    htmlFor="payroll-selector"
                                    className="dark:text-gray-200"
                                >
                                    Generated Payroll
                                </Label>
                                <Select
                                    value={
                                        form.data.payroll_id
                                            ? form.data.payroll_id.toString()
                                            : ''
                                    }
                                    onValueChange={selectPayroll}
                                    disabled={payrolls.length === 0}
                                >
                                    <SelectTrigger
                                        id="payroll-selector"
                                        className="w-full dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    >
                                        <SelectValue placeholder="Choose generated payroll" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {payrolls.map((row) => (
                                            <SelectItem
                                                key={row.id}
                                                value={row.id.toString()}
                                            >
                                                {row.label} -{' '}
                                                {row.pending_items_count}{' '}
                                                pending
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-1.5">
                                <Label
                                    htmlFor="salary-payment-date"
                                    className="dark:text-gray-200"
                                >
                                    Payment Date
                                </Label>
                                <Input
                                    id="salary-payment-date"
                                    type="date"
                                    value={form.data.date}
                                    onChange={(event) =>
                                        form.setData(
                                            'date',
                                            event.target.value,
                                        )
                                    }
                                    className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                />
                            </div>

                            <Button
                                type="button"
                                onClick={submit}
                                disabled={
                                    !payroll ||
                                    form.processing ||
                                    form.data.employee_ids.length === 0
                                }
                                className="w-full whitespace-nowrap md:w-auto"
                            >
                                <Banknote className="mr-2 h-4 w-4" />
                                {form.processing
                                    ? 'Processing...'
                                    : `Pay Selected (${form.data.employee_ids.length})`}
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                {payroll && (
                    <Card className="dark:border-gray-700 dark:bg-gray-800">
                        <CardHeader className="mb-4">
                            <CardTitle className="flex items-center gap-2 dark:text-white">
                                <Filter className="h-5 w-5" />
                                Filters
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                                <div className="space-y-1.5">
                                    <Label
                                        htmlFor="salary-payment-search"
                                        className="dark:text-gray-200"
                                    >
                                        Search
                                    </Label>
                                    <Input
                                        id="salary-payment-search"
                                        placeholder="Search employee..."
                                        value={search}
                                        onChange={(event) =>
                                            setSearch(event.target.value)
                                        }
                                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    />
                                </div>

                                <div className="space-y-1.5">
                                    <Label className="dark:text-gray-200">
                                        Department
                                    </Label>
                                    <Select
                                        value={department}
                                        onValueChange={setDepartment}
                                    >
                                        <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                            <SelectValue placeholder="All departments" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">
                                                All departments
                                            </SelectItem>
                                            {departments.map((name) => (
                                                <SelectItem
                                                    key={name}
                                                    value={name}
                                                >
                                                    {name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="space-y-1.5">
                                    <Label className="dark:text-gray-200">
                                        Payment Status
                                    </Label>
                                    <Select
                                        value={status}
                                        onValueChange={setStatus}
                                    >
                                        <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                            <SelectValue placeholder="All statuses" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">
                                                All statuses
                                            </SelectItem>
                                            <SelectItem value="pending">
                                                Pending
                                            </SelectItem>
                                            <SelectItem value="paid">
                                                Paid
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="flex flex-col gap-2 sm:flex-row md:items-end">
                                    <Button
                                        type="button"
                                        onClick={applyFilters}
                                        className="w-full px-4 sm:w-auto"
                                    >
                                        Apply Filters
                                    </Button>
                                    <Button
                                        type="button"
                                        onClick={clearFilters}
                                        variant="secondary"
                                        className="w-full px-4 sm:w-auto"
                                    >
                                        <X className="mr-2 h-4 w-4" />
                                        Clear
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                <Card className="dark:border-gray-700 dark:bg-gray-800">
                    <CardContent>
                        {payroll ? (
                            <div className="space-y-4">
                                <div className="flex flex-col gap-2 border-b border-gray-200 pb-4 dark:border-gray-700 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <h2 className="font-semibold text-gray-900 dark:text-white">
                                            {payroll.label}
                                        </h2>
                                        <p className="text-sm text-gray-500 dark:text-gray-400">
                                            Select pending employees to
                                            generate salary payment vouchers
                                        </p>
                                    </div>
                                    <Badge
                                        variant="secondary"
                                        className="w-fit capitalize"
                                    >
                                        {payroll.status}
                                    </Badge>
                                </div>

                                <div className="overflow-x-auto">
                                    <table className="w-full min-w-[1250px]">
                                        <thead>
                                            <tr className="border-b dark:border-gray-700">
                                                <th className="w-12 p-3 text-left">
                                                    <Checkbox
                                                        checked={allSelected}
                                                        disabled={
                                                            pendingIds.length ===
                                                            0
                                                        }
                                                        onCheckedChange={
                                                            toggleAllPending
                                                        }
                                                        aria-label="Select all pending employees on this page"
                                                    />
                                                </th>
                                                <th className="p-3 text-left text-[13px] font-medium dark:text-gray-300">
                                                    Employee
                                                </th>
                                                <th className="p-3 text-left text-[13px] font-medium dark:text-gray-300">
                                                    Department
                                                </th>
                                                <th className="p-3 text-right text-[13px] font-medium dark:text-gray-300">
                                                    Monthly Salary
                                                </th>
                                                <th className="p-3 text-right text-[13px] font-medium dark:text-gray-300">
                                                    Deduction
                                                </th>
                                                <th className="p-3 text-right text-[13px] font-medium dark:text-gray-300">
                                                    Advance Adjustment
                                                </th>
                                                <th className="p-3 text-right text-[13px] font-medium dark:text-gray-300">
                                                    Bonus
                                                </th>
                                                <th className="p-3 text-right text-[13px] font-medium dark:text-gray-300">
                                                    Net Salary
                                                </th>
                                                <th className="p-3 text-left text-[13px] font-medium dark:text-gray-300">
                                                    Payment Method
                                                </th>
                                                <th className="p-3 text-left text-[13px] font-medium dark:text-gray-300">
                                                    Account
                                                </th>
                                                <th className="p-3 text-left text-[13px] font-medium dark:text-gray-300">
                                                    Status
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {items.data.map((item) => (
                                                <tr
                                                    key={item.id}
                                                    className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700"
                                                >
                                                    <td className="p-3">
                                                        <Checkbox
                                                            checked={form.data.employee_ids.includes(
                                                                item.employee_id,
                                                            )}
                                                            disabled={
                                                                item.status !==
                                                                'pending'
                                                            }
                                                            onCheckedChange={() =>
                                                                toggleEmployee(
                                                                    item.employee_id,
                                                                )
                                                            }
                                                            aria-label={`Select ${item.employee_name}`}
                                                        />
                                                    </td>
                                                    <td className="p-3 text-[13px] dark:text-white">
                                                        <div className="font-medium">
                                                            {
                                                                item.employee_name
                                                            }
                                                        </div>
                                                        <div className="text-xs text-gray-500 dark:text-gray-400">
                                                            {item.employee_code ??
                                                                'No employee code'}
                                                        </div>
                                                    </td>
                                                    <td className="p-3 text-[13px] dark:text-gray-300">
                                                        {item.department ??
                                                            'N/A'}
                                                    </td>
                                                    <td className="p-3 text-right text-[13px] tabular-nums dark:text-gray-300">
                                                        {amount(
                                                            item.monthly_salary,
                                                        )}
                                                    </td>
                                                    <td className="p-3 text-right text-[13px] tabular-nums dark:text-gray-300">
                                                        {amount(
                                                            item.total_deduction,
                                                        )}
                                                    </td>
                                                    <td className="p-3 text-right text-[13px] tabular-nums dark:text-gray-300">
                                                        {amount(
                                                            item.advance_applied,
                                                        )}
                                                    </td>
                                                    <td className="p-3 text-right text-[13px] tabular-nums dark:text-gray-300">
                                                        {amount(
                                                            item.total_bonus,
                                                        )}
                                                    </td>
                                                    <td className="p-3 text-right text-[13px] font-semibold tabular-nums dark:text-white">
                                                        {amount(
                                                            item.net_payable,
                                                        )}
                                                    </td>
                                                    <td className="p-3 text-[13px] dark:text-gray-300">
                                                        {item.payment_method ??
                                                            'Not configured'}
                                                    </td>
                                                    <td className="p-3 text-[13px] dark:text-gray-300">
                                                        {item.payment_account ? (
                                                            <>
                                                                <div>
                                                                    {
                                                                        item
                                                                            .payment_account
                                                                            .name
                                                                    }
                                                                </div>
                                                                <div className="text-xs text-gray-500 dark:text-gray-400">
                                                                    {
                                                                        item
                                                                            .payment_account
                                                                            .ac_number
                                                                    }
                                                                </div>
                                                            </>
                                                        ) : (
                                                            'Not configured'
                                                        )}
                                                    </td>
                                                    <td className="p-3">
                                                        <Badge
                                                            variant={
                                                                item.status ===
                                                                'paid'
                                                                    ? 'success'
                                                                    : 'warning'
                                                            }
                                                            className="capitalize"
                                                        >
                                                            {item.status}
                                                        </Badge>
                                                    </td>
                                                </tr>
                                            ))}

                                            {items.data.length === 0 && (
                                                <tr>
                                                    <td
                                                        colSpan={11}
                                                        className="p-10 text-center text-gray-500 dark:text-gray-400"
                                                    >
                                                        <Users className="mx-auto mb-3 h-10 w-10 text-gray-400" />
                                                        No payroll employees
                                                        match the selected
                                                        filters
                                                    </td>
                                                </tr>
                                            )}
                                        </tbody>
                                    </table>
                                </div>

                                <Pagination
                                    currentPage={items.current_page}
                                    lastPage={items.last_page}
                                    from={items.from}
                                    to={items.to}
                                    total={items.total}
                                    perPage={perPage}
                                    onPageChange={(page) =>
                                        router.get(
                                            '/salary-payments',
                                            query(page),
                                            {
                                                preserveState: true,
                                                preserveScroll: true,
                                            },
                                        )
                                    }
                                    onPerPageChange={(value) => {
                                        setPerPage(value);
                                        router.get(
                                            '/salary-payments',
                                            query(1, { per_page: value }),
                                            {
                                                preserveState: true,
                                                preserveScroll: true,
                                            },
                                        );
                                    }}
                                />
                            </div>
                        ) : (
                            <div className="py-12 text-center text-gray-500 dark:text-gray-400">
                                <Users className="mx-auto mb-3 h-10 w-10 text-gray-400" />
                                <p className="font-medium text-gray-700 dark:text-gray-300">
                                    No generated payroll is available for
                                    payment
                                </p>
                                <p className="mt-1 text-sm">
                                    Generate a payroll before creating salary
                                    payment vouchers
                                </p>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
