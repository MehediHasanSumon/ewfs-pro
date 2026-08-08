import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Pagination } from '@/components/ui/pagination';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, Filter, X, XCircle } from 'lucide-react';
import { useState } from 'react';

interface Period {
    id: number;
    label: string;
    status: string;
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
}

const months = Array.from({ length: 12 }, (_, index) => ({
    value: index + 1,
    label: new Date(2000, index, 1).toLocaleString('en-US', { month: 'long' }),
}));

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Payroll', href: '/payroll' },
    { title: 'Payroll History', href: '/payroll/history' },
];

export default function History({
    periods,
    filters = {},
}: {
    periods: Paginator;
    filters?: Filters;
}) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? 'all');
    const [month, setMonth] = useState(filters.month?.toString() ?? 'all');
    const [year, setYear] = useState(filters.year?.toString() ?? '');
    const [perPage, setPerPage] = useState(filters.per_page ?? periods.per_page ?? 10);

    const query = (page?: number, overrides: Record<string, string | number | undefined> = {}) => ({
        search: overrides.search ?? (search || undefined),
        status: overrides.status ?? (status === 'all' ? undefined : status),
        month: overrides.month ?? (month === 'all' ? undefined : Number(month)),
        year: overrides.year ?? (year || undefined),
        per_page: overrides.per_page ?? perPage,
        page,
    });

    const applyFilters = () => {
        router.get('/payroll/history', query(), { preserveState: true, preserveScroll: true });
    };

    const clearFilters = () => {
        setSearch('');
        setStatus('all');
        setMonth('all');
        setYear('');
        router.get('/payroll/history', { per_page: perPage }, { preserveState: true, preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Payroll History" />
            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold dark:text-white">Payroll History</h1>
                        <p className="text-gray-600 dark:text-gray-400">Completed and cancelled payroll records.</p>
                    </div>
                    <Button type="button" variant="outline" onClick={() => router.get('/payroll')}>
                        <ArrowLeft className="mr-2 h-4 w-4" />
                        Periods
                    </Button>
                </div>
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
                                        <SelectItem value="paid">Paid</SelectItem>
                                        <SelectItem value="cancelled">Cancelled</SelectItem>
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
                                <Button
                                    type="button"
                                    onClick={clearFilters}
                                    variant="secondary"
                                    className="px-4"
                                >
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
                            <table className="w-full min-w-[700px]">
                                <thead>
                                    <tr className="border-b text-left text-sm text-gray-500 dark:border-gray-700">
                                        <th className="p-4">Period</th>
                                        <th className="p-4">Employees</th>
                                        <th className="p-4">Status</th>
                                        <th className="p-4 text-right">View</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {periods.data.map((period) => (
                                        <tr key={period.id} className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">
                                            <td className="p-4 font-medium dark:text-white">{period.label}</td>
                                            <td className="p-4 text-sm dark:text-gray-300">{period.item_count ?? 0}</td>
                                            <td className="p-4 text-sm capitalize dark:text-gray-300">
                                                <div className="flex items-center gap-2">
                                                    {period.status === 'paid' && <CheckCircle2 className="h-4 w-4 text-emerald-600" />}
                                                    {period.status === 'cancelled' && <XCircle className="h-4 w-4 text-gray-500" />}
                                                    {period.status}
                                                </div>
                                            </td>
                                            <td className="p-4 text-right">
                                                <Button type="button" size="sm" variant="outline" onClick={() => router.get(`/payroll/${period.id}/processing`)}>
                                                    View
                                                </Button>
                                            </td>
                                        </tr>
                                    ))}
                                    {periods.data.length === 0 && (
                                        <tr>
                                            <td colSpan={4} className="p-10 text-center text-gray-500 dark:text-gray-400">
                                                No payroll history found.
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
                                onPageChange={(page) => router.get('/payroll/history', query(page), { preserveState: true, preserveScroll: true })}
                                onPerPageChange={(value) => {
                                    setPerPage(value);
                                    router.get('/payroll/history', query(1, { per_page: value }), {
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
