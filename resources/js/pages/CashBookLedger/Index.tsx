import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { FileText, Filter, X, Eye } from 'lucide-react';
import { useState } from 'react';
import { usePermission } from '@/hooks/usePermission';

interface ClosedShift {
    id: number;
    close_date: string;
    shift_id: number;
    shift: {
        id: number;
        name: string;
    };
    cash_payment: number;
    cash_receive: number;
}

interface Shift {
    id: number;
    name: string;
}

interface Props {
    closedShifts: ClosedShift[];
    shifts: Shift[];
    filters: {
        shift_id?: string;
        start_date?: string;
        end_date?: string;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Cash Book Ledger', href: '/cash-book-ledger' },
];

export default function CashBookLedger({ closedShifts, shifts, filters }: Props) {
    const { can } = usePermission();
    const canFilter = can('can-account-filter');
    const canDownload = can('can-account-download');

    const [shiftId, setShiftId] = useState(filters?.shift_id || 'all');
    const [startDate, setStartDate] = useState(filters?.start_date || '');
    const [endDate, setEndDate] = useState(filters?.end_date || '');

    const applyFilters = () => {
        const params = new URLSearchParams();
        if (shiftId && shiftId !== 'all') params.append('shift_id', shiftId);
        if (startDate) params.append('start_date', startDate);
        if (endDate) params.append('end_date', endDate);

        router.get(`/cash-book-ledger?${params.toString()}`);
    };

    const clearFilters = () => {
        setShiftId('all');
        setStartDate('');
        setEndDate('');
        router.get('/cash-book-ledger');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Cash Book Ledger" />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold dark:text-white">
                            Cash Book Ledger
                        </h1>
                        <p className="text-gray-600 dark:text-gray-400">
                            View cash account transactions and balances
                        </p>
                    </div>
                    {closedShifts.length > 0 && canDownload && (
                        <Button
                            variant="success"
                            onClick={() => {
                                const params = new URLSearchParams();
                                if (startDate)
                                    params.append('start_date', startDate);
                                if (endDate) params.append('end_date', endDate);
                                window.location.href = `/cash-book-ledger/download-pdf?${params.toString()}`;
                            }}
                        >
                            <FileText className="mr-2 h-4 w-4" />
                            Download
                        </Button>
                    )}
                </div>

                {/* Filter Card */}
                {canFilter && (
                    <Card className="dark:border-gray-700 dark:bg-gray-800">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 dark:text-white">
                                <Filter className="h-5 w-5" />
                                Filters
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                                <div>
                                    <Label className="dark:text-gray-200">
                                        Shift
                                    </Label>
                                    <Select value={shiftId} onValueChange={setShiftId}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="All Shifts" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">All Shifts</SelectItem>
                                            {shifts.map((shift) => (
                                                <SelectItem key={shift.id} value={shift.id.toString()}>
                                                    {shift.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div>
                                    <Label className="dark:text-gray-200">
                                        Start Date
                                    </Label>
                                    <Input
                                        type="date"
                                        value={startDate}
                                        onChange={(e) =>
                                            setStartDate(e.target.value)
                                        }
                                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    />
                                </div>
                                <div>
                                    <Label className="dark:text-gray-200">
                                        End Date
                                    </Label>
                                    <Input
                                        type="date"
                                        value={endDate}
                                        onChange={(e) => setEndDate(e.target.value)}
                                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    />
                                </div>
                                <div className="flex items-end gap-2">
                                    <Button onClick={applyFilters} className="px-4">
                                        Apply Filters
                                    </Button>
                                    <Button
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
                )}

                {closedShifts.length > 0 ? (
                    <Card className="dark:border-gray-700 dark:bg-gray-800">
                        <CardContent>
                            <div className="overflow-x-auto">
                                <table className="w-full">
                                    <thead>
                                        <tr className="border-b dark:border-gray-700">
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                                Date
                                            </th>
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                                Shift
                                            </th>
                                            <th className="p-4 text-right text-[13px] font-medium dark:text-gray-300">
                                                Cash Payment
                                            </th>
                                            <th className="p-4 text-right text-[13px] font-medium dark:text-gray-300">
                                                Cash Received
                                            </th>
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {closedShifts.map((shift) => (
                                            <tr
                                                key={shift.id}
                                                className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700"
                                            >
                                                <td className="p-4 text-[13px] dark:text-white">
                                                    {new Date(shift.close_date).toLocaleDateString()}
                                                </td>
                                                <td className="p-4 text-[13px] dark:text-gray-300">
                                                    {shift.shift?.name}
                                                </td>
                                                <td className="p-4 text-right text-[13px] dark:text-white">
                                                    {Number(shift.cash_payment || 0).toFixed(2)}
                                                </td>
                                                <td className="p-4 text-right text-[13px] dark:text-white">
                                                    {Number(shift.cash_receive || 0).toFixed(2)}
                                                </td>
                                                <td className="p-4 text-[13px] dark:text-white">
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() => router.get(`/cash-book-ledger/${shift.id}`)}
                                                    >
                                                        <Eye className="h-4 w-4" />
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))}
                                        <tr className="border-t-2 bg-gray-100 font-semibold dark:border-gray-600 dark:bg-gray-700">
                                            <td colSpan={2} className="p-4 text-right text-[13px] dark:text-white">
                                                Total:
                                            </td>
                                            <td className="p-4 text-right text-[13px] dark:text-white">
                                                {closedShifts.reduce((sum, shift) => sum + Number(shift.cash_payment || 0), 0).toFixed(2)}
                                            </td>
                                            <td className="p-4 text-right text-[13px] dark:text-white">
                                                {closedShifts.reduce((sum, shift) => sum + Number(shift.cash_receive || 0), 0).toFixed(2)}
                                            </td>
                                            <td></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                ) : (
                    <Card className="dark:border-gray-700 dark:bg-gray-800">
                        <CardContent>
                            <p className="p-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                No closed shifts found for the selected period
                            </p>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
