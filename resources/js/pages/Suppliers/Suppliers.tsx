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
    Eye,
    FileText,
    Filter,
    Plus,
    Trash2,
    Users as SuppliersIcon,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { usePermission } from '@/hooks/usePermission';

interface Supplier {
    id: number;
    name: string;
    mobile: string;
    email: string;
    address: string;
    proprietor_name: string;
    group_id?: number;
    group_code?: string;
    account_number?: string;
    total_purchases?: number;
    total_payment?: number;
    total_due?: number;
    status: boolean;
    created_at: string;
}

interface Group {
    id: number;
    code: string;
    name: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
    {
        title: 'Suppliers',
        href: '/suppliers',
    },
];

interface SuppliersProps {
    suppliers: {
        data: Supplier[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
    };
    groups: Group[];
    lastSupplierGroup?: {
        id: number;
        code: string;
    } | null;
    filters: {
        search?: string;
        status?: string;
        sort_by?: string;
        sort_order?: string;
        per_page?: number;
    };
}

export default function Suppliers({ suppliers, groups = [], lastSupplierGroup, filters }: SuppliersProps) {
    const { can } = usePermission();
    const hasActionPermission = can('update-supplier') || can('delete-supplier');
    const canFilter = can('can-supplier-filter');
    const canDownload = can('can-supplier-download');
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editingSupplier, setEditingSupplier] = useState<Supplier | null>(null);
    const [deletingSupplier, setDeletingSupplier] = useState<Supplier | null>(null);
    const [selectedSuppliers, setSelectedSuppliers] = useState<number[]>([]);
    const [isBulkDeleting, setIsBulkDeleting] = useState(false);
    const [search, setSearch] = useState(filters?.search || '');
    const [status, setStatus] = useState(filters?.status || 'all');
    const [sortBy, setSortBy] = useState(filters?.sort_by || 'created_at');
    const [sortOrder, setSortOrder] = useState(filters?.sort_order || 'desc');
    const [perPage, setPerPage] = useState(filters?.per_page || 10);

    const { data, setData, post, put, processing, errors, reset } = useForm({
        name: '',

        mobile: '',
        email: '',
        address: '',
        proprietor_name: '',
        status: true,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingSupplier) {
            put(`/suppliers/${editingSupplier.id}`, {
                onSuccess: () => {
                    setEditingSupplier(null);
                    reset();
                },
            });
        } else {
            post('/suppliers', {
                onSuccess: () => {
                    setIsCreateOpen(false);
                    reset();
                },
            });
        }
    };

    const handleEdit = async (supplier: Supplier) => {
        setEditingSupplier(supplier);
        setData({
            name: supplier.name,

            mobile: supplier.mobile || '',
            email: supplier.email || '',
            address: supplier.address || '',
            proprietor_name: supplier.proprietor_name || '',
            status: supplier.status,
        });
    };

    const handleDelete = (supplier: Supplier) => {
        setDeletingSupplier(supplier);
    };

    const confirmDelete = () => {
        if (deletingSupplier) {
            router.delete(`/suppliers/${deletingSupplier.id}`, {
                onSuccess: () => setDeletingSupplier(null),
            });
        }
    };

    const handleBulkDelete = () => {
        setIsBulkDeleting(true);
    };

    const confirmBulkDelete = () => {
        router.delete('/suppliers/bulk/delete', {
            data: { ids: selectedSuppliers },
            onSuccess: () => {
                setSelectedSuppliers([]);
                setIsBulkDeleting(false);
            },
        });
    };

