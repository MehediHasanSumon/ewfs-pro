import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { Head, router, useForm } from '@inertiajs/react';
import { CalendarPlus, LockKeyhole, Play, WalletCards } from 'lucide-react';
import { FormEvent } from 'react';

interface Period {
    id: number;
    label: string;
    month: number;
    year: number;
    status: string;
    payable_date: string | null;
    snapshot_count?: number;
    item_count?: number;
}

interface Paginator {
    data: Period[];
    current_page: number;
    last_page: number;
}

export default function Periods({ periods }: { periods: Paginator }) {
    const form = useForm({
        month: new Date().getMonth() + 1,
        year: new Date().getFullYear(),
        payable_date: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/payroll', { preserveScroll: true });
    };

    return (
        <AppLayout>
            <Head title="Payroll Periods" />
            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold dark:text-white">
                            Payroll Periods
                        </h1>
                        <p className="text-sm text-gray-500 dark:text-gray-400">
                            Create, process, complete, and lock payroll periods.
                        </p>
                    </div>
                    <Button type="button" onClick={() => document.getElementById('payroll-period-form')?.scrollIntoView({ behavior: 'smooth' })}>
                        <CalendarPlus className="mr-2 h-4 w-4" />
                        New Period
                    </Button>
                </div>

                <Card id="payroll-period-form">
                    <CardHeader>
                        <CardTitle>Create Payroll Period</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form className="grid gap-4 md:grid-cols-4" onSubmit={submit}>
                            <Input
                                type="number"
                                min={1}
                                max={12}
                                value={form.data.month}
                                onChange={(event) => form.setData('month', Number(event.target.value))}
                                error={Boolean(form.errors.month)}
                                aria-label="Payroll month"
                            />
                            <Input
                                type="number"
                                min={2000}
                                max={2100}
                                value={form.data.year}
                                onChange={(event) => form.setData('year', Number(event.target.value))}
                                error={Boolean(form.errors.year)}
                                aria-label="Payroll year"
                            />
                            <Input
                                type="date"
                                value={form.data.payable_date}
                                onChange={(event) => form.setData('payable_date', event.target.value)}
                                aria-label="Salary payable date"
                            />
                            <Button type="submit" disabled={form.processing}>
                                Create Period
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[820px]">
                                <thead>
                                    <tr className="border-b text-left text-sm text-gray-500 dark:border-gray-700">
                                        <th className="p-4">Period</th>
                                        <th className="p-4">Payable Date</th>
                                        <th className="p-4">Status</th>
                                        <th className="p-4">Employees</th>
                                        <th className="p-4 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {periods.data.map((period) => (
                                        <tr key={period.id} className="border-b dark:border-gray-700">
                                            <td className="p-4 font-medium dark:text-white">{period.label}</td>
                                            <td className="p-4 text-sm text-gray-600 dark:text-gray-300">
                                                {period.payable_date ?? 'Not set'}
                                            </td>
                                            <td className="p-4 capitalize text-sm dark:text-gray-300">{period.status}</td>
                                            <td className="p-4 text-sm dark:text-gray-300">
                                                {period.item_count ?? 0}
                                            </td>
                                            <td className="p-4">
                                                <div className="flex justify-end gap-2">
                                                    {period.status === 'draft' && (
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            onClick={() => router.post(`/payroll/${period.id}/start`)}
                                                        >
                                                            <Play className="mr-2 h-4 w-4" />
                                                            Start
                                                        </Button>
                                                    )}
                                                    {period.status !== 'draft' && (
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() => router.get(`/payroll/${period.id}/processing`)}
                                                        >
                                                            <WalletCards className="mr-2 h-4 w-4" />
                                                            Open
                                                        </Button>
                                                    )}
                                                    {period.status === 'completed' && (
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="secondary"
                                                            onClick={() => router.post(`/payroll/${period.id}/lock`)}
                                                        >
                                                            <LockKeyhole className="mr-2 h-4 w-4" />
                                                            Lock
                                                        </Button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                    {periods.data.length === 0 && (
                                        <tr>
                                            <td colSpan={5} className="p-10 text-center text-gray-500">
                                                No payroll periods found.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
                {periods.last_page > 1 && (
                    <div className="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            disabled={periods.current_page <= 1}
                            onClick={() => router.get('/payroll', { page: periods.current_page - 1 }, { preserveState: true })}
                        >
                            Previous
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={periods.current_page >= periods.last_page}
                            onClick={() => router.get('/payroll', { page: periods.current_page + 1 }, { preserveState: true })}
                        >
                            Next
                        </Button>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
