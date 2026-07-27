import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DeleteModal } from '@/components/ui/delete-modal';
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
    ChevronDown,
    ChevronUp,
    Edit,
    FileText,
    Filter,
    Plus,
    Trash2,
    Receipt,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { usePermission } from '@/hooks/usePermission';
import { PaymentVoucherModal, type PaymentVoucher } from './PaymentVoucherModal';

interface VoucherCategory {
    id: number;
    name: string;
}

interface PaymentSubType {
    id: number;
    name: string;
    voucher_category_id: number;
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

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
    {
        title: 'Payment Voucher',
        href: '/vouchers/payment',
    },
];

interface PaymentVoucherProps {
    vouchers: {
        data: PaymentVoucher[];
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
    closedShifts: Array<{close_date: string; shift_id: number}>;
    voucherCategories: VoucherCategory[];
    paymentSubTypes: PaymentSubType[];
    filters: {
        search?: string;
        payment_method?: string;
        start_date?: string;
        end_date?: string;
        sort_by?: string;
        sort_order?: string;
        per_page?: number;
    };
}

export default function PaymentVoucher({ vouchers, accounts = [], groupedAccounts = {}, shifts = [], closedShifts = [], voucherCategories = [], paymentSubTypes = [], filters }: PaymentVoucherProps) {
    const { can } = usePermission();
    const hasActionPermission = can('update-voucher') || can('delete-voucher');
    const canFilter = can('can-voucher-filter');
    const canDownload = can('can-voucher-download');
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editingVoucher, setEditingVoucher] = useState<PaymentVoucher | null>(null);
    const [deletingVoucher, setDeletingVoucher] = useState<PaymentVoucher | null>(null);
    const [selectedVouchers, setSelectedVouchers] = useState<number[]>([]);
    const [isBulkDeleting, setIsBulkDeleting] = useState(false);
    const [search, setSearch] = useState(filters?.search || '');

    const [paymentMethod, setPaymentMethod] = useState(filters?.payment_method || 'all');
    const [startDate, setStartDate] = useState(filters?.start_date || '');
    const [endDate, setEndDate] = useState(filters?.end_date || '');
    const [sortBy, setSortBy] = useState(filters?.sort_by || 'created_at');
    const [sortOrder, setSortOrder] = useState(filters?.sort_order || 'desc');
    const [perPage, setPerPage] = useState(filters?.per_page || 10);

    const handleEdit = (voucher: PaymentVoucher) => {
        setEditingVoucher(voucher);
    };

    const handleDelete = (voucher: PaymentVoucher) => {
        setDeletingVoucher(voucher);
    };

    const confirmDelete = () => {
        if (deletingVoucher) {
            router.delete(`/vouchers/payment/${deletingVoucher.id}`, {
                onSuccess: () => setDeletingVoucher(null),

            });
        }
    };

    const handleBulkDelete = () => {
        setIsBulkDeleting(true);
    };

    const confirmBulkDelete = () => {
        router.delete('/vouchers/payment/bulk/delete', {
            data: { ids: selectedVouchers },
            onSuccess: () => {
                setSelectedVouchers([]);
                setIsBulkDeleting(false);
            },

        });
    };

    const applyFilters = () => {
        router.get(
            '/vouchers/payment',
            {
                search: search || undefined,
                payment_method: paymentMethod === 'all' ? undefined : paymentMethod,
                start_date: startDate || undefined,
                end_date: endDate || undefined,
                sort_by: sortBy,
                sort_order: sortOrder,
                per_page: perPage,
            },
            { preserveState: true },
        );
    };

    const clearFilters = () => {
        setSearch('');
        setPaymentMethod('all');
        setStartDate('');
        setEndDate('');
        router.get(
            '/vouchers/payment',
            {
                sort_by: sortBy,
                sort_order: sortOrder,
                per_page: perPage,
            },
            { preserveState: true },
        );
    };

    const handleSort = (column: string) => {
        const newOrder = sortBy === column && sortOrder === 'asc' ? 'desc' : 'asc';
        setSortBy(column);
        setSortOrder(newOrder);
        router.get(
            '/vouchers/payment',
            {
                search: search || undefined,
                payment_method: paymentMethod === 'all' ? undefined : paymentMethod,
                start_date: startDate || undefined,
                end_date: endDate || undefined,
                sort_by: column,
                sort_order: newOrder,
                per_page: perPage,
            },
            { preserveState: true },
        );
    };

