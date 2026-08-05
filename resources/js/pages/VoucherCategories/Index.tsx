import { openPdfViewer } from '@/components/documents/pdf-viewer';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DeleteModal } from '@/components/ui/delete-modal';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
import { usePermission } from '@/hooks/usePermission';
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
    Tag,
    Trash2,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';

interface VoucherCategory {
    id: number;
    code: string | null;
    name: string;
    description: string | null;
    status: boolean;
    sort_order: number;
    is_system: boolean;
    created_at: string;
}

interface VoucherCategoryPageProps {
    voucherCategories: {
        data: VoucherCategory[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
    };
    filters: {
        search?: string;
        status?: string;
        system?: string;
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
        title: 'Voucher Category Management',
        href: '/voucher-categories',
    },
];

export default function VoucherCategoryIndex({
    voucherCategories,
    filters,
}: VoucherCategoryPageProps) {
    const { can } = usePermission();
    const canCreate = can('voucher-category-create');
    const canUpdate = can('voucher-category-update');
    const canDelete = can('voucher-category-delete');

    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editingCategory, setEditingCategory] =
        useState<VoucherCategory | null>(null);
    const [viewingCategory, setViewingCategory] =
        useState<VoucherCategory | null>(null);
    const [deletingCategory, setDeletingCategory] =
        useState<VoucherCategory | null>(null);
    const [isBulkDeleting, setIsBulkDeleting] = useState(false);
    const [selectedCategoryIds, setSelectedCategoryIds] = useState<number[]>(
        [],
    );
    const [search, setSearch] = useState(filters.search || '');
    const [status, setStatus] = useState(filters.status || 'all');
    const [system, setSystem] = useState(filters.system || 'all');
    const [sortBy, setSortBy] = useState(filters.sort_by || 'sort_order');
    const [sortOrder, setSortOrder] = useState(filters.sort_order || 'asc');
    const [perPage, setPerPage] = useState(filters.per_page || 10);

    const { data, setData, post, put, processing, errors, reset } = useForm({
        name: '',
        description: '',
        status: true,
        sort_order: 0,
    });

    const visit = (
        overrides: Record<string, string | number | undefined> = {},
    ) => {
        router.get(
            '/voucher-categories',
            {
                search: search || undefined,
                status: status === 'all' ? undefined : status,
                system: system === 'all' ? undefined : system,
                sort_by: sortBy,
                sort_order: sortOrder,
                per_page: perPage,
                ...overrides,
            },
            { preserveState: true },
        );
    };

    const handleSubmit = (event: React.FormEvent) => {
        event.preventDefault();

        if (editingCategory) {
            put(`/voucher-categories/${editingCategory.id}`, {
                onSuccess: () => {
                    setEditingCategory(null);
                    reset();
                },
            });

            return;
        }

        post('/voucher-categories', {
            onSuccess: () => {
                setIsCreateOpen(false);
                reset();
            },
        });
    };

    const openCreateModal = () => {
        reset();
        setIsCreateOpen(true);
    };

    const handleEdit = (category: VoucherCategory) => {
        setEditingCategory(category);
        setData({
            name: category.name,
            description: category.description || '',
            status: category.status,
            sort_order: category.sort_order,
        });
    };

    const confirmDelete = () => {
        if (!deletingCategory) {
            return;
        }

        router.delete(`/voucher-categories/${deletingCategory.id}`, {
            onSuccess: () => setDeletingCategory(null),
        });
    };

    const confirmBulkDelete = () => {
        router.delete('/voucher-categories/bulk/delete', {
            data: { ids: selectedCategoryIds },
            onSuccess: () => {
                setSelectedCategoryIds([]);
                setIsBulkDeleting(false);
            },
        });
    };

    const clearFilters = () => {
        setSearch('');
        setStatus('all');
        setSystem('all');
        router.get(
            '/voucher-categories',
            {
                sort_by: sortBy,
                sort_order: sortOrder,
                per_page: perPage,
            },
            { preserveState: true },
        );
    };

    const handleSort = (column: string) => {
        const nextOrder =
            sortBy === column && sortOrder === 'asc' ? 'desc' : 'asc';
        setSortBy(column);
        setSortOrder(nextOrder);
        visit({ sort_by: column, sort_order: nextOrder });
    };

