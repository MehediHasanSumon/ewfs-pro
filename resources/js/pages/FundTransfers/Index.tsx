import { openPdfViewer } from '@/components/documents/pdf-viewer';
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
    Eye,
    FileText,
    Filter,
    Plus,
    Receipt,
    Trash2,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { usePermission } from '@/hooks/usePermission';
import {
    FundTransferDetailsModal,
    type FundTransferDetail,
} from './FundTransferDetailsModal';
import { FundTransferModal } from './FundTransferModal';

interface Account {
    id: number;
    name: string;
    ac_number: string;
    group_id?: number;
    group?: { id: number; name: string; code?: string };
}

interface FundTransfersProps {
    transfers: {
        data: FundTransferDetail[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
    };
    paymentAccounts: Account[];
    expenseAccounts: Account[];
    filters: {
        search?: string;
        from_account_id?: string;
        to_account_id?: string;
        start_date?: string;
        end_date?: string;
        status?: string;
        sort_by?: string;
        sort_order?: string;
        per_page?: number;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
    {
        title: 'Fund Transfer',
        href: '/fund-transfers',
    },
];

export default function FundTransfers({
    transfers = {
        data: [],
        current_page: 1,
        last_page: 1,
        per_page: 10,
        total: 0,
        from: 0,
        to: 0,
    },
    paymentAccounts = [],
    expenseAccounts = [],
    filters = {},
}: FundTransfersProps) {
    const { can } = usePermission();
    const hasActionPermission = can('view-account');
    const canFilter = can('can-account-filter') || true;
    const canDownload = can('can-account-download') || true;

    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editingTransfer, setEditingTransfer] = useState<FundTransferDetail | null>(null);
    const [viewingTransfer, setViewingTransfer] = useState<FundTransferDetail | null>(null);
    const [deletingTransfer, setDeletingTransfer] = useState<FundTransferDetail | null>(null);
    const [selectedTransfers, setSelectedTransfers] = useState<number[]>([]);
    const [isBulkDeleting, setIsBulkDeleting] = useState(false);

    const [search, setSearch] = useState(filters?.search || '');
    const [fromAccount, setFromAccount] = useState(filters?.from_account_id || 'all');
    const [toAccount, setToAccount] = useState(filters?.to_account_id || 'all');
    const [startDate, setStartDate] = useState(filters?.start_date || '');
    const [endDate, setEndDate] = useState(filters?.end_date || '');
    const [statusFilter, setStatusFilter] = useState(filters?.status || 'all');
    const [sortBy, setSortBy] = useState(filters?.sort_by || 'transfer_date');
    const [sortOrder, setSortOrder] = useState(filters?.sort_order || 'desc');
    const [perPage, setPerPage] = useState(filters?.per_page || 10);

    const handleEdit = (transfer: FundTransferDetail) => {
        setEditingTransfer(transfer);
    };

    const handleDelete = (transfer: FundTransferDetail) => {
        setDeletingTransfer(transfer);
    };

    const confirmDelete = () => {
        if (deletingTransfer) {
            router.delete(`/fund-transfers/${deletingTransfer.id}`, {
                onSuccess: () => setDeletingTransfer(null),
            });
        }
    };

    const handleBulkDelete = () => {
        setIsBulkDeleting(true);
    };

    const confirmBulkDelete = () => {
        router.delete('/fund-transfers/bulk/delete', {
            data: { ids: selectedTransfers },
            onSuccess: () => {
                setSelectedTransfers([]);
                setIsBulkDeleting(false);
            },
        });
    };

    const applyFilters = () => {
        router.get(
            '/fund-transfers',
            {
                search: search || undefined,
                from_account_id: fromAccount === 'all' ? undefined : fromAccount,
                to_account_id: toAccount === 'all' ? undefined : toAccount,
                start_date: startDate || undefined,
                end_date: endDate || undefined,
                status: statusFilter === 'all' ? undefined : statusFilter,
                sort_by: sortBy,
                sort_order: sortOrder,
                per_page: perPage,
            },
            { preserveState: true },
        );
    };

    const clearFilters = () => {
        setSearch('');
        setFromAccount('all');
        setToAccount('all');
        setStartDate('');
        setEndDate('');
        setStatusFilter('all');
        router.get(
            '/fund-transfers',
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
            '/fund-transfers',
            {
                search: search || undefined,
                from_account_id: fromAccount === 'all' ? undefined : fromAccount,
                to_account_id: toAccount === 'all' ? undefined : toAccount,
                start_date: startDate || undefined,
                end_date: endDate || undefined,
                status: statusFilter === 'all' ? undefined : statusFilter,
                sort_by: column,
                sort_order: newOrder,
                per_page: perPage,
            },
            { preserveState: true },
        );
    };

    const handlePageChange = (page: number) => {
        router.get(
            '/fund-transfers',
            {
                search: search || undefined,
                from_account_id: fromAccount === 'all' ? undefined : fromAccount,
                to_account_id: toAccount === 'all' ? undefined : toAccount,
                start_date: startDate || undefined,
                end_date: endDate || undefined,
                status: statusFilter === 'all' ? undefined : statusFilter,
                sort_by: sortBy,
                sort_order: sortOrder,
                per_page: perPage,
                page,
            },
            { preserveState: true },
        );
    };

    const toggleSelectAll = () => {
        if (selectedTransfers.length === transfers.data.length) {
            setSelectedTransfers([]);
        } else {
            setSelectedTransfers(transfers.data.map((t) => t.id));
        }
    };

    const toggleSelectTransfer = (id: number) => {
        if (selectedTransfers.includes(id)) {
            setSelectedTransfers(selectedTransfers.filter((item) => item !== id));
        } else {
            setSelectedTransfers([...selectedTransfers, id]);
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
            <Head title="Fund Transfer" />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold dark:text-white">Fund Transfer</h1>
                        <p className="text-gray-600 dark:text-gray-400">Manage internal cash and bank fund transfers</p>
                    </div>
                    <div className="flex gap-2">
                        {selectedTransfers.length > 0 && hasActionPermission && (
                            <Button
                                variant="destructive"
                                onClick={handleBulkDelete}
                            >
                                <Trash2 className="mr-2 h-4 w-4" />
                                Delete Selected ({selectedTransfers.length})
                            </Button>
                        )}
                        {canDownload && (
                            <Button
                                variant="success"
                                onClick={() => {
                                    const params = new URLSearchParams();
                                    if (search) params.append('search', search);
                                    if (fromAccount !== 'all') params.append('from_account_id', fromAccount);
                                    if (toAccount !== 'all') params.append('to_account_id', toAccount);
                                    if (startDate) params.append('start_date', startDate);
                                    if (endDate) params.append('end_date', endDate);
                                    if (statusFilter !== 'all') params.append('status', statusFilter);
                                    if (sortBy) params.append('sort_by', sortBy);
                                    if (sortOrder) params.append('sort_order', sortOrder);
                                    openPdfViewer(`/fund-transfers/download-pdf?${params.toString()}`);
                                }}
                            >
                                <FileText className="mr-2 h-4 w-4" />
                                Download
                            </Button>
                        )}
                        {hasActionPermission && (
                            <Button onClick={() => setIsCreateOpen(true)}>
                                <Plus className="mr-2 h-4 w-4" />
                                Add Fund Transfer
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
                                    <Label className="dark:text-gray-200">Search</Label>
                                    <Input
                                        placeholder="Search transfers..."
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    />
                                </div>

                                <div>
                                    <Label className="dark:text-gray-200">From Account</Label>
                                    <Select
                                        value={fromAccount}
                                        onValueChange={(value) => {
                                            setFromAccount(value);
                                            setTimeout(() => applyFilters(), 100);
                                        }}
                                    >
                                        <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                            <SelectValue placeholder="All Source Accounts" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">All Source Accounts</SelectItem>
                                            {paymentAccounts.map((acc) => (
                                                <SelectItem key={acc.id} value={acc.id.toString()}>
                                                    {acc.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div>
                                    <Label className="dark:text-gray-200">To Account</Label>
                                    <Select
                                        value={toAccount}
                                        onValueChange={(value) => {
                                            setToAccount(value);
                                            setTimeout(() => applyFilters(), 100);
                                        }}
                                    >
                                        <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                            <SelectValue placeholder="All Dest Accounts" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">All Dest Accounts</SelectItem>
                                            {paymentAccounts.map((acc) => (
                                                <SelectItem key={acc.id} value={acc.id.toString()}>
                                                    {acc.name}
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

                                <div>
                                    <Label className="dark:text-gray-200">Status</Label>
                                    <Select
                                        value={statusFilter}
                                        onValueChange={(value) => {
                                            setStatusFilter(value);
                                            setTimeout(() => applyFilters(), 100);
                                        }}
                                    >
                                        <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                            <SelectValue placeholder="All Status" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">All Status</SelectItem>
                                            <SelectItem value="posted">Posted</SelectItem>
                                            <SelectItem value="draft">Draft</SelectItem>
                                            <SelectItem value="cancelled">Cancelled</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>

                            <div className="mt-4 flex justify-end gap-2">
                                <Button onClick={applyFilters} className="px-4">
                                    Apply Filters
                                </Button>
                                <Button onClick={clearFilters} variant="secondary" className="px-4">
                                    <X className="mr-2 h-4 w-4" />
                                    Clear
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Table Card */}
                <Card className="dark:border-gray-700 dark:bg-gray-800">
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b dark:border-gray-700">
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                            <input
                                                type="checkbox"
                                                checked={selectedTransfers.length === transfers.data.length && transfers.data.length > 0}
                                                onChange={toggleSelectAll}
                                                className="rounded border-gray-300 dark:border-gray-600"
                                            />
                                        </th>
                                        <th
                                            className="cursor-pointer p-4 text-left text-[13px] font-medium dark:text-gray-300"
                                            onClick={() => handleSort('transfer_date')}
                                        >
                                            <div className="flex items-center gap-1">
                                                Date
                                                {sortBy === 'transfer_date' && (sortOrder === 'asc' ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />)}
                                            </div>
                                        </th>
                                        <th
                                            className="cursor-pointer p-4 text-left text-[13px] font-medium dark:text-gray-300"
                                            onClick={() => handleSort('transfer_no')}
                                        >
                                            <div className="flex items-center gap-1">
                                                Transfer No
                                                {sortBy === 'transfer_no' && (sortOrder === 'asc' ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />)}
                                            </div>
                                        </th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">From Account</th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">To Account</th>
                                        <th className="p-4 text-right text-[13px] font-medium dark:text-gray-300">Amount</th>
                                        <th className="p-4 text-right text-[13px] font-medium dark:text-gray-300">Transfer Fee</th>
                                        <th className="p-4 text-right text-[13px] font-medium dark:text-gray-300">Total Out</th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Reference</th>
                                        <th className="p-4 text-center text-[13px] font-medium dark:text-gray-300">Status</th>
                                        {hasActionPermission && (
                                            <th className="p-4 text-center text-[13px] font-medium dark:text-gray-300">Actions</th>
                                        )}
                                    </tr>
                                </thead>
                                <tbody>
                                    {transfers.data.length > 0 ? (
                                        transfers.data.map((transfer) => {
                                            const amount = Number(transfer.amount) || 0;
                                            const fee = Number(transfer.transfer_fee) || 0;
                                            const total = amount + fee;

                                            return (
                                                <tr
                                                    key={transfer.id}
                                                    className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700"
                                                >
                                                    <td className="p-4">
                                                        <input
                                                            type="checkbox"
                                                            checked={selectedTransfers.includes(transfer.id)}
                                                            onChange={() => toggleSelectTransfer(transfer.id)}
                                                            className="rounded border-gray-300 dark:border-gray-600"
                                                        />
                                                    </td>
                                                    <td className="p-4 text-[13px] dark:text-white">
                                                        {new Date(transfer.transfer_date).toLocaleDateString()}
                                                    </td>
                                                    <td className="p-4 text-[13px] font-semibold dark:text-white">
                                                        {transfer.transfer_no}
                                                    </td>
                                                    <td className="p-4 text-[13px] dark:text-gray-300">
                                                        <div>{transfer.from_account?.name || 'N/A'}</div>
                                                        {transfer.from_account?.ac_number && (
                                                            <div className="text-[11px] text-gray-500 dark:text-gray-400">
                                                                {transfer.from_account.ac_number}
                                                            </div>
                                                        )}
                                                    </td>
                                                    <td className="p-4 text-[13px] dark:text-gray-300">
                                                        <div>{transfer.to_account?.name || 'N/A'}</div>
                                                        {transfer.to_account?.ac_number && (
                                                            <div className="text-[11px] text-gray-500 dark:text-gray-400">
                                                                {transfer.to_account.ac_number}
                                                            </div>
                                                        )}
                                                    </td>
                                                    <td className="p-4 text-right text-[13px] font-semibold text-emerald-600 dark:text-emerald-400">
                                                        {amount.toLocaleString(undefined, { minimumFractionDigits: 2 })}
                                                    </td>
                                                    <td className="p-4 text-right text-[13px] dark:text-gray-300">
                                                        {fee > 0 ? fee.toLocaleString(undefined, { minimumFractionDigits: 2 }) : '-'}
                                                    </td>
                                                    <td className="p-4 text-right text-[13px] font-bold text-gray-900 dark:text-white">
                                                        {total.toLocaleString(undefined, { minimumFractionDigits: 2 })}
                                                    </td>
                                                    <td className="p-4 text-[13px] dark:text-gray-300">
                                                        {transfer.reference_no || '-'}
                                                    </td>
                                                    <td className="p-4 text-center">
                                                        {transfer.status === 'posted' && (
                                                            <span className="rounded bg-emerald-100 px-2 py-1 text-xs text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200">
                                                                Posted
                                                            </span>
                                                        )}
                                                        {transfer.status === 'cancelled' && (
                                                            <span className="rounded bg-red-100 px-2 py-1 text-xs text-red-800 dark:bg-red-900 dark:text-red-200">
                                                                Cancelled
                                                            </span>
                                                        )}
                                                        {transfer.status === 'draft' && (
                                                            <span className="rounded bg-yellow-100 px-2 py-1 text-xs text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                                                Draft
                                                            </span>
                                                        )}
                                                    </td>
                                                    {hasActionPermission && (
                                                        <td className="p-4">
                                                            <div className="flex items-center justify-center gap-1">
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={() => setViewingTransfer(transfer)}
                                                                    className="text-blue-600 hover:text-blue-800"
                                                                    title="View Details"
                                                                >
                                                                    <Eye className="h-4 w-4" />
                                                                </Button>
                                                                {transfer.status === 'posted' && (
                                                                    <>
                                                                        <Button
                                                                            variant="ghost"
                                                                            size="sm"
                                                                            onClick={() => handleEdit(transfer)}
                                                                            className="text-indigo-600 hover:text-indigo-800"
                                                                            title="Edit & Re-post"
                                                                        >
                                                                            <Edit className="h-4 w-4" />
                                                                        </Button>
                                                                        <Button
                                                                            variant="ghost"
                                                                            size="sm"
                                                                            onClick={() => handleDelete(transfer)}
                                                                            className="text-red-600 hover:text-red-800"
                                                                            title="Cancel Transfer"
                                                                        >
                                                                            <Trash2 className="h-4 w-4" />
                                                                        </Button>
                                                                    </>
                                                                )}
                                                            </div>
                                                        </td>
                                                    )}
                                                </tr>
                                            );
                                        })
                                    ) : (
                                        <tr>
                                            <td colSpan={hasActionPermission ? 11 : 10} className="p-8 text-center text-gray-500 dark:text-gray-400">
                                                <Receipt className="mx-auto mb-4 h-12 w-12 text-gray-400" />
                                                No fund transfers found
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <Pagination
                            currentPage={transfers.current_page}
                            lastPage={transfers.last_page}
                            from={transfers.from}
                            to={transfers.to}
                            total={transfers.total}
                            perPage={perPage}
                            onPageChange={handlePageChange}
                            onPerPageChange={(newPerPage) => {
                                setPerPage(newPerPage);
                                setTimeout(() => applyFilters(), 100);
                            }}
                        />
                    </CardContent>
                </Card>

                {/* Create / Edit Modal */}
                <FundTransferModal
                    isOpen={isCreateOpen || !!editingTransfer}
                    onClose={() => {
                        setIsCreateOpen(false);
                        setEditingTransfer(null);
                    }}
                    editingTransfer={editingTransfer}
                    accounts={paymentAccounts}
                    expenseAccounts={expenseAccounts}
                />

                {/* Details View Modal */}
                <FundTransferDetailsModal
                    isOpen={!!viewingTransfer}
                    onClose={() => setViewingTransfer(null)}
                    transfer={viewingTransfer}
                />

                {/* Delete / Cancel Single Modal */}
                <DeleteModal
                    isOpen={!!deletingTransfer}
                    onClose={() => setDeletingTransfer(null)}
                    onConfirm={confirmDelete}
                    title="Cancel Fund Transfer"
                    message={`Are you sure you want to cancel fund transfer "${deletingTransfer?.transfer_no}"? This action will reverse the accounting journal entry and restore account balances.`}
                />

                {/* Bulk Delete Modal */}
                <DeleteModal
                    isOpen={isBulkDeleting}
                    onClose={() => setIsBulkDeleting(false)}
                    onConfirm={confirmBulkDelete}
                    title="Cancel Selected Transfers"
                    message={`Are you sure you want to cancel ${selectedTransfers.length} selected fund transfers? This will reverse their respective accounting journal entries.`}
                />
            </div>
        </AppLayout>
    );
}
