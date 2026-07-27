import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DeleteModal } from '@/components/ui/delete-modal';
import { FormModal } from '@/components/ui/form-modal';
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
import {
    Building,
    ChevronDown,
    ChevronUp,
    Edit,
    FileText,
    Filter,
    Plus,
    Trash2,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { usePermission } from '@/hooks/usePermission';

interface OfficePayment {
    id: number;
    date: string;
    shift: { id: number; name: string };

    to_account: { id: number; name: string };
    amount: number;
    payment_type: string;
    remarks: string;
    created_at: string;
}

interface Account {
    id: number;
    name: string;
    ac_number: string;
}

interface Shift {
    id: number;
    name: string;
}

interface ClosedShift {
    close_date: string;
    shift_id: number;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Office Payments', href: '/office-payments' },
];

interface OfficePaymentsProps {
    officePayments: {
        data: OfficePayment[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
    };
    accounts: Account[];
    groupedAccounts: Record<string, Account[]>;
    shifts: Shift[];
    closedShifts: ClosedShift[];
    paymentTypes: Array<{ code: string; name: string; type: string }>;
    types: Array<{ value: string; label: string }>;
    filters: {
        search?: string;
        shift_id?: string;
        start_date?: string;
        end_date?: string;
        type?: string;
        sort_by?: string;
        sort_order?: string;
        per_page?: number;
    };
}

export default function OfficePayments({
    officePayments = {
        data: [],
        current_page: 1,
        last_page: 1,
        per_page: 10,
        total: 0,
        from: 0,
        to: 0,
    },
    accounts = [],
    groupedAccounts = {},
    shifts = [],
    closedShifts = [],
    paymentTypes = [],
    types = [],
    filters = {},
}: OfficePaymentsProps) {
    const { can } = usePermission();
    const hasActionPermission = can('update-office-payment') || can('delete-office-payment');
    const canFilter = can('can-office-payment-filter');
    const canDownload = can('can-office-payment-download');
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editingPayment, setEditingPayment] = useState<OfficePayment | null>(
        null,
    );
    const [deletingPayment, setDeletingPayment] =
        useState<OfficePayment | null>(null);
    const [selectedPayments, setSelectedPayments] = useState<number[]>([]);
    const [isBulkDeleting, setIsBulkDeleting] = useState(false);
    const [search, setSearch] = useState('');

    const [startDate, setStartDate] = useState('');
    const [endDate, setEndDate] = useState('');
    const [selectedType, setSelectedType] = useState('');
    const [selectedShift, setSelectedShift] = useState('');
    const [sortBy, setSortBy] = useState('created_at');
    const [sortOrder, setSortOrder] = useState('desc');
    const [perPage, setPerPage] = useState(10);
    const [availableShifts, setAvailableShifts] = useState<Shift[]>(shifts);

    const { data, setData, post, put, processing, errors, reset } = useForm({
        date: '',
        shift_id: '',
        to_account_id: '',
        amount: '',
        payment_type: paymentTypes[0]?.type || '',
        remarks: '',
    });

    const getFilteredAccounts = () => {
        const groupName =
            data.payment_type === 'Cash' ? 'Cash in hand' : data.payment_type;
        return groupedAccounts[groupName] || [];
    };

    const getAvailableShifts = (selectedDate: string) => {
        if (!selectedDate) return shifts;

        const closedShiftIds = closedShifts
            .filter((cs) => cs.close_date === selectedDate)
            .map((cs) => cs.shift_id);

        return shifts.filter((shift) => !closedShiftIds.includes(shift.id));
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingPayment) {
            put(`/office-payments/${editingPayment.id}`, {
                onSuccess: () => {
                    setEditingPayment(null);
                    reset();
                },
            });
        } else {
            post('/office-payments', {
                onSuccess: () => {
                    setIsCreateOpen(false);
                    reset();
                },
            });
        }
    };

    const handleEdit = (payment: OfficePayment) => {
        setEditingPayment(payment);
        let displayPaymentType = payment.payment_type || '';
        if (displayPaymentType === 'cash') displayPaymentType = 'Cash';
        else if (displayPaymentType === 'mobile bank')
            displayPaymentType = 'Mobile Bank';
        else if (displayPaymentType === 'bank')
            displayPaymentType = 'Bank Account';
        setData({
            date: payment.date ? payment.date.split('T')[0] : '',
            shift_id: payment.shift?.id?.toString() || '',

            to_account_id: payment.to_account?.id?.toString() || '',
            amount: payment.amount?.toString() || '',
            payment_type: displayPaymentType,
            remarks: payment.remarks || '',
        });
    };

    const handleDelete = (payment: OfficePayment) => {
        setDeletingPayment(payment);
    };

    const confirmDelete = () => {
        if (deletingPayment) {
            router.delete(`/office-payments/${deletingPayment.id}`, {
                onSuccess: () => setDeletingPayment(null),
            });
        }
    };

    const handleBulkDelete = () => {
        setIsBulkDeleting(true);
    };

    const confirmBulkDelete = () => {
        router.delete('/office-payments/bulk/delete', {
            data: { ids: selectedPayments },
            onSuccess: () => {
                setSelectedPayments([]);
                setIsBulkDeleting(false);
            },
        });
    };

    const applyFilters = () => {
        router.get(
            '/office-payments',
            {
                search: search || undefined,
                start_date: startDate || undefined,
                end_date: endDate || undefined,
                type: selectedType || undefined,
                shift_id: selectedShift || undefined,
                sort_by: sortBy,
                sort_order: sortOrder,
                per_page: perPage,
            },
            { preserveState: true },
        );
    };

    const clearFilters = () => {
        setSearch('');
        setStartDate('');
        setEndDate('');
        setSelectedType('');
        setSelectedShift('');
        router.get(
            '/office-payments',
            { sort_by: sortBy, sort_order: sortOrder, per_page: perPage },
            { preserveState: true },
        );
    };

    const handleSort = (column: string) => {
        const newOrder =
            sortBy === column && sortOrder === 'asc' ? 'desc' : 'asc';
        setSortBy(column);
        setSortOrder(newOrder);
        router.get(
            '/office-payments',
            {
                search: search || undefined,
                start_date: startDate || undefined,
                end_date: endDate || undefined,
                type: selectedType || undefined,
                shift_id: selectedShift || undefined,
                sort_by: column,
                sort_order: newOrder,
                per_page: perPage,
            },
            { preserveState: true },
        );
    };

    const handlePageChange = (page: number) => {
        router.get(
            '/office-payments',
            {
                search: search || undefined,
                start_date: startDate || undefined,
                end_date: endDate || undefined,
                type: selectedType || undefined,
                shift_id: selectedShift || undefined,
                sort_by: sortBy,
                sort_order: sortOrder,
                per_page: perPage,
                page,
            },
            { preserveState: true },
        );
    };

    const toggleSelectAll = () => {
        if (selectedPayments.length === (officePayments?.data?.length || 0)) {
            setSelectedPayments([]);
        } else {
            setSelectedPayments(
                officePayments?.data?.map((payment) => payment.id) || [],
            );
        }
    };

    const toggleSelectPayment = (paymentId: number) => {
        if (selectedPayments.includes(paymentId)) {
            setSelectedPayments(
                selectedPayments.filter((id) => id !== paymentId),
            );
        } else {
            setSelectedPayments([...selectedPayments, paymentId]);
        }
    };

    useEffect(() => {
        const timer = setTimeout(() => {
            applyFilters();
        }, 500);
        return () => clearTimeout(timer);
    }, [search]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Office Payments" />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold dark:text-white">
                            Office Payments
                        </h1>
                        <p className="text-gray-600 dark:text-gray-400">
                            Manage shift end deposits and office payments
                        </p>
                    </div>
                    <div className="flex gap-2">
                        {selectedPayments.length > 0 && can('delete-office-payment') && (
                            <Button
                                variant="destructive"
                                onClick={handleBulkDelete}
                            >
                                <Trash2 className="mr-2 h-4 w-4" />
                                Delete Selected ({selectedPayments.length})
                            </Button>
                        )}
                        {canDownload && (
                            <Button
                                variant="success"
                                onClick={() => {
                                    const params = new URLSearchParams();
                                    if (search) params.append('search', search);
                                    if (startDate)
                                        params.append('start_date', startDate);
                                    if (endDate) params.append('end_date', endDate);
                                    if (selectedType)
                                        params.append('type', selectedType);
                                    if (selectedShift)
                                        params.append('shift_id', selectedShift);
                                    if (sortBy) params.append('sort_by', sortBy);
                                    if (sortOrder)
                                        params.append('sort_order', sortOrder);
                                    window.location.href = `/office-payments/download-pdf?${params.toString()}`;
                                }}
                            >
                                <FileText className="mr-2 h-4 w-4" />
                                Download
                            </Button>
                        )}
                        {can('create-office-payment') && (
                            <Button onClick={() => setIsCreateOpen(true)}>
                                <Plus className="mr-2 h-4 w-4" />
                                Add Office Payment
                            </Button>
                        )}
                    </div>
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
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-6">
                            <div>
                                <Label className="dark:text-gray-200">
                                    Search
                                </Label>
                                <Input
                                    placeholder="Search payments..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                />
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
                            <div>
                                <Label className="dark:text-gray-200">
                                    Type
                                </Label>
                                <Select
                                    value={selectedType || 'all'}
                                    onValueChange={(value) =>
                                        setSelectedType(
                                            value === 'all' ? '' : value,
                                        )
                                    }
                                >
                                    <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                        <SelectValue placeholder="All Types" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            All Types
                                        </SelectItem>
                                        {types.map((type) => (
                                            <SelectItem
                                                key={type.value}
                                                value={type.value}
                                            >
                                                {type.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <Label className="dark:text-gray-200">
                                    Shift
                                </Label>
                                <Select
                                    value={selectedShift || 'all'}
                                    onValueChange={(value) =>
                                        setSelectedShift(
                                            value === 'all' ? '' : value,
                                        )
                                    }
                                >
                                    <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                        <SelectValue placeholder="All Shifts" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            All Shifts
                                        </SelectItem>
                                        {shifts.map((shift) => (
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
                            <div className="flex items-end gap-2">
                                <Button
                                    onClick={applyFilters}
                                    className="px-4"
                                >
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

                <Card className="dark:border-gray-700 dark:bg-gray-800">
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b dark:border-gray-700">
                                        <th className="p-4 text-left font-medium dark:text-gray-300">
                                            <input
                                                type="checkbox"
                                                checked={
                                                    selectedPayments.length ===
                                                        (officePayments?.data
                                                            ?.length || 0) &&
                                                    (officePayments?.data
                                                        ?.length || 0) > 0
                                                }
                                                onChange={toggleSelectAll}
                                                className="rounded border-gray-300 dark:border-gray-600"
                                            />
                                        </th>
                                        <th
                                            className="cursor-pointer p-4 text-left text-[13px] font-medium dark:text-gray-300"
                                            onClick={() => handleSort('date')}
                                        >
                                            <div className="flex items-center gap-1">
                                                Date
                                                {sortBy === 'date' &&
                                                    (sortOrder === 'asc' ? (
                                                        <ChevronUp className="h-4 w-4" />
                                                    ) : (
                                                        <ChevronDown className="h-4 w-4" />
                                                    ))}
                                            </div>
                                        </th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                            Shift
                                        </th>

                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                            To Account
                                        </th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                            Amount
                                        </th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                            Remarks
                                        </th>
                                        {hasActionPermission && (
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                            Actions
                                        </th>
                                        )}
                                    </tr>
                                </thead>
                                <tbody>
                                    {officePayments?.data?.length > 0 ? (
                                        officePayments.data.map((payment) => (
                                            <tr
                                                key={payment.id}
                                                className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700"
                                            >
                                                <td className="p-4">
                                                    <input
                                                        type="checkbox"
                                                        checked={selectedPayments.includes(
                                                            payment.id,
                                                        )}
                                                        onChange={() =>
                                                            toggleSelectPayment(
                                                                payment.id,
                                                            )
                                                        }
                                                        className="rounded border-gray-300 dark:border-gray-600"
                                                    />
                                                </td>
                                                <td className="p-4 text-[13px] dark:text-white">
                                                    {payment.date
                                                        ? new Date(
                                                              payment.date,
                                                          ).toLocaleDateString(
                                                              'en-GB',
                                                          )
                                                        : 'N/A'}
                                                </td>
                                                <td className="p-4 text-[13px] dark:text-gray-300">
                                                    {payment.shift?.name ||
                                                        'N/A'}
                                                </td>

                                                <td className="p-4 text-[13px] dark:text-gray-300">
                                                    {payment.to_account?.name ||
                                                        'N/A'}
                                                </td>
                                                <td className="p-4 text-[13px] dark:text-gray-300">
                                                    {payment.amount?.toLocaleString() ||
                                                        '0'}
                                                </td>
                                                <td className="p-4 text-[13px] dark:text-gray-300">
                                                    {payment.remarks || 'N/A'}
                                                </td>
                                                {hasActionPermission && (
                                                <td className="p-4">
                                                    <div className="flex gap-2">
                                                        {can('update-office-payment') && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() =>
                                                                handleEdit(
                                                                    payment,
                                                                )
                                                            }
                                                            className="text-indigo-600 hover:text-indigo-800"
                                                        >
                                                            <Edit className="h-4 w-4" />
                                                        </Button>
                                                        )}
                                                        {can('delete-office-payment') && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() =>
                                                                handleDelete(
                                                                    payment,
                                                                )
                                                            }
                                                            className="text-red-600 hover:text-red-800"
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
                                                colSpan={hasActionPermission ? 7 : 6}
                                                className="p-8 text-center text-gray-500 dark:text-gray-400"
                                            >
                                                <Building className="mx-auto mb-4 h-12 w-12 text-gray-400" />
                                                No office payments found
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <Pagination
                            currentPage={officePayments.current_page}
                            lastPage={officePayments.last_page}
                            from={officePayments.from}
                            to={officePayments.to}
                            total={officePayments.total}
                            perPage={perPage}
                            onPageChange={handlePageChange}
                            onPerPageChange={(newPerPage) => {
                                setPerPage(newPerPage);
                                applyFilters();
                            }}
                        />
                    </CardContent>
                </Card>

                <FormModal
                    isOpen={isCreateOpen}
                    onClose={() => setIsCreateOpen(false)}
                    title="Create Office Payment"
                    onSubmit={handleSubmit}
                    processing={processing}
                    submitText="Create"
                    className="max-w-lg"
                >
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <Label
                                htmlFor="date"
                                className="dark:text-gray-200"
                            >
                                Date
                            </Label>
                            <Input
                                id="date"
                                type="date"
                                value={data.date}
                                onChange={(e) => {
                                    setData('date', e.target.value);
                                    setAvailableShifts(
                                        getAvailableShifts(e.target.value),
                                    );
                                    setData('shift_id', '');
                                }}
                                className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            />
                            {errors.date && (
                                <span className="text-sm text-red-500">
                                    {errors.date}
                                </span>
                            )}
                        </div>
                        <div>
                            <Label
                                htmlFor="shift_id"
                                className="dark:text-gray-200"
                            >
                                Shift
                            </Label>
                            <Select
                                value={data.shift_id}
                                onValueChange={(value) =>
                                    setData('shift_id', value)
                                }
                                disabled={!data.date}
                            >
                                <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                    <SelectValue placeholder="Select shift" />
                                </SelectTrigger>
                                <SelectContent>
                                    {availableShifts?.map((shift) => (
                                        <SelectItem
                                            key={shift.id}
                                            value={shift.id.toString()}
                                        >
                                            {shift.name}
                                        </SelectItem>
                                    )) || []}
                                </SelectContent>
                            </Select>
                            {errors.shift_id && (
                                <span className="text-sm text-red-500">
                                    {errors.shift_id}
                                </span>
                            )}
                        </div>
                    </div>

                    <div>
                        <Label
                            htmlFor="payment_type"
                            className="dark:text-gray-200"
                        >
                            Payment Type
                        </Label>
                        <Select
                            value={data.payment_type}
                            onValueChange={(value) => {
                                setData('payment_type', value);
                                setData('to_account_id', ''); // Reset account when payment type changes
                            }}
                        >
                            <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                <SelectValue placeholder="Select payment type" />
                            </SelectTrigger>
                            <SelectContent>
                                {paymentTypes?.map((paymentType) => (
                                    <SelectItem
                                        key={paymentType.code}
                                        value={paymentType.type}
                                    >
                                        {paymentType.type}
                                    </SelectItem>
                                )) || []}
                            </SelectContent>
                        </Select>
                        {errors.payment_type && (
                            <span className="text-sm text-red-500">
                                {errors.payment_type}
                            </span>
                        )}
                    </div>

                    <div>
                        <Label
                            htmlFor="to_account_id"
                            className="dark:text-gray-200"
                        >
                            To Account (Office)
                        </Label>
                        <Select
                            value={data.to_account_id}
                            onValueChange={(value) =>
                                setData('to_account_id', value)
                            }
                        >
                            <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                <SelectValue placeholder="Select office account" />
                            </SelectTrigger>
                            <SelectContent>
                                {getFilteredAccounts().map((account) => (
                                    <SelectItem
                                        key={account.id}
                                        value={account.id.toString()}
                                    >
                                        {account.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.to_account_id && (
                            <span className="text-sm text-red-500">
                                {errors.to_account_id}
                            </span>
                        )}
                    </div>

                    <div>
                        <Label htmlFor="amount" className="dark:text-gray-200">
                            Amount
                        </Label>
                        <Input
                            id="amount"
                            type="number"
                            step="0.01"
                            value={data.amount}
                            onChange={(e) => setData('amount', e.target.value)}
                            className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                        {errors.amount && (
                            <span className="text-sm text-red-500">
                                {errors.amount}
                            </span>
                        )}
                    </div>

                    <div>
                        <Label htmlFor="remarks" className="dark:text-gray-200">
                            Remarks
                        </Label>
                        <Input
                            id="remarks"
                            value={data.remarks}
                            onChange={(e) => setData('remarks', e.target.value)}
                            className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                    </div>
                </FormModal>

                <FormModal
                    isOpen={!!editingPayment}
                    onClose={() => setEditingPayment(null)}
                    title="Update Office Payment"
                    onSubmit={handleSubmit}
                    processing={processing}
                    submitText="Update"
                    className="max-w-lg"
                >
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <Label
                                htmlFor="date"
                                className="dark:text-gray-200"
                            >
                                Date
                            </Label>
                            <Input
                                id="date"
                                type="date"
                                value={data.date}
                                onChange={(e) => {
                                    setData('date', e.target.value);
                                    setAvailableShifts(
                                        getAvailableShifts(e.target.value),
                                    );
                                    setData('shift_id', '');
                                }}
                                className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            />
                            {errors.date && (
                                <span className="text-sm text-red-500">
                                    {errors.date}
                                </span>
                            )}
                        </div>
                        <div>
                            <Label
                                htmlFor="shift_id"
                                className="dark:text-gray-200"
                            >
                                Shift
                            </Label>
                            <Select
                                value={data.shift_id}
                                onValueChange={(value) =>
                                    setData('shift_id', value)
                                }
                                disabled={!data.date}
                            >
                                <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                    <SelectValue placeholder="Select shift" />
                                </SelectTrigger>
                                <SelectContent>
                                    {availableShifts?.map((shift) => (
                                        <SelectItem
                                            key={shift.id}
                                            value={shift.id.toString()}
                                        >
                                            {shift.name}
                                        </SelectItem>
                                    )) || []}
                                </SelectContent>
                            </Select>
                            {errors.shift_id && (
                                <span className="text-sm text-red-500">
                                    {errors.shift_id}
                                </span>
                            )}
                        </div>
                    </div>

                    <div>
                        <Label
                            htmlFor="payment_type"
                            className="dark:text-gray-200"
                        >
                            Payment Type
                        </Label>
                        <Select
                            value={data.payment_type}
                            onValueChange={(value) => {
                                setData('payment_type', value);
                                setData('to_account_id', '');
                            }}
                        >
                            <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                <SelectValue placeholder="Select payment type" />
                            </SelectTrigger>
                            <SelectContent>
                                {paymentTypes?.map((paymentType) => (
                                    <SelectItem
                                        key={paymentType.code}
                                        value={paymentType.type}
                                    >
                                        {paymentType.type}
                                    </SelectItem>
                                )) || []}
                            </SelectContent>
                        </Select>
                        {errors.payment_type && (
                            <span className="text-sm text-red-500">
                                {errors.payment_type}
                            </span>
                        )}
                    </div>

                    <div>
                        <Label
                            htmlFor="to_account_id"
                            className="dark:text-gray-200"
                        >
                            To Account (Office)
                        </Label>
                        <Select
                            value={data.to_account_id}
                            onValueChange={(value) =>
                                setData('to_account_id', value)
                            }
                        >
                            <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                <SelectValue placeholder="Select office account" />
                            </SelectTrigger>
                            <SelectContent>
                                {getFilteredAccounts().map((account) => (
                                    <SelectItem
                                        key={account.id}
                                        value={account.id.toString()}
                                    >
                                        {account.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.to_account_id && (
                            <span className="text-sm text-red-500">
                                {errors.to_account_id}
                            </span>
                        )}
                    </div>

                    <div>
                        <Label htmlFor="amount" className="dark:text-gray-200">
                            Amount
                        </Label>
                        <Input
                            id="amount"
                            type="number"
                            step="0.01"
                            value={data.amount}
                            onChange={(e) => setData('amount', e.target.value)}
                            className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                        {errors.amount && (
                            <span className="text-sm text-red-500">
                                {errors.amount}
                            </span>
                        )}
                    </div>

                    <div>
                        <Label htmlFor="remarks" className="dark:text-gray-200">
                            Remarks
                        </Label>
                        <Input
                            id="remarks"
                            value={data.remarks}
                            onChange={(e) => setData('remarks', e.target.value)}
                            className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                    </div>
                </FormModal>

                <DeleteModal
                    isOpen={!!deletingPayment}
                    onClose={() => setDeletingPayment(null)}
                    onConfirm={confirmDelete}
                    title="Delete Office Payment"
                    message={`Are you sure you want to delete this office payment? This action cannot be undone.`}
                />

                <DeleteModal
                    isOpen={isBulkDeleting}
                    onClose={() => setIsBulkDeleting(false)}
                    onConfirm={confirmBulkDelete}
                    title="Delete Selected Payments"
                    message={`Are you sure you want to delete ${selectedPayments.length} selected payments? This action cannot be undone.`}
                />
            </div>
        </AppLayout>
    );
}