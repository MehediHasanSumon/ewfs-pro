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
    ChevronDown,
    ChevronUp,
    Edit,
    FileText,
    Filter,
    Plus,
    Trash2,
    Settings,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { usePermission } from '@/hooks/usePermission';

interface PaymentSubType {
    id: number;
    code: string;
    name: string;
    voucher_category: string;
    voucher_category_id: number;
    type: string;
    status: boolean;
    created_at: string;
}

interface VoucherCategory {
    id: number;
    name: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
    {
        title: 'Payment Sub Types',
        href: '/payment-sub-types',
    },
];

interface PaymentSubTypeProps {
    paymentSubTypes: {
        data: PaymentSubType[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
    };
    voucherCategories: VoucherCategory[];
    filters: {
        search?: string;
        category?: string;
        type?: string;
        status?: string;
        sort_by?: string;
        sort_order?: string;
        per_page?: number;
    };
}

export default function PaymentSubType({ paymentSubTypes, voucherCategories = [], filters }: PaymentSubTypeProps) {
    const { can } = usePermission();
    const hasActionPermission = can('update-payment-sub-type') || can('delete-payment-sub-type');
    const canFilter = can('can-payment-sub-type-filter');
    const canDownload = can('can-payment-sub-type-download');
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editingPaymentSubType, setEditingPaymentSubType] = useState<PaymentSubType | null>(null);
    const [deletingPaymentSubType, setDeletingPaymentSubType] = useState<PaymentSubType | null>(null);
    const [selectedPaymentSubTypes, setSelectedPaymentSubTypes] = useState<number[]>([]);
    const [isBulkDeleting, setIsBulkDeleting] = useState(false);
    const [search, setSearch] = useState(filters?.search || '');
    const [category, setCategory] = useState(filters?.category || 'all');
    const [type, setType] = useState(filters?.type || 'all');
    const [status, setStatus] = useState(filters?.status || 'all');
    const [sortBy, setSortBy] = useState(filters?.sort_by || 'created_at');
    const [sortOrder, setSortOrder] = useState(filters?.sort_order || 'desc');
    const [perPage, setPerPage] = useState(filters?.per_page || 10);

    const { data, setData, post, put, processing, errors, reset } = useForm({
        code: '',
        name: '',
        voucher_category_id: '',
        type: 'payment',
        status: true,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingPaymentSubType) {
            put(`/payment-sub-types/${editingPaymentSubType.id}`, {
                onSuccess: () => {
                    setEditingPaymentSubType(null);
                    reset();
                },
            });
        } else {
            post('/payment-sub-types', {
                onSuccess: () => {
                    setIsCreateOpen(false);
                    reset();
                },
            });
        }
    };

    const handleEdit = async (paymentSubType: PaymentSubType) => {
        setEditingPaymentSubType(paymentSubType);
        setData({
            code: paymentSubType.code,
            name: paymentSubType.name,
            voucher_category_id: paymentSubType.voucher_category_id.toString(),
            type: paymentSubType.type,
            status: paymentSubType.status,
        });
    };

    const handleDelete = (paymentSubType: PaymentSubType) => {
        setDeletingPaymentSubType(paymentSubType);
    };

    const confirmDelete = () => {
        if (deletingPaymentSubType) {
            router.delete(`/payment-sub-types/${deletingPaymentSubType.id}`, {
                onSuccess: () => setDeletingPaymentSubType(null),
            });
        }
    };

    const handleBulkDelete = () => {
        setIsBulkDeleting(true);
    };

    const confirmBulkDelete = () => {
        router.delete('/payment-sub-types/bulk/delete', {
            data: { ids: selectedPaymentSubTypes },
            onSuccess: () => {
                setSelectedPaymentSubTypes([]);
                setIsBulkDeleting(false);
            },
        });
    };

    const applyFilters = () => {
        router.get(
            '/payment-sub-types',
            {
                search: search || undefined,
                category: category === 'all' ? undefined : category,
                type: type === 'all' ? undefined : type,
                status: status === 'all' ? undefined : status,
                sort_by: sortBy,
                sort_order: sortOrder,
                per_page: perPage,
            },
            { preserveState: true },
        );
    };

