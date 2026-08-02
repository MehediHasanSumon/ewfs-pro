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
    Tags,
    Trash2,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';

interface VoucherCategory {
    id: number;
    code: string;
    name: string;
}

interface VoucherTransactionType {
    id: number;
    voucher_category_id: number;
    code: string;
    name: string;
    voucher_type: string;
    description: string | null;
    sort_order: number;
    status: boolean;
    is_system: boolean;
    voucher_category: VoucherCategory;
    created_at: string;
}

interface PageProps {
    voucherTransactionTypes: {
        data: VoucherTransactionType[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
    };
    voucherCategories: VoucherCategory[];
    voucherTypes: string[];
    filters: {
        search?: string;
        category?: string;
        voucher_type?: string;
        status?: string;
        system?: string;
        sort_by?: string;
        sort_order?: string;
        per_page?: number;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    {
        title: 'Voucher Transaction Types',
        href: '/voucher-transaction-types',
    },
];

export default function VoucherTransactionTypeIndex({
    voucherTransactionTypes,
    voucherCategories,
    voucherTypes,
    filters,
}: PageProps) {
    const { can } = usePermission();
    const canCreate = can('voucher-transaction-type-create');
    const canUpdate = can('voucher-transaction-type-update');
    const canDelete = can('voucher-transaction-type-delete');
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editingType, setEditingType] =
        useState<VoucherTransactionType | null>(null);
    const [viewingType, setViewingType] =
        useState<VoucherTransactionType | null>(null);
    const [deletingType, setDeletingType] =
        useState<VoucherTransactionType | null>(null);
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const [isBulkDeleting, setIsBulkDeleting] = useState(false);
    const [search, setSearch] = useState(filters.search || '');
    const [category, setCategory] = useState(filters.category || 'all');
    const [voucherType, setVoucherType] = useState(
        filters.voucher_type || 'all',
    );
    const [status, setStatus] = useState(filters.status || 'all');
    const [system, setSystem] = useState(filters.system || 'all');
    const [sortBy, setSortBy] = useState(filters.sort_by || 'sort_order');
    const [sortOrder, setSortOrder] = useState(
        filters.sort_order || 'asc',
    );
    const [perPage, setPerPage] = useState(filters.per_page || 10);
    const { data, setData, post, put, processing, errors, reset } = useForm({
        voucher_category_id: '',
        voucher_type: 'payment',
        name: '',
        description: '',
        sort_order: 0,
        status: true,
    });

    const visit = (
        overrides: Record<string, string | number | undefined> = {},
    ) => {
        router.get(
            '/voucher-transaction-types',
            {
                search: search || undefined,
                category: category === 'all' ? undefined : category,
                voucher_type:
                    voucherType === 'all' ? undefined : voucherType,
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

        if (editingType) {
            put(`/voucher-transaction-types/${editingType.id}`, {
                onSuccess: () => {
                    setEditingType(null);
                    reset();
                },
            });
            return;
        }

        post('/voucher-transaction-types', {
            onSuccess: () => {
                setIsCreateOpen(false);
                reset();
            },
        });
    };

    const openCreate = () => {
        reset();
        setData({
            voucher_category_id: '',
            voucher_type: 'payment',
            name: '',
            description: '',
            sort_order: 0,
            status: true,
        });
        setIsCreateOpen(true);
    };

    const openEdit = (transactionType: VoucherTransactionType) => {
        setEditingType(transactionType);
        setData({
            voucher_category_id:
                transactionType.voucher_category_id.toString(),
            voucher_type: transactionType.voucher_type,
            name: transactionType.name,
            description: transactionType.description || '',
            sort_order: transactionType.sort_order,
            status: transactionType.status,
        });
    };

    const clearFilters = () => {
        setSearch('');
        setCategory('all');
        setVoucherType('all');
        setStatus('all');
        setSystem('all');
        router.get(
            '/voucher-transaction-types',
            { sort_by: sortBy, sort_order: sortOrder, per_page: perPage },
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

    const customTypes = voucherTransactionTypes.data.filter(
        (transactionType) => !transactionType.is_system,
    );
    const allCustomSelected =
        customTypes.length > 0 &&
        customTypes.every((transactionType) =>
            selectedIds.includes(transactionType.id),
        );

    useEffect(() => {
        const timeout = window.setTimeout(() => {
            if (search !== (filters.search || '')) visit();
        }, 500);
        return () => window.clearTimeout(timeout);
    }, [search]);

    useEffect(() => {
        if (
            category !== (filters.category || 'all') ||
            voucherType !== (filters.voucher_type || 'all') ||
            status !== (filters.status || 'all') ||
            system !== (filters.system || 'all')
        ) {
            visit();
        }
    }, [category, voucherType, status, system]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Voucher Transaction Types" />
            <div className="space-y-6 p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-3xl font-bold dark:text-white">
                            Voucher Transaction Types
                        </h1>
                        <p className="text-gray-600 dark:text-gray-400">
                            Manage transaction types used by ERP vouchers
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {selectedIds.length > 0 && canDelete && (
                            <Button
                                variant="destructive"
                                onClick={() => setIsBulkDeleting(true)}
                            >
                                <Trash2 className="mr-2 h-4 w-4" />
                                Delete Selected ({selectedIds.length})
                            </Button>
                        )}
                        <Button
                            variant="success"
                            onClick={() => {
                                const params = new URLSearchParams();
                                if (search) params.set('search', search);
                                if (category !== 'all')
                                    params.set('category', category);
                                if (voucherType !== 'all')
                                    params.set(
                                        'voucher_type',
                                        voucherType,
                                    );
                                if (status !== 'all')
                                    params.set('status', status);
                                if (system !== 'all')
                                    params.set('system', system);
                                params.set('sort_by', sortBy);
                                params.set('sort_order', sortOrder);
                                window.location.href = `/voucher-transaction-types/download-pdf?${params.toString()}`;
                            }}
                        >
                            <FileText className="mr-2 h-4 w-4" />
                            Download
                        </Button>
                        {canCreate && (
                            <Button onClick={openCreate}>
                                <Plus className="mr-2 h-4 w-4" />
                                Add Transaction Type
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
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3 xl:grid-cols-6">
                            <FilterInput
                                label="Search"
                                value={search}
                                onChange={setSearch}
                            />
                            <FilterSelect
                                label="Category"
                                value={category}
                                onChange={setCategory}
                                options={voucherCategories.map((item) => ({
                                    value: item.id.toString(),
                                    label: item.name,
                                }))}
                            />
                            <FilterSelect
                                label="Voucher Type"
                                value={voucherType}
                                onChange={setVoucherType}
                                options={voucherTypes.map((item) => ({
                                    value: item,
                                    label: formatType(item),
                                }))}
                            />
                            <FilterSelect
                                label="Status"
                                value={status}
                                onChange={setStatus}
                                options={[
                                    { value: '1', label: 'Active' },
                                    { value: '0', label: 'Inactive' },
                                ]}
                            />
                            <FilterSelect
                                label="Data Type"
                                value={system}
                                onChange={setSystem}
                                options={[
                                    { value: 'system', label: 'System' },
                                    { value: 'custom', label: 'Custom' },
                                ]}
                            />
                            <div className="flex items-end gap-2">
                                <Button onClick={() => visit()}>Apply</Button>
                                <Button
                                    variant="secondary"
                                    onClick={clearFilters}
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
                                        <th className="p-4 text-left">
                                            <input
                                                type="checkbox"
                                                checked={allCustomSelected}
                                                disabled={
                                                    customTypes.length === 0
                                                }
                                                onChange={() =>
                                                    setSelectedIds(
                                                        allCustomSelected
                                                            ? []
                                                            : customTypes.map(
                                                                  (item) =>
                                                                      item.id,
                                                              ),
                                                    )
                                                }
                                                aria-label="Select custom voucher transaction types"
                                            />
                                        </th>
                                        <th className="p-4 text-left text-[13px] font-medium">
                                            SL
                                        </th>
                                        <SortableHeader
                                            label="Category"
                                            column="voucher_category_id"
                                            sortBy={sortBy}
                                            sortOrder={sortOrder}
                                            onSort={handleSort}
                                        />
                                        <SortableHeader
                                            label="Code"
                                            column="code"
                                            sortBy={sortBy}
                                            sortOrder={sortOrder}
                                            onSort={handleSort}
                                        />
                                        <SortableHeader
                                            label="Transaction Name"
                                            column="name"
                                            sortBy={sortBy}
                                            sortOrder={sortOrder}
                                            onSort={handleSort}
                                        />
                                        <SortableHeader
                                            label="Voucher Type"
                                            column="voucher_type"
                                            sortBy={sortBy}
                                            sortOrder={sortOrder}
                                            onSort={handleSort}
                                        />
                                        <th className="p-4 text-left text-[13px] font-medium">
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
                                            label="System"
                                            column="is_system"
                                            sortBy={sortBy}
                                            sortOrder={sortOrder}
                                            onSort={handleSort}
                                        />
                                        <th className="p-4 text-left text-[13px] font-medium">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {voucherTransactionTypes.data.length ? (
                                        voucherTransactionTypes.data.map(
                                            (transactionType, index) => (
                                                <tr
                                                    key={transactionType.id}
                                                    className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700"
                                                >
                                                    <td className="p-4">
                                                        {!transactionType.is_system && (
                                                            <input
                                                                type="checkbox"
                                                                checked={selectedIds.includes(
                                                                    transactionType.id,
                                                                )}
                                                                onChange={() =>
                                                                    setSelectedIds(
                                                                        (
                                                                            current,
                                                                        ) =>
                                                                            current.includes(
                                                                                transactionType.id,
                                                                            )
                                                                                ? current.filter(
                                                                                      (
                                                                                          id,
                                                                                      ) =>
                                                                                          id !==
                                                                                          transactionType.id,
                                                                                  )
                                                                                : [
                                                                                      ...current,
                                                                                      transactionType.id,
                                                                                  ],
                                                                    )
                                                                }
                                                            />
                                                        )}
                                                    </td>
                                                    <td className="p-4 text-[13px]">
                                                        {(voucherTransactionTypes.current_page -
                                                            1) *
                                                            voucherTransactionTypes.per_page +
                                                            index +
                                                            1}
                                                    </td>
                                                    <td className="p-4 text-[13px]">
                                                        {
                                                            transactionType
                                                                .voucher_category
                                                                .name
                                                        }
                                                    </td>
                                                    <td className="p-4 font-mono text-[13px]">
                                                        {transactionType.code}
                                                    </td>
                                                    <td className="p-4 text-[13px] font-medium">
                                                        {transactionType.name}
                                                    </td>
                                                    <td className="p-4 text-[13px]">
                                                        {formatType(
                                                            transactionType.voucher_type,
                                                        )}
                                                    </td>
                                                    <td className="max-w-64 truncate p-4 text-[13px]">
                                                        {transactionType.description ||
                                                            '-'}
                                                    </td>
                                                    <td className="p-4 text-[13px]">
                                                        {
                                                            transactionType.sort_order
                                                        }
                                                    </td>
                                                    <td className="p-4">
                                                        <StatusBadge
                                                            active={
                                                                transactionType.status
                                                            }
                                                        />
                                                    </td>
                                                    <td className="p-4 text-[13px]">
                                                        {transactionType.is_system
                                                            ? 'Yes'
                                                            : 'No'}
                                                    </td>
                                                    <td className="p-4">
                                                        <div className="flex gap-1">
                                                            <IconButton
                                                                title="View transaction type"
                                                                onClick={() =>
                                                                    setViewingType(
                                                                        transactionType,
                                                                    )
                                                                }
                                                            >
                                                                <Eye className="h-4 w-4" />
                                                            </IconButton>
                                                            {canUpdate && (
                                                                <IconButton
                                                                    title="Edit transaction type"
                                                                    onClick={() =>
                                                                        openEdit(
                                                                            transactionType,
                                                                        )
                                                                    }
                                                                >
                                                                    <Edit className="h-4 w-4" />
                                                                </IconButton>
                                                            )}
                                                            {canDelete &&
                                                                !transactionType.is_system && (
                                                                    <IconButton
                                                                        title="Delete transaction type"
                                                                        destructive
                                                                        onClick={() =>
                                                                            setDeletingType(
                                                                                transactionType,
                                                                            )
                                                                        }
                                                                    >
                                                                        <Trash2 className="h-4 w-4" />
                                                                    </IconButton>
                                                                )}
                                                        </div>
                                                    </td>
                                                </tr>
                                            ),
                                        )
                                    ) : (
                                        <tr>
                                            <td
                                                colSpan={11}
                                                className="p-10 text-center text-gray-500"
                                            >
                                                <Tags className="mx-auto mb-4 h-12 w-12" />
                                                No voucher transaction types
                                                found
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                        <Pagination
                            currentPage={
                                voucherTransactionTypes.current_page
                            }
                            lastPage={voucherTransactionTypes.last_page}
                            from={voucherTransactionTypes.from}
                            to={voucherTransactionTypes.to}
                            total={voucherTransactionTypes.total}
                            perPage={perPage}
                            onPageChange={(page) => visit({ page })}
                            onPerPageChange={(value) => {
                                setPerPage(value);
                                visit({ per_page: value, page: undefined });
                            }}
                        />
                    </CardContent>
                </Card>
            </div>

            <FormModal
                isOpen={isCreateOpen}
                onClose={() => setIsCreateOpen(false)}
                title="Create Voucher Transaction Type"
                onSubmit={handleSubmit}
                processing={processing}
                submitText="Create"
                errors={errors}
            >
                <EditableFields
                    data={data}
                    setData={setData}
                    errors={errors}
                    voucherCategories={voucherCategories}
                    voucherTypes={voucherTypes}
                    creating
                />
            </FormModal>

            <FormModal
                isOpen={Boolean(editingType)}
                onClose={() => setEditingType(null)}
                title="Edit Voucher Transaction Type"
                onSubmit={handleSubmit}
                processing={processing}
                submitText="Update"
                errors={errors}
            >
                {editingType && (
                    <>
                        <ReadOnlyField
                            label="Voucher Category"
                            value={editingType.voucher_category.name}
                        />
                        <ReadOnlyField
                            label="Transaction Code"
                            value={editingType.code}
                        />
                        <ReadOnlyField
                            label="Voucher Type"
                            value={formatType(editingType.voucher_type)}
                        />
                    </>
                )}
                <EditableFields
                    data={data}
                    setData={setData}
                    errors={errors}
                    voucherCategories={voucherCategories}
                    voucherTypes={voucherTypes}
                />
            </FormModal>

            <Dialog
                open={Boolean(viewingType)}
                onOpenChange={(open) => !open && setViewingType(null)}
            >
                <DialogContent className="dark:bg-gray-800">
                    <DialogHeader>
                        <DialogTitle>Voucher Transaction Type</DialogTitle>
                        <DialogDescription>
                            View transaction type master data.
                        </DialogDescription>
                    </DialogHeader>
                    {viewingType && (
                        <dl className="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                            <Detail
                                label="Category"
                                value={viewingType.voucher_category.name}
                            />
                            <Detail label="Code" value={viewingType.code} />
                            <Detail label="Name" value={viewingType.name} />
                            <Detail
                                label="Voucher Type"
                                value={formatType(viewingType.voucher_type)}
                            />
                            <Detail
                                label="Description"
                                value={viewingType.description || '-'}
                                wide
                            />
                            <Detail
                                label="Sort Order"
                                value={viewingType.sort_order.toString()}
                            />
                            <Detail
                                label="Status"
                                value={
                                    viewingType.status ? 'Active' : 'Inactive'
                                }
                            />
                            <Detail
                                label="System Data"
                                value={viewingType.is_system ? 'Yes' : 'No'}
                            />
                            <Detail
                                label="Created Date"
                                value={viewingType.created_at}
                            />
                        </dl>
                    )}
                </DialogContent>
            </Dialog>

            <DeleteModal
                isOpen={Boolean(deletingType)}
                onClose={() => setDeletingType(null)}
                onConfirm={() => {
                    if (!deletingType) return;
                    router.delete(
                        `/voucher-transaction-types/${deletingType.id}`,
                        { onSuccess: () => setDeletingType(null) },
                    );
                }}
                title="Delete Voucher Transaction Type"
                message={`Are you sure you want to delete "${deletingType?.name}"?`}
            />
            <DeleteModal
                isOpen={isBulkDeleting}
                onClose={() => setIsBulkDeleting(false)}
                onConfirm={() =>
                    router.delete(
                        '/voucher-transaction-types/bulk/delete',
                        {
                            data: { ids: selectedIds },
                            onSuccess: () => {
                                setSelectedIds([]);
                                setIsBulkDeleting(false);
                            },
                        },
                    )
                }
                title="Delete Selected Voucher Transaction Types"
                message={`Are you sure you want to delete ${selectedIds.length} selected transaction types?`}
            />
        </AppLayout>
    );
}

function EditableFields({
    data,
    setData,
    errors,
    voucherCategories,
    voucherTypes,
    creating = false,
}: {
    data: {
        voucher_category_id: string;
        voucher_type: string;
        name: string;
        description: string;
        sort_order: number;
        status: boolean;
    };
    setData: (key: keyof typeof data, value: string | number | boolean) => void;
    errors: Partial<Record<keyof typeof data, string>>;
    voucherCategories: VoucherCategory[];
    voucherTypes: string[];
    creating?: boolean;
}) {
    return (
        <>
            {creating && (
                <>
                    <div>
                        <Label>Voucher Category</Label>
                        <Select
                            value={data.voucher_category_id}
                            onValueChange={(value) =>
                                setData('voucher_category_id', value)
                            }
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Choose category" />
                            </SelectTrigger>
                            <SelectContent>
                                {voucherCategories.map((item) => (
                                    <SelectItem
                                        key={item.id}
                                        value={item.id.toString()}
                                    >
                                        {item.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError
                            message={errors.voucher_category_id}
                        />
                    </div>
                    <div>
                        <Label>Voucher Type</Label>
                        <Select
                            value={data.voucher_type}
                            onValueChange={(value) =>
                                setData('voucher_type', value)
                            }
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {voucherTypes.map((item) => (
                                    <SelectItem key={item} value={item}>
                                        {formatType(item)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.voucher_type} />
                    </div>
                </>
            )}
            <div>
                <Label>Transaction Name</Label>
                <Input
                    value={data.name}
                    maxLength={150}
                    required
                    onChange={(event) =>
                        setData('name', event.target.value)
                    }
                />
                <InputError message={errors.name} />
            </div>
            <div>
                <Label>Description</Label>
                <textarea
                    value={data.description}
                    maxLength={2000}
                    rows={3}
                    onChange={(event) =>
                        setData('description', event.target.value)
                    }
                    className="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:border-gray-600 dark:bg-gray-700"
                />
                <InputError message={errors.description} />
            </div>
            <div>
                <Label>Sort Order</Label>
                <Input
                    type="number"
                    min="0"
                    max="65535"
                    value={data.sort_order}
                    required
                    onChange={(event) =>
                        setData(
                            'sort_order',
                            Number(event.target.value || 0),
                        )
                    }
                />
                <InputError message={errors.sort_order} />
            </div>
            <div>
                <Label>Status</Label>
                <Select
                    value={data.status ? '1' : '0'}
                    onValueChange={(value) =>
                        setData('status', value === '1')
                    }
                >
                    <SelectTrigger>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="1">Active</SelectItem>
                        <SelectItem value="0">Inactive</SelectItem>
                    </SelectContent>
                </Select>
                <InputError message={errors.status} />
            </div>
        </>
    );
}

function FilterInput({
    label,
    value,
    onChange,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
}) {
    return (
        <div>
            <Label>{label}</Label>
            <Input
                value={value}
                placeholder="Search code or name..."
                onChange={(event) => onChange(event.target.value)}
            />
        </div>
    );
}

function FilterSelect({
    label,
    value,
    onChange,
    options,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    options: Array<{ value: string; label: string }>;
}) {
    return (
        <div>
            <Label>{label}</Label>
            <Select value={value} onValueChange={onChange}>
                <SelectTrigger>
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All</SelectItem>
                    {options.map((item) => (
                        <SelectItem key={item.value} value={item.value}>
                            {item.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
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
            className="cursor-pointer p-4 text-left text-[13px] font-medium"
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

function ReadOnlyField({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <Label>{label}</Label>
            <Input value={value} readOnly />
        </div>
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

function IconButton({
    title,
    onClick,
    destructive = false,
    children,
}: {
    title: string;
    onClick: () => void;
    destructive?: boolean;
    children: React.ReactNode;
}) {
    return (
        <Button
            variant="ghost"
            size="sm"
            title={title}
            aria-label={title}
            onClick={onClick}
            className={destructive ? 'text-red-600 hover:text-red-800' : ''}
        >
            {children}
        </Button>
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
            <dt className="text-xs font-medium text-gray-500">{label}</dt>
            <dd className="mt-1 text-gray-900 dark:text-gray-100">{value}</dd>
        </div>
    );
}

function formatType(value: string) {
    return value
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}