    const customCategories = voucherCategories.data.filter(
        (category) => !category.is_system,
    );
    const allCustomSelected =
        customCategories.length > 0 &&
        customCategories.every((category) =>
            selectedCategoryIds.includes(category.id),
        );

    const toggleSelectAll = () => {
        setSelectedCategoryIds(
            allCustomSelected
                ? []
                : customCategories.map((category) => category.id),
        );
    };

    const toggleSelectedCategory = (categoryId: number) => {
        setSelectedCategoryIds((current) =>
            current.includes(categoryId)
                ? current.filter((id) => id !== categoryId)
                : [...current, categoryId],
        );
    };

    useEffect(() => {
        const timeout = window.setTimeout(() => {
            if (search !== (filters.search || '')) {
                visit();
            }
        }, 500);

        return () => window.clearTimeout(timeout);
    }, [search]);

    useEffect(() => {
        if (status !== (filters.status || 'all')) {
            visit();
        }
    }, [status]);

    useEffect(() => {
        if (system !== (filters.system || 'all')) {
            visit();
        }
    }, [system]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Voucher Category Management" />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold dark:text-white">
                            Voucher Category Management
                        </h1>
                        <p className="text-gray-600 dark:text-gray-400">
                            Manage reusable categories for voucher transactions
                        </p>
                    </div>
                    <div className="flex gap-2">
                        {selectedCategoryIds.length > 0 && canDelete && (
                            <Button
                                variant="destructive"
                                onClick={() => setIsBulkDeleting(true)}
                            >
                                <Trash2 className="mr-2 h-4 w-4" />
                                Delete Selected ({selectedCategoryIds.length})
                            </Button>
                        )}
                        <Button
                            variant="success"
                            onClick={() => {
                                const params = new URLSearchParams();
                                if (search) params.set('search', search);
                                if (status !== 'all')
                                    params.set('status', status);
                                if (system !== 'all')
                                    params.set('system', system);
                                params.set('sort_by', sortBy);
                                params.set('sort_order', sortOrder);
                                openPdfViewer(`/voucher-categories/download-pdf?${params.toString()}`);
                            }}
                        >
                            <FileText className="mr-2 h-4 w-4" />
                            Download
                        </Button>
                        {canCreate && (
                            <Button onClick={openCreateModal}>
                                <Plus className="mr-2 h-4 w-4" />
                                Add Voucher Category
                            </Button>
                        )}
                    </div>
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
                                <Label className="dark:text-gray-200">
                                    Search
                                </Label>
                                <Input
                                    placeholder="Search code, name, description..."
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                />
                            </div>
                            <div>
                                <Label className="dark:text-gray-200">
                                    Status
                                </Label>
                                <Select
                                    value={status}
                                    onValueChange={setStatus}
                                >
                                    <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                        <SelectValue placeholder="All status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            All status
                                        </SelectItem>
                                        <SelectItem value="1">
                                            Active
                                        </SelectItem>
                                        <SelectItem value="0">
                                            Inactive
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <Label className="dark:text-gray-200">
                                    Category Type
                                </Label>
                                <Select
                                    value={system}
                                    onValueChange={setSystem}
                                >
                                    <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                        <SelectValue placeholder="All categories" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            All categories
                                        </SelectItem>
                                        <SelectItem value="system">
                                            System
                                        </SelectItem>
                                        <SelectItem value="custom">
                                            Custom
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="flex items-end gap-2">
                                <Button
                                    onClick={() => visit()}
                                    className="px-4"
                                >
                                    Apply Filters
                                </Button>
                                <Button
                                    variant="secondary"
                                    onClick={clearFilters}
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
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b dark:border-gray-700">
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                            <input
                                                type="checkbox"
                                                checked={allCustomSelected}
                                                onChange={toggleSelectAll}
                                                disabled={
                                                    customCategories.length ===
                                                    0
                                                }
                                                className="rounded border-gray-300 dark:border-gray-600"
                                                aria-label="Select custom voucher categories"
                                            />
                                        </th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                            SL
                                        </th>
                                        <SortableHeader
                                            label="Category Code"
                                            column="code"
                                            sortBy={sortBy}
                                            sortOrder={sortOrder}
                                            onSort={handleSort}
                                        />
                                        <SortableHeader
                                            label="Category Name"
                                            column="name"
                                            sortBy={sortBy}
                                            sortOrder={sortOrder}
                                            onSort={handleSort}
                                        />
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                            Description
                                        </th>
                                        <SortableHeader
                                            label="Sort Order"
                                            column="sort_order"
                                            sortBy={sortBy}
                                            sortOrder={sortOrder}
                                            onSort={handleSort}
                                        />
                                        <SortableHeader
                                            label="Status"
                                            column="status"
                                            sortBy={sortBy}
                                            sortOrder={sortOrder}
                                            onSort={handleSort}
                                        />
                                        <SortableHeader
                                            label="System Category"
                                            column="is_system"
                                            sortBy={sortBy}
                                            sortOrder={sortOrder}
                                            onSort={handleSort}
                                        />
                                        <SortableHeader
                                            label="Created Date"
                                            column="created_at"
                                            sortBy={sortBy}
                                            sortOrder={sortOrder}
                                            onSort={handleSort}
                                        />
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {voucherCategories.data.length > 0 ? (
                                        voucherCategories.data.map(
                                            (category, index) => (
                                                <tr
                                                    key={category.id}
                                                    className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700"
                                                >
                                                    <td className="p-4">
                                                        {!category.is_system && (
                                                            <input
                                                                type="checkbox"
                                                                checked={selectedCategoryIds.includes(
                                                                    category.id,
                                                                )}
                                                                onChange={() =>
                                                                    toggleSelectedCategory(
                                                                        category.id,
                                                                    )
                                                                }
                                                                className="rounded border-gray-300 dark:border-gray-600"
                                                                aria-label={`Select ${category.name}`}
                                                            />
                                                        )}
                                                    </td>
                                                    <td className="p-4 text-[13px] dark:text-white">
                                                        {(voucherCategories.from ||
                                                            1) + index}
                                                    </td>
                                                    <td className="p-4 text-[13px] dark:text-white">
                                                        {category.code || '-'}
                                                    </td>
                                                    <td className="p-4 text-[13px] font-medium dark:text-white">
                                                        {category.name}
                                                    </td>
                                                    <td className="max-w-72 p-4 text-[13px] text-gray-600 dark:text-gray-300">
                                                        <span className="line-clamp-2">
                                                            {category.description ||
                                                                '-'}
                                                        </span>
                                                    </td>
                                                    <td className="p-4 text-[13px] dark:text-gray-300">
                                                        {category.sort_order}
                                                    </td>
                                                    <td className="p-4">
                                                        <StatusBadge
                                                            active={
                                                                category.status
                                                            }
                                                        />
                                                    </td>
                                                    <td className="p-4">
                                                        <span
                                                            className={`rounded px-2 py-1 text-xs ${
                                                                category.is_system
                                                                    ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'
                                                                    : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200'
                                                            }`}
                                                        >
                                                            {category.is_system
                                                                ? 'System'
                                                                : 'Custom'}
                                                        </span>
                                                    </td>
                                                    <td className="p-4 text-[13px] dark:text-gray-300">
                                                        {category.created_at}
                                                    </td>
                                                    <td className="p-4">
                                                        <div className="flex gap-2">
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                title="View voucher category"
                                                                aria-label="View voucher category"
                                                                onClick={() =>
                                                                    setViewingCategory(
                                                                        category,
                                                                    )
                                                                }
                                                                className="text-gray-600 hover:text-gray-900 dark:text-gray-300"
                                                            >
                                                                <Eye className="h-4 w-4" />
                                                            </Button>
                                                            {canUpdate && (
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    title="Edit voucher category"
                                                                    aria-label="Edit voucher category"
                                                                    onClick={() =>
                                                                        handleEdit(
                                                                            category,
                                                                        )
                                                                    }
                                                                    className="text-indigo-600 hover:text-indigo-800"
                                                                >
                                                                    <Edit className="h-4 w-4" />
                                                                </Button>
                                                            )}
                                                            {canDelete &&
                                                                !category.is_system && (
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="sm"
                                                                        title="Delete voucher category"
                                                                        aria-label="Delete voucher category"
                                                                        onClick={() =>
                                                                            setDeletingCategory(
                                                                                category,
                                                                            )
                                                                        }
                                                                        className="text-red-600 hover:text-red-800"
                                                                    >
                                                                        <Trash2 className="h-4 w-4" />
                                                                    </Button>
                                                                )}
                                                        </div>
                                                    </td>
                                                </tr>
                                            ),
                                        )
                                    ) : (
                                        <tr>
                                            <td
                                                colSpan={10}
                                                className="p-10 text-center text-gray-500 dark:text-gray-400"
                                            >
                                                <Tag className="mx-auto mb-4 h-12 w-12 text-gray-400" />
                                                No voucher categories found
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <Pagination
                            currentPage={voucherCategories.current_page}
                            lastPage={voucherCategories.last_page}
                            from={voucherCategories.from}
                            to={voucherCategories.to}
                            total={voucherCategories.total}
                            perPage={perPage}
                            onPageChange={(page) => visit({ page })}
                            onPerPageChange={(nextPerPage) => {
                                setPerPage(nextPerPage);
                                visit({
                                    per_page: nextPerPage,
                                    page: undefined,
                                });
                            }}
                        />
                    </CardContent>
                </Card>

                <FormModal
                    isOpen={isCreateOpen}
                    onClose={() => setIsCreateOpen(false)}
                    title="Create Voucher Category"
                    onSubmit={handleSubmit}
                    processing={processing}
                    submitText="Create"
                    errors={errors}
                >
                    <div>
                        <Label className="dark:text-gray-200">
                            Category Name
                        </Label>
                        <Input
                            value={data.name}
                            onChange={(event) =>
                                setData('name', event.target.value)
                            }
                            maxLength={150}
                            required
                            aria-invalid={Boolean(errors.name)}
                            className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                        <InputError message={errors.name} />
                    </div>
                    <div>
                        <Label className="dark:text-gray-200">
                            Description
                        </Label>
                        <textarea
                            value={data.description}
                            onChange={(event) =>
                                setData('description', event.target.value)
                            }
                            maxLength={2000}
                            rows={3}
                            className="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                        <InputError message={errors.description} />
                    </div>
                    <div>
                        <Label className="dark:text-gray-200">Sort Order</Label>
                        <Input
                            type="number"
                            min="0"
                            max="65535"
                            value={data.sort_order}
                            onChange={(event) =>
                                setData(
                                    'sort_order',
                                    Number(event.target.value || 0),
                                )
                            }
                            required
                            className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                        <InputError message={errors.sort_order} />
                    </div>
                    <div>
                        <Label className="dark:text-gray-200">Status</Label>
                        <Select
                            value={data.status ? '1' : '0'}
                            onValueChange={(value) =>
                                setData('status', value === '1')
                            }
                        >
                            <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="1">Active</SelectItem>
                                <SelectItem value="0">Inactive</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError message={errors.status} />
                    </div>
                </FormModal>

                <Dialog
                    open={Boolean(viewingCategory)}
                    onOpenChange={(open) => {
                        if (!open) {
                            setViewingCategory(null);
                        }
                    }}
                >
                    <DialogContent className="dark:bg-gray-800">
                        <DialogHeader>
                            <DialogTitle className="dark:text-white">
                                Voucher Category Details
                            </DialogTitle>
                            <DialogDescription>
                                View the selected voucher category information.
                            </DialogDescription>
                        </DialogHeader>
                        {viewingCategory && (
                            <dl className="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                                <Detail
                                    label="Category Code"
                                    value={viewingCategory.code || '-'}
                                />
                                <Detail
                                    label="Category Name"
                                    value={viewingCategory.name}
                                />
                                <Detail
                                    label="Description"
                                    value={viewingCategory.description || '-'}
                                    wide
                                />
                                <Detail
                                    label="Sort Order"
                                    value={viewingCategory.sort_order.toString()}
                                />
                                <Detail
                                    label="Status"
                                    value={
                                        viewingCategory.status
                                            ? 'Active'
                                            : 'Inactive'
                                    }
                                />
                                <Detail
                                    label="System Category"
                                    value={
                                        viewingCategory.is_system ? 'Yes' : 'No'
                                    }
                                />
                                <Detail
                                    label="Created Date"
                                    value={viewingCategory.created_at}
                                />
                            </dl>
                        )}
                    </DialogContent>
                </Dialog>

                <FormModal
                    isOpen={Boolean(editingCategory)}
                    onClose={() => setEditingCategory(null)}
                    title="Edit Voucher Category"
                    onSubmit={handleSubmit}
                    processing={processing}
                    submitText="Update"
                    errors={errors}
                >
                    <div>
                        <Label className="dark:text-gray-200">
                            Category Code
                        </Label>
                        <Input
                            value={editingCategory?.code || ''}
                            readOnly
                            className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                    </div>
                    <div>
                        <Label className="dark:text-gray-200">
                            Category Name
                        </Label>
                        <Input
                            value={data.name}
                            onChange={(event) =>
                                setData('name', event.target.value)
                            }
                            maxLength={150}
                            required
                            aria-invalid={Boolean(errors.name)}
                            className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                        <InputError message={errors.name} />
                    </div>
                    <div>
                        <Label className="dark:text-gray-200">
                            Description
                        </Label>
                        <textarea
                            value={data.description}
                            onChange={(event) =>
                                setData('description', event.target.value)
                            }
                            maxLength={2000}
                            rows={3}
                            className="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                        <InputError message={errors.description} />
                    </div>
                    <div>
                        <Label className="dark:text-gray-200">Sort Order</Label>
                        <Input
                            type="number"
                            min="0"
                            max="65535"
                            value={data.sort_order}
                            onChange={(event) =>
                                setData(
                                    'sort_order',
                                    Number(event.target.value || 0),
                                )
                            }
                            required
                            className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                        <InputError message={errors.sort_order} />
                    </div>
                    <div>
                        <Label className="dark:text-gray-200">Status</Label>
                        <Select
                            value={data.status ? '1' : '0'}
                            onValueChange={(value) =>
                                setData('status', value === '1')
                            }
                        >
                            <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="1">Active</SelectItem>
                                <SelectItem value="0">Inactive</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError message={errors.status} />
                    </div>
                </FormModal>

                <DeleteModal
                    isOpen={Boolean(deletingCategory)}
                    onClose={() => setDeletingCategory(null)}
                    onConfirm={confirmDelete}
                    title="Delete Voucher Category"
                    message={`Are you sure you want to delete "${deletingCategory?.name}"? This action cannot be undone.`}
                />

                <DeleteModal
                    isOpen={isBulkDeleting}
                    onClose={() => setIsBulkDeleting(false)}
                    onConfirm={confirmBulkDelete}
                    title="Delete Selected Voucher Categories"
                    message={`Are you sure you want to delete ${selectedCategoryIds.length} selected voucher categories? This action cannot be undone.`}
                />
            </div>
        </AppLayout>
    );
}

function SortableHeader({
    label,
    column,
    sortBy,
    sortOrder,
    onSort,
}: {
    label: string;
    column: string;
    sortBy: string;
    sortOrder: string;
    onSort: (column: string) => void;
}) {
    return (
        <th
            className="cursor-pointer p-4 text-left text-[13px] font-medium dark:text-gray-300"
            onClick={() => onSort(column)}
        >
            <div className="flex items-center gap-1">
                {label}
                {sortBy === column &&
                    (sortOrder === 'asc' ? (
                        <ChevronUp className="h-4 w-4" />
                    ) : (
                        <ChevronDown className="h-4 w-4" />
                    ))}
            </div>
        </th>
    );
}

function StatusBadge({ active }: { active: boolean }) {
    return (
        <span
            className={`rounded px-2 py-1 text-xs ${
                active
                    ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                    : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
            }`}
        >
            {active ? 'Active' : 'Inactive'}
        </span>
    );
}

function Detail({
    label,
    value,
    wide = false,
}: {
    label: string;
    value: string;
    wide?: boolean;
}) {
    return (
        <div className={wide ? 'sm:col-span-2' : undefined}>
            <dt className="text-xs font-medium text-gray-500 dark:text-gray-400">
                {label}
            </dt>
            <dd className="mt-1 text-gray-900 dark:text-gray-100">{value}</dd>
        </div>
    );
}
