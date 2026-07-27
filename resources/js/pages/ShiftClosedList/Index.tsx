import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { DeleteModal } from '@/components/ui/delete-modal';
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
    ChevronDown,
    ChevronUp,
    Filter,
    Clock,
    Trash2,
    Eye,
    FileText,
} from 'lucide-react';
import { useState } from 'react';
import { usePermission } from '@/hooks/usePermission';

interface ShiftClosed {
    id: number;
    close_date: string;
    shift_id: number;
    shift: {
        id: number;
        name: string;
    };
    daily_reading?: {
        credit_sales: number;
        bank_sales: number;
        cash_sales: number;
        total_cash: number;
        cash_payment: number;
        office_payment: number;
        final_due_amount: number;
    };
}

interface Shift {
    id: number;
    name: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
    {
        title: 'Shift Closed List',
        href: '/shift-closed-list',
    },
];

interface ShiftClosedListProps {
    shiftClosedList: {
        data: ShiftClosed[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
    };
    shifts: Shift[];
    filters: {
        search?: string;
        shift_id?: string;
        start_date?: string;
        end_date?: string;
        sortBy?: string;
        direction?: string;
    };
}

export default function ShiftClosedList({ shiftClosedList, shifts, filters = {} }: ShiftClosedListProps) {
    const { can } = usePermission();
    const hasActionPermission = can('delete-is-shift-close');
    const canFilter = can('can-is-shift-close-filter');
    const canDownload = can('can-is-shift-close-download');
    const [search, setSearch] = useState(filters['search'] || '');
    const [shift, setShift] = useState(filters['shift_id'] || 'all');
    const [startDate, setStartDate] = useState(filters['start_date'] || '');
    const [endDate, setEndDate] = useState(filters['end_date'] || '');
    const [sortField, setSortField] = useState(filters['sortBy'] || 'close_date');
    const [sortDirection, setSortDirection] = useState(filters['direction'] || 'desc');
    const [selectedItems, setSelectedItems] = useState<number[]>([]);
    const [deletingItem, setDeletingItem] = useState<ShiftClosed | null>(null);
    const [isBulkDeleting, setIsBulkDeleting] = useState(false);

    const applyFilters = () => {
        router.get('/shift-closed-list', {
            search: search || undefined,
            shift_id: shift === 'all' ? undefined : shift,
            start_date: startDate || undefined,
            end_date: endDate || undefined,
            sort: sortField,
            direction: sortDirection,
        });
    };

    const handleSort = (column: string) => {
        const newOrder = sortField === column && sortDirection === 'asc' ? 'desc' : 'asc';
        setSortField(column);
        setSortDirection(newOrder);
        router.get('/shift-closed-list', {
            search: search || undefined,
            shift_id: shift === 'all' ? undefined : shift,
            start_date: startDate || undefined,
            end_date: endDate || undefined,
            sort: column,
            direction: newOrder,
        });
    };

    const handleDelete = (record: ShiftClosed) => {
        setDeletingItem(record);
    };

    const confirmDelete = () => {
        if (deletingItem) {
            router.delete(`/shift-closed-list/${deletingItem.id}`, {
                onSuccess: () => setDeletingItem(null),
            });
        }
    };

    const handleBulkDelete = () => {
        if (selectedItems.length === 0) {
            alert('Please select items to delete');
            return;
        }
        setIsBulkDeleting(true);
    };

    const confirmBulkDelete = () => {
        router.delete('/shift-closed-list/bulk/delete', {
            data: { ids: selectedItems },
            onSuccess: () => {
                setSelectedItems([]);
                setIsBulkDeleting(false);
            },
        });
    };

    const toggleSelectAll = () => {
        if (selectedItems.length === shiftClosedList?.data?.length) {
            setSelectedItems([]);
        } else {
            setSelectedItems(shiftClosedList?.data?.map(item => item.id) || []);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Shift Closed List" />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold dark:text-white">
                            Shift Closed List
                        </h1>
                        <p className="text-gray-600 dark:text-gray-400">
                            View all closed shift records
                        </p>
                    </div>
                    <div className="flex gap-2">
                        {selectedItems.length > 0 && can('delete-is-shift-close') && (
                            <Button
                                variant="destructive"
                                onClick={handleBulkDelete}
                            >
                                <Trash2 className="mr-2 h-4 w-4" />
                                Delete Selected ({selectedItems.length})
                            </Button>
                        )}
                        {canDownload && (
                            <Button
                                variant="success"
                                onClick={() => {
                                    const params = new URLSearchParams();
                                    if (search) params.append('search', search);
                                    if (shift !== 'all') params.append('shift_id', shift);
                                    if (startDate) params.append('start_date', startDate);
                                    if (endDate) params.append('end_date', endDate);
                                    if (sortField) params.append('sort', sortField);
                                    if (sortDirection) params.append('direction', sortDirection);
                                    window.location.href = `/shift-closed-list/download-pdf?${params.toString()}`;
                                }}
                            >
                                <FileText className="mr-2 h-4 w-4" />
                                Download
                            </Button>
                        )}
                    </div>
                </div>

                {canFilter && (
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
                                    placeholder="Search..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                />
                            </div>
                            <div>
                                <Label className="dark:text-gray-200">Shift</Label>
                                <Select value={shift} onValueChange={setShift}>
                                    <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                        <SelectValue placeholder="All shifts" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All shifts</SelectItem>
                                        {shifts?.map((s) => (
                                            <SelectItem key={s.id} value={s.id.toString()}>
                                                {s.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <Label className="dark:text-gray-200">Start Date</Label>
                                <Input
                                    type="date"
                                    value={startDate}
                                    onChange={(e) => setStartDate(e.target.value)}
                                    className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                />
                            </div>
                            <div>
                                <Label className="dark:text-gray-200">End Date</Label>
                                <Input
                                    type="date"
                                    value={endDate}
                                    onChange={(e) => setEndDate(e.target.value)}
                                    className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                />
                            </div>
                            <div className="flex items-end">
                                <Button onClick={applyFilters} className="px-4">
                                    Apply Filters
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                )}

                <Card className="dark:border-gray-700 dark:bg-gray-800">
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b dark:border-gray-700">
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                            <Checkbox
                                                checked={selectedItems.length === shiftClosedList?.data?.length && shiftClosedList?.data?.length > 0}
                                                onCheckedChange={toggleSelectAll}
                                            />
                                        </th>
                                        <th
                                            className="cursor-pointer p-4 text-left text-[13px] font-medium dark:text-gray-300"
                                            onClick={() => handleSort('close_date')}
                                        >
                                            <div className="flex items-center gap-1">
                                                Date
                                                {sortField === 'close_date' &&
                                                    (sortDirection === 'asc' ? (
                                                        <ChevronUp className="h-4 w-4" />
                                                    ) : (
                                                        <ChevronDown className="h-4 w-4" />
                                                    ))}
                                            </div>
                                        </th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                            Shift
                                        </th>
                                        <th className="p-4 text-right text-[13px] font-medium dark:text-gray-300">
                                            Credit Sales
                                        </th>
                                        <th className="p-4 text-right text-[13px] font-medium dark:text-gray-300">
                                            Bank Sales
                                        </th>
                                        <th className="p-4 text-right text-[13px] font-medium dark:text-gray-300">
                                            Cash Sales
                                        </th>
                                        <th className="p-4 text-right text-[13px] font-medium dark:text-gray-300">
                                            Total Cash
                                        </th>
                                        <th className="p-4 text-right text-[13px] font-medium dark:text-gray-300">
                                            Cash Payment
                                        </th>
                                        <th className="p-4 text-right text-[13px] font-medium dark:text-gray-300">
                                            Office Payment
                                        </th>
                                        <th className="p-4 text-right text-[13px] font-medium dark:text-gray-300">
                                            Final Due
                                        </th>
                                        {hasActionPermission && (
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                            Actions
                                        </th>
                                        )}
                                    </tr>
                                </thead>
                                <tbody>
                                    {shiftClosedList?.data?.length > 0 ? (
                                        shiftClosedList.data.map((record) => (
                                            <tr
                                                key={record.id}
                                                className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700"
                                            >
                                                <td className="p-4 text-[13px] dark:text-white">
                                                    <Checkbox
                                                        checked={selectedItems.includes(record.id)}
                                                        onCheckedChange={(checked) => {
                                                            if (checked) {
                                                                setSelectedItems([...selectedItems, record.id]);
                                                            } else {
                                                                setSelectedItems(selectedItems.filter(id => id !== record.id));
                                                            }
                                                        }}
                                                    />
                                                </td>
                                                <td className="p-4 text-[13px] dark:text-white">
                                                    {new Date(record.close_date).toLocaleDateString()}
                                                </td>
                                                <td className="p-4 text-[13px] dark:text-gray-300">
                                                    {record.shift?.name}
                                                </td>
                                                <td className="p-4 text-right text-[13px] dark:text-white">
                                                    {Number(record.daily_reading?.credit_sales || 0).toFixed(2)}
                                                </td>
                                                <td className="p-4 text-right text-[13px] dark:text-white">
                                                    {Number(record.daily_reading?.bank_sales || 0).toFixed(2)}
                                                </td>
                                                <td className="p-4 text-right text-[13px] dark:text-white">
                                                    {Number(record.daily_reading?.cash_sales || 0).toFixed(2)}
                                                </td>
                                                <td className="p-4 text-right text-[13px] dark:text-white">
                                                    {Number(record.daily_reading?.total_cash || 0).toFixed(2)}
                                                </td>
                                                <td className="p-4 text-right text-[13px] dark:text-white">
                                                    {Number(record.daily_reading?.cash_payment || 0).toFixed(2)}
                                                </td>
                                                <td className="p-4 text-right text-[13px] dark:text-white">
                                                    {Number(record.daily_reading?.office_payment || 0).toFixed(2)}
                                                </td>
                                                <td className="p-4 text-right text-[13px] dark:text-white">
                                                    {Number(record.daily_reading?.final_due_amount || 0).toFixed(2)}
                                                </td>
                                                {hasActionPermission && (
                                                <td className="p-4 text-[13px] dark:text-white">
                                                    <div className="flex gap-2">
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={() => router.get(`/shift-closed-list/${record.id}`)}
                                                        >
                                                            <Eye className="h-4 w-4" />
                                                        </Button>
                                                        {can('delete-is-shift-close') && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => handleDelete(record)}
                                                            className="text-red-600 hover:text-red-700"
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                        )}
                                                    </div>
                                                </td>
                                                )}
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td
                                                colSpan={hasActionPermission ? 11 : 10}
                                                className="p-8 text-center text-gray-500 dark:text-gray-400"
                                            >
                                                <Clock className="mx-auto mb-4 h-12 w-12 text-gray-400" />
                                                No shift closed records found
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                <DeleteModal
                    isOpen={!!deletingItem}
                    onClose={() => setDeletingItem(null)}
                    onConfirm={confirmDelete}
                    title="Delete Shift Record"
                    message={`Are you sure you want to delete the shift record for "${deletingItem?.shift?.name}" on ${deletingItem ? new Date(deletingItem.close_date).toLocaleDateString() : ''}? This action cannot be undone.`}
                />

                <DeleteModal
                    isOpen={isBulkDeleting}
                    onClose={() => setIsBulkDeleting(false)}
                    onConfirm={confirmBulkDelete}
                    title="Delete Selected Records"
                    message={`Are you sure you want to delete ${selectedItems.length} selected shift records? This action cannot be undone.`}
                />
            </div>
        </AppLayout>
    );
}