    const applyFilters = () => {
        router.get(
            '/suppliers',
            {
                search: search || undefined,
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
        setStatus('all');
        router.get(
            '/suppliers',
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
            '/suppliers',
            {
                search: search || undefined,
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
            '/suppliers',
            {
                search: search || undefined,
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
        if (selectedSuppliers.length === suppliers.data.length) {
            setSelectedSuppliers([]);
        } else {
            setSelectedSuppliers(suppliers.data.map((supplier) => supplier.id));
        }
    };

    const toggleSelectSupplier = (supplierId: number) => {
        if (selectedSuppliers.includes(supplierId)) {
            setSelectedSuppliers(selectedSuppliers.filter((id) => id !== supplierId));
        } else {
            setSelectedSuppliers([...selectedSuppliers, supplierId]);
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
            <Head title="Suppliers" />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold dark:text-white">
                            Suppliers
                        </h1>
                        <p className="text-gray-600 dark:text-gray-400">
                            Manage suppliers and their accounts
                        </p>
                    </div>
                    <div className="flex gap-2">
                        {selectedSuppliers.length > 0 && can('delete-supplier') && (
                            <Button
                                variant="destructive"
                                onClick={handleBulkDelete}
                            >
                                <Trash2 className="mr-2 h-4 w-4" />
                                Delete Selected ({selectedSuppliers.length})
                            </Button>
                        )}
                        {canDownload && (
                        <Button
                            variant="success"
                            onClick={() => {
                                const params = new URLSearchParams();
                                if (search) params.append('search', search);
                                if (status !== 'all') params.append('status', status);
                                if (sortBy) params.append('sort_by', sortBy);
                                if (sortOrder) params.append('sort_order', sortOrder);
                                window.location.href = `/suppliers/download-pdf?${params.toString()}`;
                            }}
                        >
                            <FileText className="mr-2 h-4 w-4" />
                            Download
                        </Button>
                        )}
                        {can('create-supplier') && (
                        <Button onClick={() => {
                            setIsCreateOpen(true);

                        }}>
                            <Plus className="mr-2 h-4 w-4" />
                            Add Supplier
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
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                            <div>
                                <Label className="dark:text-gray-200">
                                    Search
                                </Label>
                                <Input
                                    placeholder="Search suppliers..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                />
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
                                            '/suppliers',
                                            {
                                                search: search || undefined,
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
                                        <SelectItem value="active">Active</SelectItem>
                                        <SelectItem value="inactive">Inactive</SelectItem>
                                    </SelectContent>
                                </Select>
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

                <Card className="dark:border-gray-700 dark:bg-gray-800">
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b dark:border-gray-700">
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                            <input
                                                type="checkbox"
                                                checked={
                                                    selectedSuppliers.length === suppliers.data.length &&
                                                    suppliers.data.length > 0
                                                }
                                                onChange={toggleSelectAll}
                                                className="rounded border-gray-300 dark:border-gray-600"
                                            />
                                        </th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                            SL
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
                                            Account Number
                                        </th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                            Mobile
                                        </th>
                                        <th className="p-4 text-right text-[13px] font-medium dark:text-gray-300">
                                            Total Purchases
                                        </th>
                                        <th className="p-4 text-right text-[13px] font-medium dark:text-gray-300">
                                            Total Payment
                                        </th>
                                        <th className="p-4 text-right text-[13px] font-medium dark:text-gray-300">
                                            Total
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
                                    {suppliers.data.length > 0 ? (
                                        suppliers.data.map((supplier, index) => (
                                            <tr
                                                key={supplier.id}
                                                className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700"
                                            >
                                                <td className="p-4">
                                                    <input
                                                        type="checkbox"
                                                        checked={selectedSuppliers.includes(supplier.id)}
                                                        onChange={() => toggleSelectSupplier(supplier.id)}
                                                        className="rounded border-gray-300 dark:border-gray-600"
                                                    />
                                                </td>
                                                <td className="p-4 text-[13px] dark:text-white">
                                                    {(suppliers.current_page - 1) * suppliers.per_page + index + 1}
                                                </td>
                                                <td className="p-4 text-[13px] dark:text-white">
                                                    {supplier.name}
                                                </td>
                                                <td className="p-4 text-[13px] dark:text-gray-300">
                                                    {supplier.account_number || 'N/A'}
                                                </td>
                                                <td className="p-4 text-[13px] dark:text-gray-300">
                                                    {supplier.mobile || 'N/A'}
                                                </td>
                                                <td className="p-4 text-[13px] text-right dark:text-white font-semibold">
                                                    {supplier.total_purchases?.toLocaleString() || '0'}
                                                </td>
                                                <td className="p-4 text-[13px] text-right dark:text-green-400 font-semibold">
                                                    {supplier.total_payment?.toLocaleString() || '0'}
                                                </td>
                                                <td className="p-4 text-[13px] text-right font-semibold">
                                                    <span className={supplier.total_due > 0 ? 'text-red-600 dark:text-red-400' : supplier.total_due < 0 ? 'text-green-600 dark:text-green-400' : 'dark:text-white'}>
                                                        {Math.abs(supplier.total_due || 0).toLocaleString()}
                                                        {supplier.total_due > 0 && ' (Due)'}
                                                        {supplier.total_due < 0 && ' (Adv)'}
                                                    </span>
                                                </td>
                                                <td className="p-4">
                                                    <span
                                                        className={`px-2 py-1 rounded text-xs ${
                                                            supplier.status
                                                                ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                                                : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                                        }`}
                                                    >
                                                        {supplier.status ? 'Active' : 'Inactive'}
                                                    </span>
                                                </td>
                                                {hasActionPermission && (
                                                <td className="p-4">
                                                    <div className="flex gap-2">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => router.get(`/suppliers/${supplier.id}`)}
                                                            className="text-blue-600 hover:text-blue-800"
                                                        >
                                                            <Eye className="h-4 w-4" />
                                                        </Button>
                                                        {can('update-supplier') && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => handleEdit(supplier)}
                                                            className="text-indigo-600 hover:text-indigo-800"
                                                        >
                                                            <Edit className="h-4 w-4" />
                                                        </Button>
                                                        )}
                                                        {can('delete-supplier') && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => handleDelete(supplier)}
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
                                                colSpan={hasActionPermission ? 10 : 9}
                                                className="p-8 text-center text-gray-500 dark:text-gray-400"
                                            >
                                                <SuppliersIcon className="mx-auto mb-4 h-12 w-12 text-gray-400" />
                                                No suppliers found
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <Pagination
                            currentPage={suppliers.current_page}
                            lastPage={suppliers.last_page}
                            from={suppliers.from}
                            to={suppliers.to}
                            total={suppliers.total}
                            perPage={perPage}
                            onPageChange={handlePageChange}
                            onPerPageChange={(newPerPage) => {
                                setPerPage(newPerPage);
                                router.get(
                                    '/suppliers',
                                    {
                                        search: search || undefined,
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
                    title="Create Supplier"
                    onSubmit={handleSubmit}
                    processing={processing}
                    submitText="Create"
                >
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <Label htmlFor="name" className="dark:text-gray-200">
                                Name *
                            </Label>
                            <Input
                                id="name"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            />
                            {errors.name && <span className="text-sm text-red-500">{errors.name}</span>}
                        </div>
                        <div>
                            <Label htmlFor="email" className="dark:text-gray-200">
                                Email
                            </Label>
                            <Input
                                id="email"
                                type="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            />
                            {errors.email && <span className="text-sm text-red-500">{errors.email}</span>}
                        </div>
                        <div>
                            <Label htmlFor="mobile" className="dark:text-gray-200">
                                Mobile
                            </Label>
                            <Input
                                id="mobile"
                                value={data.mobile}
                                onChange={(e) => setData('mobile', e.target.value)}
                                className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            />
                            {errors.mobile && <span className="text-sm text-red-500">{errors.mobile}</span>}
                        </div>
                        <div>
                            <Label htmlFor="proprietor_name" className="dark:text-gray-200">
                                Proprietor Name
                            </Label>
                            <Input
                                id="proprietor_name"
                                value={data.proprietor_name}
                                onChange={(e) => setData('proprietor_name', e.target.value)}
                                className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            />
                            {errors.proprietor_name && (
                                <span className="text-sm text-red-500">{errors.proprietor_name}</span>
                            )}
                        </div>
                        <div>
                            <Label htmlFor="address" className="dark:text-gray-200">
                                Address
                            </Label>
                            <Input
                                id="address"
                                value={data.address}
                                onChange={(e) => setData('address', e.target.value)}
                                className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            />
                            {errors.address && <span className="text-sm text-red-500">{errors.address}</span>}
                        </div>

                        <div>
                            <Label htmlFor="status" className="dark:text-gray-200">
                                Status
                            </Label>
                            <Select
                                value={data.status ? 'true' : 'false'}
                                onValueChange={(value) => setData('status', value === 'true')}
                            >
                                <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                    <SelectValue placeholder="Select status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="true">Active</SelectItem>
                                    <SelectItem value="false">Inactive</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                </FormModal>

                <FormModal
                    isOpen={!!editingSupplier}
                    onClose={() => setEditingSupplier(null)}
                    title="Edit Supplier"
                    onSubmit={handleSubmit}
                    processing={processing}
                    submitText="Update"
                >
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <Label htmlFor="edit-name" className="dark:text-gray-200">
                                Name *
                            </Label>
                            <Input
                                id="edit-name"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            />
                            {errors.name && <span className="text-sm text-red-500">{errors.name}</span>}
                        </div>
                        <div>
                            <Label htmlFor="edit-email" className="dark:text-gray-200">
                                Email
                            </Label>
                            <Input
                                id="edit-email"
                                type="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            />
                            {errors.email && <span className="text-sm text-red-500">{errors.email}</span>}
                        </div>
                        <div>
                            <Label htmlFor="edit-mobile" className="dark:text-gray-200">
                                Mobile
                            </Label>
                            <Input
                                id="edit-mobile"
                                value={data.mobile}
                                onChange={(e) => setData('mobile', e.target.value)}
                                className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            />
                            {errors.mobile && <span className="text-sm text-red-500">{errors.mobile}</span>}
                        </div>
                        <div>
                            <Label htmlFor="edit-proprietor_name" className="dark:text-gray-200">
                                Proprietor Name
                            </Label>
                            <Input
                                id="edit-proprietor_name"
                                value={data.proprietor_name}
                                onChange={(e) => setData('proprietor_name', e.target.value)}
                                className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            />
                            {errors.proprietor_name && (
                                <span className="text-sm text-red-500">{errors.proprietor_name}</span>
                            )}
                        </div>
                        <div>
                            <Label htmlFor="edit-address" className="dark:text-gray-200">
                                Address
                            </Label>
                            <Input
                                id="edit-address"
                                value={data.address}
                                onChange={(e) => setData('address', e.target.value)}
                                className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            />
                            {errors.address && <span className="text-sm text-red-500">{errors.address}</span>}
                        </div>

                        <div>
                            <Label htmlFor="edit-status" className="dark:text-gray-200">
                                Status
                            </Label>
                            <Select
                                value={data.status ? 'true' : 'false'}
                                onValueChange={(value) => setData('status', value === 'true')}
                            >
                                <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                    <SelectValue placeholder="Select status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="true">Active</SelectItem>
                                    <SelectItem value="false">Inactive</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                </FormModal>

                <DeleteModal
                    isOpen={!!deletingSupplier}
                    onClose={() => setDeletingSupplier(null)}
                    onConfirm={confirmDelete}
                    title="Delete Supplier"
                    message={`Are you sure you want to delete the supplier "${deletingSupplier?.name}"? This action cannot be undone.`}
                />

                <DeleteModal
                    isOpen={isBulkDeleting}
                    onClose={() => setIsBulkDeleting(false)}
                    onConfirm={confirmBulkDelete}
                    title="Delete Selected Suppliers"
                    message={`Are you sure you want to delete ${selectedSuppliers.length} selected suppliers? This action cannot be undone.`}
                />
            </div>
        </AppLayout>
    );
}