    const handlePageChange = (page: number) => {
        router.get(
            '/vouchers/payment',
            {
                search: search || undefined,
                payment_method: paymentMethod === 'all' ? undefined : paymentMethod,
                start_date: startDate || undefined,
                end_date: endDate || undefined,
                sort_by: sortBy,
                sort_order: sortOrder,
                per_page: perPage,
                page,
            },
            { preserveState: true },
        );
    };

    const toggleSelectAll = () => {
        if (selectedVouchers.length === vouchers.data.length) {
            setSelectedVouchers([]);
        } else {
            setSelectedVouchers(vouchers.data.map((voucher) => voucher.id));
        }
    };

    const toggleSelectVoucher = (voucherId: number) => {
        if (selectedVouchers.includes(voucherId)) {
            setSelectedVouchers(selectedVouchers.filter((id) => id !== voucherId));
        } else {
            setSelectedVouchers([...selectedVouchers, voucherId]);
        }
    };

    useEffect(() => {
        const timer = setTimeout(() => {
            if (search !== (filters?.search || '')) {
                applyFilters();
            }
        }, 500);
        return () => clearTimeout(timer);
    }, [search, filters?.search]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Payment Voucher" />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold dark:text-white">Payment Voucher</h1>
                        <p className="text-gray-600 dark:text-gray-400">Manage payment vouchers and transactions</p>
                    </div>
                    <div className="flex gap-2">
                        {selectedVouchers.length > 0 && can('delete-voucher') && (
                            <Button
                                variant="destructive"
                                onClick={handleBulkDelete}
                            >
                                <Trash2 className="mr-2 h-4 w-4" />
                                Delete Selected ({selectedVouchers.length})
                            </Button>
                        )}
                        {canDownload && (
                            <Button
                                variant="success"
                                onClick={() => {
                                    const params = new URLSearchParams();
                                    if (search) params.append('search', search);
                                    if (paymentMethod !== 'all') params.append('payment_method', paymentMethod);
                                    if (startDate) params.append('start_date', startDate);
                                    if (endDate) params.append('end_date', endDate);
                                    if (sortBy) params.append('sort_by', sortBy);
                                    if (sortOrder) params.append('sort_order', sortOrder);
                                    window.location.href = `/vouchers/payment/download-pdf?${params.toString()}`;
                                }}
                            >
                                <FileText className="mr-2 h-4 w-4" />
                                Download
                            </Button>
                        )}
                        {can('create-voucher') && (
                            <Button onClick={() => setIsCreateOpen(true)}>
                                <Plus className="mr-2 h-4 w-4" />
                                Add Payment Voucher
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
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-5">
                            <div>
                                <Label className="dark:text-gray-200">Search</Label>
                                <Input
                                    placeholder="Search vouchers..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                />
                            </div>

                            <div>
                                <Label className="dark:text-gray-200">Payment Method</Label>
                                <Select
                                    value={paymentMethod}
                                    onValueChange={(value) => {
                                        setPaymentMethod(value);
                                        setTimeout(() => applyFilters(), 100);
                                    }}
                                >
                                    <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                        <SelectValue placeholder="Choose payment method" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All methods</SelectItem>
                                        <SelectItem value="Cash">Cash</SelectItem>
                                        <SelectItem value="Bank">Bank</SelectItem>
                                        <SelectItem value="Mobile Bank">Mobile Bank</SelectItem>
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
                            <div className="flex items-end gap-2">
                                <Button onClick={applyFilters} className="px-4">
                                    Apply Filters
                                </Button>
                                <Button onClick={clearFilters} variant="secondary" className="px-4">
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
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                            <input
                                                type="checkbox"
                                                checked={selectedVouchers.length === vouchers.data.length && vouchers.data.length > 0}
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
                                                {sortBy === 'date' && (sortOrder === 'asc' ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />)}
                                            </div>
                                        </th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Shift</th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">From Account</th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">To Account</th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Amount</th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Category</th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Sub Type</th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Payment Method</th>
                                        {hasActionPermission && (
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Actions</th>
                                        )}
                                    </tr>
                                </thead>
                                <tbody>
                                    {vouchers.data.length > 0 ? (
                                        vouchers.data.map((voucher) => (
                                            <tr key={voucher.id} className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">
                                                <td className="p-4">
                                                    <input
                                                        type="checkbox"
                                                        checked={selectedVouchers.includes(voucher.id)}
                                                        onChange={() => toggleSelectVoucher(voucher.id)}
                                                        className="rounded border-gray-300 dark:border-gray-600"
                                                    />
                                                </td>
                                                <td className="p-4 text-[13px] dark:text-white">{new Date(voucher.date).toLocaleDateString()}</td>
                                                <td className="p-4 text-[13px] dark:text-gray-300">{voucher.shift?.name || 'N/A'}</td>
                                                <td className="p-4 text-[13px] dark:text-gray-300">{voucher.from_account?.name || 'N/A'}</td>
                                                <td className="p-4 text-[13px] dark:text-gray-300">{voucher.to_account?.name || 'N/A'}</td>
                                                <td className="p-4 text-[13px] dark:text-gray-300">{voucher.transaction?.amount ? Number(voucher.transaction.amount).toLocaleString() : '0'}</td>
                                                <td className="p-4 text-[13px] dark:text-gray-300">{voucher.voucher_category?.name || 'N/A'}</td>
                                                <td className="p-4 text-[13px] dark:text-gray-300">{voucher.payment_sub_type?.name || 'N/A'}</td>
                                                <td className="p-4">
                                                    <span className="rounded bg-blue-100 px-2 py-1 text-xs text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                                        {voucher.transaction?.payment_type || 'N/A'}
                                                    </span>
                                                </td>
                                                {hasActionPermission && (
                                                    <td className="p-4">
                                                        <div className="flex gap-2">
                                                            {can('update-voucher') && (
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={() => handleEdit(voucher)}
                                                                    className="text-indigo-600 hover:text-indigo-800"
                                                                >
                                                                    <Edit className="h-4 w-4" />
                                                                </Button>
                                                            )}
                                                            {can('delete-voucher') && (
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={() => handleDelete(voucher)}
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
                                            <td colSpan={hasActionPermission ? 10 : 9} className="p-8 text-center text-gray-500 dark:text-gray-400">
                                                <Receipt className="mx-auto mb-4 h-12 w-12 text-gray-400" />
                                                No payment vouchers found
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <Pagination
                            currentPage={vouchers.current_page}
                            lastPage={vouchers.last_page}
                            from={vouchers.from}
                            to={vouchers.to}
                            total={vouchers.total}
                            perPage={perPage}
                            onPageChange={handlePageChange}
                            onPerPageChange={(newPerPage) => {
                                setPerPage(newPerPage);
                                setTimeout(() => applyFilters(), 100);
                            }}
                        />
                    </CardContent>
                </Card>

                <PaymentVoucherModal
                    isOpen={isCreateOpen || !!editingVoucher}
                    onClose={() => {
                        setIsCreateOpen(false);
                        setEditingVoucher(null);
                    }}
                    editingVoucher={editingVoucher}
                    accounts={accounts}
                    groupedAccounts={groupedAccounts}
                    shifts={shifts}
                    closedShifts={closedShifts}
                    voucherCategories={voucherCategories}
                    paymentSubTypes={paymentSubTypes}
                />

                <DeleteModal
                    isOpen={!!deletingVoucher}
                    onClose={() => setDeletingVoucher(null)}
                    onConfirm={confirmDelete}
                    title="Delete Payment Voucher"
                    message="Are you sure you want to delete this payment voucher? This action cannot be undone."
                />

                <DeleteModal
                    isOpen={isBulkDeleting}
                    onClose={() => setIsBulkDeleting(false)}
                    onConfirm={confirmBulkDelete}
                    title="Delete Selected Vouchers"
                    message={`Are you sure you want to delete ${selectedVouchers.length} selected vouchers? This action cannot be undone.`}
                />
            </div>
        </AppLayout>
    );
}
