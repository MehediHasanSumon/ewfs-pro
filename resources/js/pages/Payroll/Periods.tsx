import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Pagination } from '@/components/ui/pagination';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { CalendarPlus, FileText, Filter, FolderOpen, Trash2, X, XCircle } from 'lucide-react';
import { FormEvent, useState } from 'react';

interface Period {
    id: number;
    payroll_code: string | null;
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
    per_page: number;
    total: number;
    from: number;
    to: number;
}

interface Filters {
    search?: string;
    status?: string;
    month?: number | null;
    year?: number | null;
    per_page?: number;
    employee_id?: number | null;
}

const months = Array.from({ length: 12 }, (_, index) => ({
    value: index + 1,
    label: new Date(2000, index, 1).toLocaleString('en-US', { month: 'long' }),
}));

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Payroll', href: '/payroll' },
];

export default function Periods({
    periods,
    filters = {},
}: {
    periods: Paginator;
    filters?: Filters;
}) {
    const form = useForm({
        month: new Date().getMonth() + 1,
        year: new Date().getFullYear(),
        payable_date: '',
    });
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? 'all');
    const [month, setMonth] = useState(filters.month?.toString() ?? 'all');
    const [year, setYear] = useState(filters.year?.toString() ?? '');
    const [perPage, setPerPage] = useState(filters.per_page ?? periods.per_page ?? 10);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/payroll', { preserveScroll: true });
    };

    const query = (page?: number, overrides: Record<string, string | number | undefined> = {}) => ({
        search: overrides.search ?? (search || undefined),
        status: overrides.status ?? (status === 'all' ? undefined : status),
        month: overrides.month ?? (month === 'all' ? undefined : Number(month)),
        year: overrides.year ?? (year || undefined),
        per_page: overrides.per_page ?? perPage,
        employee_id: filters.employee_id ?? undefined,
        page,
    });

    const applyFilters = () => {
        router.get('/payroll', query(), { preserveState: true, preserveScroll: true });
    };

    const clearFilters = () => {
        setSearch('');
        setStatus('all');
        setMonth('all');
        setYear('');
        router.get('/payroll', {
            per_page: perPage,
            employee_id: filters.employee_id ?? undefined,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handlePageChange = (page: number) => {
        router.get('/payroll', query(page), { preserveState: true, preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Payroll" />
            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold dark:text-white">Payroll</h1>
                        <p className="text-gray-600 dark:text-gray-400">
                            Create, generate, review, and pay monthly payroll.
                        </p>
                    </div>
                    <Button
                        type="button"
                        onClick={() => document.getElementById('payroll-form')?.scrollIntoView({ behavior: 'smooth' })}
                    >
                        <CalendarPlus className="mr-2 h-4 w-4" />
                        New Payroll
                    </Button>
                </div>

                <Card id="payroll-form" className="dark:border-gray-700 dark:bg-gray-800">
                    <CardHeader>
                        <CardTitle>Create Payroll</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form className="grid gap-4 md:grid-cols-2 xl:grid-cols-4" onSubmit={submit}>
                            <div>
                                <Label className="dark:text-gray-200">Month</Label>
                                <Select
                                    value={form.data.month.toString()}
                                    onValueChange={(value) => form.setData('month', Number(value))}
                                >
                                    <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                        <SelectValue placeholder="Choose month" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {months.map((item) => (
                                            <SelectItem key={item.value} value={item.value.toString()}>
                                                {item.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <Label className="dark:text-gray-200">Year</Label>
                                <Input
                                    type="number"
                                    min={2000}
                                    max={2100}
                                    value={form.data.year}
                                    onChange={(event) => form.setData('year', Number(event.target.value))}
                                    error={Boolean(form.errors.year)}
                                    className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                />
                            </div>
                            <div>
                                <Label className="dark:text-gray-200">Payable Date</Label>
                                <Input
                                    type="date"
                                    value={form.data.payable_date}
                                    onChange={(event) => form.setData('payable_date', event.target.value)}
                                    className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                />
                            </div>
                            <div className="flex items-end">
                                <Button type="submit" disabled={form.processing}>
                                    Create Payroll
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card className="dark:border-gray-700 dark:bg-gray-800">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 dark:text-white">
                            <Filter className="h-5 w-5" />
                            Filters
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-5">
                            <div>
                                <Label className="dark:text-gray-200">Search</Label>
                                <Input
                                    placeholder="Search payroll code or year..."
                                    value={search}
                                    onChange={(event) => setSearch(event.target.value)}
                                    className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                />
                            </div>
                            <div>
                                <Label className="dark:text-gray-200">Status</Label>
                                <Select value={status} onValueChange={setStatus}>
                                    <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                        <SelectValue placeholder="All statuses" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All statuses</SelectItem>
                                        {['draft', 'processing', 'generated', 'paid', 'cancelled'].map((value) => (
                                            <SelectItem key={value} value={value}>
                                                <span className="capitalize">{value}</span>
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <Label className="dark:text-gray-200">Month</Label>
                                <Select value={month} onValueChange={setMonth}>
                                    <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                        <SelectValue placeholder="All months" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All months</SelectItem>
                                        {months.map((item) => (
                                            <SelectItem key={item.value} value={item.value.toString()}>
                                                {item.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <Label className="dark:text-gray-200">Year</Label>
                                <Input
                                    type="number"
                                    min={2000}
                                    max={2100}
                                    placeholder="All years"
                                    value={year}
                                    onChange={(event) => setYear(event.target.value)}
                                    className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                />
                            </div>
                            <div className="flex items-end gap-2">
                                <Button type="button" onClick={applyFilters} className="px-4">
                                    Apply Filters
                                </Button>
                                <Button type="button" onClick={clearFilters} variant="secondary" className="px-4">
                                    <X className="mr-2 h-4 w-4" />
                                    Clear
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card className="dark:border-gray-700 dark:bg-gray-800">
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[900px]">
                                <thead>
                                    <tr className="border-b text-left text-sm text-gray-500 dark:border-gray-700">
                                        <th className="p-4">Payroll</th>
                                        <th className="p-4">Payable Date</th>
                                        <th className="p-4">Status</th>
                                        <th className="p-4">Employees</th>
                                        <th className="p-4 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {periods.data.map((period) => (
                                        <tr key={period.id} className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">
                                            <td className="p-4 dark:text-white">
                                                <div className="font-medium">{period.label}</div>
                                                <div className="text-xs text-gray-500">{period.payroll_code}</div>
                                            </td>
                                            <td className="p-4 text-sm text-gray-600 dark:text-gray-300">
                                                {period.payable_date ?? 'Not set'}
                                            </td>
                                            <td className="p-4 text-sm capitalize dark:text-gray-300">{period.status}</td>
                                            <td className="p-4 text-sm dark:text-gray-300">{period.item_count ?? 0}</td>
                                            <td className="p-4">
                                                <div className="flex justify-end gap-2">
                                                    {period.status !== 'paid' && period.status !== 'cancelled' && (
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() => router.get(`/payroll/${period.id}/processing`)}
                                                        >
                                                            <FolderOpen className="mr-2 h-4 w-4" />
                                                            Open
                                                        </Button>
                                                    )}
                                                    {period.status === 'paid' && (
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() => router.get(`/payroll/${period.id}/processing`)}
                                                        >
                                                            <FileText className="mr-2 h-4 w-4" />
                                                            View
                                                        </Button>
                                                    )}
                                                    {period.status === 'draft' && (
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="secondary"
                                                            onClick={() => {
                                                                if (window.confirm('Delete this unpaid payroll?')) {
                                                                    router.delete(`/payroll/${period.id}`);
                                                                }
                                                            }}
                                                        >
                                                            <Trash2 className="mr-2 h-4 w-4" />
                                                            Delete
                                                        </Button>
                                                    )}
                                                    {period.status === 'generated' && (
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="secondary"
                                                            onClick={() => {
                                                                if (window.confirm('Cancel this unpaid payroll?')) {
                                                                    router.post(`/payroll/${period.id}/cancel`);
                                                                }
                                                            }}
                                                        >
                                                            <XCircle className="mr-2 h-4 w-4" />
                                                            Cancel
                                                        </Button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                    {periods.data.length === 0 && (
                                        <tr>
                                            <td colSpan={5} className="p-10 text-center text-gray-500">
                                                No payrolls found.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                        <div className="px-4 pb-4">
                            <Pagination
                                currentPage={periods.current_page}
                                lastPage={periods.last_page}
                                from={periods.from}
                                to={periods.to}
                                total={periods.total}
                                perPage={perPage}
                                onPageChange={handlePageChange}
                                onPerPageChange={(value) => {
                                    setPerPage(value);
                                    router.get('/payroll', query(1, { per_page: value }), {
                                        preserveState: true,
                                        preserveScroll: true,
                                    });
                                }}
                            />
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
