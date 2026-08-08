import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { Banknote, CalendarDays, Users } from 'lucide-react';
import { useMemo } from 'react';

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

interface Props {
    payrolls: Payroll[];
    payroll: { id: number; label: string; status: string } | null;
    items: Item[];
    filters: { payroll_id: number | null; date: string };
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

export default function SalaryPaymentIndex({ payrolls, payroll, items, filters }: Props) {
    const form = useForm({
        payroll_id: filters.payroll_id ?? 0,
        date: filters.date,
        employee_ids: [] as number[],
    });
    const pendingIds = useMemo(
        () => items.filter((item) => item.status === 'pending').map((item) => item.employee_id),
        [items],
    );
    const allSelected = pendingIds.length > 0 && pendingIds.every((id) => form.data.employee_ids.includes(id));

    const selectPayroll = (value: string) => {
        router.get('/salary-payments', { payroll_id: Number(value) }, { preserveState: true, preserveScroll: true });
        form.setData('payroll_id', Number(value));
        form.setData('employee_ids', []);
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post('/salary-payments', { preserveScroll: true, onSuccess: () => form.setData('employee_ids', []) });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Salary Payment" />
            <div className="space-y-6 p-6">
                <div>
                    <h1 className="text-3xl font-bold dark:text-white">Salary Payment</h1>
                    <p className="text-gray-600 dark:text-gray-400">Pay only generated payrolls through the existing Payment Voucher workflow.</p>
                </div>
                <Card>
                    <CardHeader><CardTitle className="flex items-center gap-2"><CalendarDays className="h-5 w-5" />Select Generated Payroll</CardTitle></CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-[1fr_220px_auto]">
                        <Select value={form.data.payroll_id ? form.data.payroll_id.toString() : ''} onValueChange={selectPayroll} disabled={payrolls.length === 0}>
                            <SelectTrigger><SelectValue placeholder="Choose generated payroll" /></SelectTrigger>
                            <SelectContent>
                                {payrolls.map((row) => <SelectItem key={row.id} value={row.id.toString()}>{row.label} · {row.pending_items_count} pending</SelectItem>)}
                            </SelectContent>
                        </Select>
                        <div><Label htmlFor="salary-payment-date">Payment Date</Label><Input id="salary-payment-date" type="date" value={form.data.date} onChange={(event) => form.setData('date', event.target.value)} /></div>
                        <div className="flex items-end"><Button type="button" onClick={submit} disabled={!payroll || form.processing || form.data.employee_ids.length === 0}><Banknote className="mr-2 h-4 w-4" />Pay Selected ({form.data.employee_ids.length})</Button></div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="p-0">
                        {payroll ? (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[1350px]">
                                    <thead><tr className="border-b text-left text-sm text-gray-500 dark:border-gray-700">
                                        <th className="w-12 p-4"><Checkbox checked={allSelected} disabled={pendingIds.length === 0} onCheckedChange={() => form.setData('employee_ids', allSelected ? [] : pendingIds)} aria-label="Select all pending employees" /></th>
                                        <th className="p-4">Employee</th><th className="p-4">Department</th><th className="p-4 text-right">Monthly Salary</th><th className="p-4 text-right">Deduction</th><th className="p-4 text-right">Advance Adjustment</th><th className="p-4 text-right">Bonus</th><th className="p-4 text-right">Net Salary</th><th className="p-4">Payment Method</th><th className="p-4">Account</th><th className="p-4">Status</th>
                                    </tr></thead>
                                    <tbody>
                                        {items.map((item) => {
                                            const selected = form.data.employee_ids.includes(item.employee_id);
                                            return <tr key={item.id} className="border-b dark:border-gray-700">
                                                <td className="p-4"><Checkbox checked={selected} disabled={item.status !== 'pending'} onCheckedChange={() => form.setData('employee_ids', selected ? form.data.employee_ids.filter((id) => id !== item.employee_id) : [...form.data.employee_ids, item.employee_id])} aria-label={`Select ${item.employee_name}`} /></td>
                                                <td className="p-4 dark:text-white"><div className="font-medium">{item.employee_name}</div><div className="text-xs text-gray-500">{item.employee_code}</div></td>
                                                <td className="p-4 text-sm dark:text-gray-300">{item.department ?? 'N/A'}</td>
                                                <td className="p-4 text-right tabular-nums dark:text-gray-300">{amount(item.monthly_salary)}</td>
                                                <td className="p-4 text-right tabular-nums dark:text-gray-300">{amount(item.total_deduction)}</td>
                                                <td className="p-4 text-right tabular-nums dark:text-gray-300">{amount(item.advance_applied)}</td>
                                                <td className="p-4 text-right tabular-nums dark:text-gray-300">{amount(item.total_bonus)}</td>
                                                <td className="p-4 text-right font-semibold tabular-nums dark:text-white">{amount(item.net_payable)}</td>
                                                <td className="p-4 text-sm dark:text-gray-300">{item.payment_method ?? 'Not configured'}</td>
                                                <td className="p-4 text-sm dark:text-gray-300">{item.payment_account?.name ?? 'Not configured'}</td>
                                                <td className="p-4"><Badge variant={item.status === 'paid' ? 'success' : 'warning'}>{item.status}</Badge></td>
                                            </tr>;
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        ) : <div className="p-12 text-center text-gray-500"><Users className="mx-auto mb-3 h-10 w-10" />No Generated payroll is available for payment.</div>}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