    const clearFilters = () => {
        setSearch('');
        setCategory('all');
        setType('all');
        setStatus('all');
        router.get(
            '/payment-sub-types',
            {
                sort_by: sortBy,
                sort_order: sortOrder,
                per_page: perPage,
            },
            { preserveState: true },
        );
    };

    const handleSort = (column: string) => {
        const newOrder =
            sortBy === column && sortOrder === 'asc' ? 'desc' : 'asc';
        setSortBy(column);
        setSortOrder(newOrder);
        router.get(
            '/payment-sub-types',
            {
                search: search || undefined,
                category: category === 'all' ? undefined : category,
                type: type === 'all' ? undefined : type,
                status: status === 'all' ? undefined : status,
                sort_by: column,
                sort_order: newOrder,
                per_page: perPage,
            },
            { preserveState: true },
        );
    };

    const handlePageChange = (page: number) => {
        router.get(
            '/payment-sub-types',
            {
                search: search || undefined,
                category: category === 'all' ? undefined : category,
                type: type === 'all' ? undefined : type,
                status: status === 'all' ? undefined : status,
                sort_by: sortBy,
                sort_order: sortOrder,
                per_page: perPage,
                page,
            },
            { preserveState: true },
        );
    };

    const toggleSelectAll = () => {
        if (selectedPaymentSubTypes.length === paymentSubTypes.data.length) {
            setSelectedPaymentSubTypes([]);
        } else {
            setSelectedPaymentSubTypes(paymentSubTypes.data.map((item) => item.id));
        }
    };

    const toggleSelectPaymentSubType = (id: number) => {
        if (selectedPaymentSubTypes.includes(id)) {
            setSelectedPaymentSubTypes(selectedPaymentSubTypes.filter((itemId) => itemId !== id));
        } else {
            setSelectedPaymentSubTypes([...selectedPaymentSubTypes, id]);
        }
    };

