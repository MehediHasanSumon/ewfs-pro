import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { Head, router, useForm } from '@inertiajs/react';
import { ArrowLeft, LockKeyhole, Play } from 'lucide-react';
import { useMemo } from 'react';

interface Period {
    id: number;
    label: string;
    status: string;
}

interface Item {
    id: number;
    employee_id: number;
    employee_name: string;
    employee_code: string | null;
    department: string | null;
    designation: string | null;
    payment_method: string | null;
    payment_account: {
        id: number;
        name: string;
        ac_number: string;
    } | null;
    gross_salary: number;
    net_salary: number;
    advance_balance: number;
    advance_applied: number;
    loan_balance: number;
    net_payable: number;
    status: string;
    payment_voucher: { voucher_no: string } | null;
}

const amount = (value: number) =>
    Number(value ?? 0).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

export default function Processing({
    period,
    items,
    canProcess,
}: {
    period: Period;
    items: Item[];
    canProcess: boolean;
}) {
    const pendingIds = useMemo(
        () => items.filter((item) => item.status === 'pending').map((item) => item.employee_id),
        [items],
    );
    const form = useForm({
        date: new Date().toISOString().slice(0, 10),
        employee_ids: pendingIds,
        remarks: {} as Record<number, string>,
    });
    const allPendingSelected =
        pendingIds.length > 0 &&
        pendingIds.every((employeeId) =>
            form.data.employee_ids.includes(employeeId),
        );

    const toggle = (employeeId: number) => {
        form.setData(
            'employee_ids',
            form.data.employee_ids.includes(employeeId)
                ? form.data.employee_ids.filter((id) => id !== employeeId)
                : [...form.data.employee_ids, employeeId],
        );
    };
    const toggleAll = () => {
        form.setData('employee_ids', allPendingSelected ? [] : pendingIds);
    };

    const submit = () => {
        form.post(`/payroll/${period.id}/process`, { preserveScroll: true });
    };

    return (
        <AppLayout>
            <Head title={`Payroll Processing - ${period.label}`} />
            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold dark:text-white">{period.label}</h1>
                        <p className="text-sm text-gray-500 dark:text-gray-400">
                            Status: <span className="capitalize">{period.status}</span>
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button type="button" variant="outline" onClick={() => router.get('/payroll')}>
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Periods
                        </Button>
                        {period.status === 'completed' && (
                            <Button type="button" variant="secondary" onClick={() => router.post(`/payroll/${period.id}/lock`)}>
                                <LockKeyhole className="mr-2 h-4 w-4" />
                                Lock Period
                            </Button>
                        )}
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between gap-4">
                            <CardTitle>Salary Processing</CardTitle>
                            <div className="flex items-center gap-2">
                                <Input
                                    type="date"
                                    value={form.data.date}
                                    onChange={(event) => form.setData('date', event.target.value)}
                                    disabled={!canProcess}
                                    aria-label="Payroll posting date"
                                />
                                <Button type="button" onClick={submit} disabled={!canProcess || form.processing || form.data.employee_ids.length === 0}>
                                    <Play className="mr-2 h-4 w-4" />
                                    Generate Vouchers
                                </Button>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[1380px]">
                                <thead>
                                    <tr className="border-b text-left text-sm text-gray-500 dark:border-gray-700">
                                        <th className="w-12 p-4">
                                            <Checkbox
                                                checked={allPendingSelected}
                                                disabled={!canProcess || pendingIds.length === 0}
                                                onCheckedChange={toggleAll}
                                                aria-label="Select all pending payroll items"
                                            />
                                        </th>
                                        <th className="p-4">Employee</th>
                                        <th className="p-4">Department</th>
                                        <th className="p-4">Payment Method</th>
                                        <th className="p-4">Account</th>
                                        <th className="p-4 text-right">Gross</th>
                                        <th className="p-4 text-right">Advance</th>
                                        <th className="p-4 text-right">Advance Applied</th>
                                        <th className="p-4 text-right">Loan</th>
                                        <th className="p-4 text-right">Net Payable</th>
                                        <th className="p-4">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {items.map((item) => {
                                        const selected = form.data.employee_ids.includes(item.employee_id);
                                        return (
                                            <tr key={item.id} className="border-b dark:border-gray-700">
                                                <td className="p-4">
                                                    <Checkbox
                                                        checked={selected}
                                                        disabled={!canProcess || item.status !== 'pending'}
                                                        onCheckedChange={() => toggle(item.employee_id)}
                                                        aria-label={`Select ${item.employee_name}`}
                                                    />
                                                </td>
                                                <td className="p-4 dark:text-white">
                                                    <div className="font-medium">{item.employee_name}</div>
                                                    <div className="text-xs text-gray-500">{item.employee_code}</div>
                                                </td>
                                                <td className="p-4 text-sm dark:text-gray-300">{item.department ?? 'N/A'}</td>
                                                <td className="p-4 text-sm dark:text-gray-300">{item.payment_method ?? 'N/A'}</td>
                                                <td className="p-4 text-sm dark:text-gray-300">{item.payment_account?.name ?? 'N/A'}</td>
                                                <td className="p-4 text-right tabular-nums dark:text-gray-300">{amount(item.gross_salary)}</td>
                                                <td className="p-4 text-right tabular-nums dark:text-gray-300">{amount(item.advance_balance)}</td>
                                                <td className="p-4 text-right tabular-nums dark:text-gray-300">{amount(item.advance_applied)}</td>
                                                <td className="p-4 text-right tabular-nums dark:text-gray-300">{amount(item.loan_balance)}</td>
                                                <td className="p-4 text-right font-semibold tabular-nums dark:text-white">{amount(item.net_payable)}</td>
                                                <td className="p-4 text-sm capitalize dark:text-gray-300">
                                                    {item.status}
                                                    {item.payment_voucher && (
                                                        <div className="text-xs text-gray-500">{item.payment_voucher.voucher_no}</div>
                                                    )}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