    useEffect(() => {
        const timer = setTimeout(() => {
            if (search !== (filters?.search || '')) {
                applyFilters();
            }
        }, 500);
        return () => clearTimeout(timer);
    }, [search]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Payment Sub Types" />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold dark:text-white">
                            Payment Sub Types
                        </h1>
                        <p className="text-gray-600 dark:text-gray-400">
                            Manage payment sub types for voucher categories
                        </p>
                    </div>
                    <div className="flex gap-2">
                        {selectedPaymentSubTypes.length > 0 && can('delete-payment-sub-type') && (
                            <Button
                                variant="destructive"
                                onClick={handleBulkDelete}
                            >
                                <Trash2 className="mr-2 h-4 w-4" />
                                Delete Selected ({selectedPaymentSubTypes.length})
                            </Button>
                        )}
                        {canDownload && (
                            <Button
                                variant="success"
                                onClick={() => {
                                    const params = new URLSearchParams();
                                    if (search) params.append('search', search);
                                    if (category !== 'all') params.append('category', category);
                                    if (type !== 'all') params.append('type', type);
                                    if (status !== 'all') params.append('status', status);
                                    if (sortBy) params.append('sort_by', sortBy);
                                    if (sortOrder) params.append('sort_order', sortOrder);
                                    window.location.href = `/payment-sub-types/download-pdf?${params.toString()}`;
                                }}
                            >
                                <FileText className="mr-2 h-4 w-4" />
                                Download
                            </Button>
                        )}
                        {can('create-payment-sub-type') && (
                            <Button onClick={() => setIsCreateOpen(true)}>
                                <Plus className="mr-2 h-4 w-4" />
                                Add Payment Sub Type
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
                                <Label className="dark:text-gray-200">
                                    Search
                                </Label>
                                <Input
                                    placeholder="Search payment sub types..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                />
                            </div>
                            <div>
                                <Label className="dark:text-gray-200">
                                    Category
                                </Label>
                                <Select
                                    value={category}
                                    onValueChange={(value) => {
                                        setCategory(value);
                                        router.get(
                                            '/payment-sub-types',
                                            {
                                                search: search || undefined,
                                                category: value === 'all' ? undefined : value,
                                                type: type === 'all' ? undefined : type,
                                                status: status === 'all' ? undefined : status,
                                                sort_by: sortBy,
                                                sort_order: sortOrder,
                                                per_page: perPage,
                                            },
                                            { preserveState: true },
                                        );
                                    }}
                                >
                                    <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                        <SelectValue placeholder="All categories" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            All categories
                                        </SelectItem>
                                        {voucherCategories.map((cat) => (
                                            <SelectItem
                                                key={cat.id}
                                                value={cat.id.toString()}
                                            >
                                                {cat.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <Label className="dark:text-gray-200">
                                    Type
                                </Label>
                                <Select
                                    value={type}
                                    onValueChange={(value) => {
                                        setType(value);
                                        router.get(
                                            '/payment-sub-types',
                                            {
                                                search: search || undefined,
                                                category: category === 'all' ? undefined : category,
                                                type: value === 'all' ? undefined : value,
                                                status: status === 'all' ? undefined : status,
                                                sort_by: sortBy,
                                                sort_order: sortOrder,
                                                per_page: perPage,
                                            },
                                            { preserveState: true },
                                        );
                                    }}
                                >
                                    <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                        <SelectValue placeholder="All types" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All types</SelectItem>
                                        <SelectItem value="payment">Payment</SelectItem>
                                        <SelectItem value="receipt">Receipt</SelectItem>
                                        <SelectItem value="both">Both</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <Label className="dark:text-gray-200">
                                    Status
                                </Label>
                                <Select
                                    value={status}
                                    onValueChange={(value) => {
                                        setStatus(value);
                                        router.get(
                                            '/payment-sub-types',
                                            {
                                                search: search || undefined,
                                                category: category === 'all' ? undefined : category,
                                                type: type === 'all' ? undefined : type,
                                                status: value === 'all' ? undefined : value,
                                                sort_by: sortBy,
                                                sort_order: sortOrder,
                                                per_page: perPage,
                                            },
                                            { preserveState: true },
                                        );
                                    }}
                                >
                                    <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                        <SelectValue placeholder="All status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All status</SelectItem>
                                        <SelectItem value="1">Active</SelectItem>
                                        <SelectItem value="0">Inactive</SelectItem>
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
                                                    selectedPaymentSubTypes.length ===
                                                        paymentSubTypes.data.length &&
                                                    paymentSubTypes.data.length > 0
                                                }
                                                onChange={toggleSelectAll}
                                                className="rounded border-gray-300 dark:border-gray-600"
                                            />
                                        </th>
                                        <th
                                            className="cursor-pointer p-4 text-left text-[13px] font-medium dark:text-gray-300"
                                            onClick={() => handleSort('code')}
                                        >
                                            <div className="flex items-center gap-1">
                                                Code
                                                {sortBy === 'code' &&
                                                    (sortOrder === 'asc' ? (
                                                        <ChevronUp className="h-4 w-4" />
                                                    ) : (
                                                        <ChevronDown className="h-4 w-4" />
                                                    ))}
                                            </div>
                                        </th>
                                        <th
                                            className="cursor-pointer p-4 text-left text-[13px] font-medium dark:text-gray-300"
                                            onClick={() => handleSort('name')}
                                        >
                                            <div className="flex items-center gap-1">
                                                Name
                                                {sortBy === 'name' &&
                                                    (sortOrder === 'asc' ? (
                                                        <ChevronUp className="h-4 w-4" />
                                                    ) : (
                                                        <ChevronDown className="h-4 w-4" />
                                                    ))}
                                            </div>
                                        </th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                            Category
                                        </th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                            Type
                                        </th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                            Status
                                        </th>
                                        {hasActionPermission && (
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                            Actions
                                        </th>
                                        )}
                                    </tr>
                                </thead>
                                <tbody>
                                    {paymentSubTypes.data.length > 0 ? (
                                        paymentSubTypes.data.map((item) => (
                                            <tr
                                                key={item.id}
                                                className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700"
                                            >
                                                <td className="p-4">
                                                    <input
                                                        type="checkbox"
                                                        checked={selectedPaymentSubTypes.includes(
                                                            item.id,
                                                        )}
                                                        onChange={() =>
                                                            toggleSelectPaymentSubType(
                                                                item.id,
                                                            )
                                                        }
                                                        className="rounded border-gray-300 dark:border-gray-600"
                                                    />
                                                </td>
                                                <td className="p-4 text-[13px] dark:text-white">
                                                    {item.code}
                                                </td>
                                                <td className="p-4 text-[13px] dark:text-white">
                                                    {item.name}
                                                </td>
                                                <td className="p-4 text-[13px] dark:text-gray-300">
                                                    {item.voucher_category}
                                                </td>
                                                <td className="p-4">
                                                    <span className={`px-2 py-1 rounded text-xs capitalize ${
                                                        item.type === 'payment' 
                                                            ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                                            : item.type === 'receipt'
                                                            ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                                            : 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'
                                                    }`}>
                                                        {item.type}
                                                    </span>
                                                </td>
                                                <td className="p-4">
                                                    <span className={`px-2 py-1 rounded text-xs ${
                                                        item.status 
                                                            ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' 
                                                            : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                                    }`}>
                                                        {item.status ? 'Active' : 'Inactive'}
                                                    </span>
                                                </td>
                                                {hasActionPermission && (
                                                <td className="p-4">
                                                    <div className="flex gap-2">
                                                        {can('update-payment-sub-type') && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() =>
                                                                handleEdit(item)
                                                            }
                                                            className="text-indigo-600 hover:text-indigo-800"
                                                        >
                                                            <Edit className="h-4 w-4" />
                                                        </Button>
                                                        )}
                                                        {can('delete-payment-sub-type') && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() =>
                                                                handleDelete(item)
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
                                                <Settings className="mx-auto mb-4 h-12 w-12 text-gray-400" />
                                                No payment sub types found
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <Pagination
                            currentPage={paymentSubTypes.current_page}
                            lastPage={paymentSubTypes.last_page}
                            from={paymentSubTypes.from}
                            to={paymentSubTypes.to}
                            total={paymentSubTypes.total}
                            perPage={perPage}
                            onPageChange={handlePageChange}
                            onPerPageChange={(newPerPage) => {
                                setPerPage(newPerPage);
                                router.get(
                                    '/payment-sub-types',
                                    {
                                        search: search || undefined,
                                        category: category === 'all' ? undefined : category,
                                        type: type === 'all' ? undefined : type,
                                        status: status === 'all' ? undefined : status,
                                        sort_by: sortBy,
                                        sort_order: sortOrder,
                                        per_page: newPerPage,
                                    },
                                    { preserveState: true },
                                );
                            }}
                        />
                    </CardContent>
                </Card>

                <FormModal
                    isOpen={isCreateOpen}
                    onClose={() => setIsCreateOpen(false)}
                    title="Create Payment Sub Type"
                    onSubmit={handleSubmit}
                    processing={processing}
                    submitText="Create"
                >
                    <div>
                        <Label htmlFor="code" className="dark:text-gray-200">
                            Code
                        </Label>
                        <Input
                            id="code"
                            value={data.code}
                            onChange={(e) => setData('code', e.target.value)}
                            className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                        {errors.code && (
                            <span className="text-sm text-red-500">
                                {errors.code}
                            </span>
                        )}
                    </div>
                    <div>
                        <Label htmlFor="name" className="dark:text-gray-200">
                            Name
                        </Label>
                        <Input
                            id="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                        {errors.name && (
                            <span className="text-sm text-red-500">
                                {errors.name}
                            </span>
                        )}
                    </div>
                    <div>
                        <Label htmlFor="voucher_category_id" className="dark:text-gray-200">
                            Voucher Category
                        </Label>
                        <Select
                            value={data.voucher_category_id}
                            onValueChange={(value) => setData('voucher_category_id', value)}
                        >
                            <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                <SelectValue placeholder="Select category" />
                            </SelectTrigger>
                            <SelectContent>
                                {voucherCategories.map((cat) => (
                                    <SelectItem key={cat.id} value={cat.id.toString()}>
                                        {cat.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.voucher_category_id && (
                            <span className="text-sm text-red-500">
                                {errors.voucher_category_id}
                            </span>
                        )}
                    </div>
                    <div>
                        <Label htmlFor="type" className="dark:text-gray-200">
                            Type
                        </Label>
                        <Select
                            value={data.type}
                            onValueChange={(value) => setData('type', value)}
                        >
                            <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                <SelectValue placeholder="Select type" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="payment">Payment</SelectItem>
                                <SelectItem value="receipt">Receipt</SelectItem>
                                <SelectItem value="both">Both</SelectItem>
                            </SelectContent>
                        </Select>
                        {errors.type && (
                            <span className="text-sm text-red-500">
                                {errors.type}
                            </span>
                        )}
                    </div>
                    <div>
                        <label className="flex items-center space-x-2">
                            <input
                                type="checkbox"
                                checked={data.status}
                                onChange={(e) => setData('status', e.target.checked)}
                                className="rounded border-gray-300 dark:border-gray-600"
                            />
                            <span className="text-sm dark:text-gray-300">Active</span>
                        </label>
                    </div>
                </FormModal>

                <FormModal
                    isOpen={!!editingPaymentSubType}
                    onClose={() => setEditingPaymentSubType(null)}
                    title="Edit Payment Sub Type"
                    onSubmit={handleSubmit}
                    processing={processing}
                    submitText="Update"
                >
                    <div>
                        <Label htmlFor="edit-code" className="dark:text-gray-200">
                            Code
                        </Label>
                        <Input
                            id="edit-code"
                            value={data.code}
                            onChange={(e) => setData('code', e.target.value)}
                            className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                        {errors.code && (
                            <span className="text-sm text-red-500">
                                {errors.code}
                            </span>
                        )}
                    </div>
                    <div>
                        <Label htmlFor="edit-name" className="dark:text-gray-200">
                            Name
                        </Label>
                        <Input
                            id="edit-name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                        {errors.name && (
                            <span className="text-sm text-red-500">
                                {errors.name}
                            </span>
                        )}
                    </div>
                    <div>
                        <Label htmlFor="edit-voucher_category_id" className="dark:text-gray-200">
                            Voucher Category
                        </Label>
                        <Select
                            value={data.voucher_category_id}
                            onValueChange={(value) => setData('voucher_category_id', value)}
                        >
                            <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                <SelectValue placeholder="Select category" />
                            </SelectTrigger>
                            <SelectContent>
                                {voucherCategories.map((cat) => (
                                    <SelectItem key={cat.id} value={cat.id.toString()}>
                                        {cat.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.voucher_category_id && (
                            <span className="text-sm text-red-500">
                                {errors.voucher_category_id}
                            </span>
                        )}
                    </div>
                    <div>
                        <Label htmlFor="edit-type" className="dark:text-gray-200">
                            Type
                        </Label>
                        <Select
                            value={data.type}
                            onValueChange={(value) => setData('type', value)}
                        >
                            <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                <SelectValue placeholder="Select type" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="payment">Payment</SelectItem>
                                <SelectItem value="receipt">Receipt</SelectItem>
                                <SelectItem value="both">Both</SelectItem>
                            </SelectContent>
                        </Select>
                        {errors.type && (
                            <span className="text-sm text-red-500">
                                {errors.type}
                            </span>
                        )}
                    </div>
                    <div>
                        <label className="flex items-center space-x-2">
                            <input
                                type="checkbox"
                                checked={data.status}
                                onChange={(e) => setData('status', e.target.checked)}
                                className="rounded border-gray-300 dark:border-gray-600"
                            />
                            <span className="text-sm dark:text-gray-300">Active</span>
                        </label>
                    </div>
                </FormModal>

                <DeleteModal
                    isOpen={!!deletingPaymentSubType}
                    onClose={() => setDeletingPaymentSubType(null)}
                    onConfirm={confirmDelete}
                    title="Delete Payment Sub Type"
                    message={`Are you sure you want to delete the payment sub type "${deletingPaymentSubType?.name}"? This action cannot be undone.`}
                />

                <DeleteModal
                    isOpen={isBulkDeleting}
                    onClose={() => setIsBulkDeleting(false)}
                    onConfirm={confirmBulkDelete}
                    title="Delete Selected Payment Sub Types"
                    message={`Are you sure you want to delete ${selectedPaymentSubTypes.length} selected payment sub types? This action cannot be undone.`}
                />
            </div>
        </AppLayout>
    );
}