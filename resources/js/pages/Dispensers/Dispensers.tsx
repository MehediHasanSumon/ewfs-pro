import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DeleteModal } from '@/components/ui/delete-modal';
import { FormModal } from '@/components/ui/form-modal';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Pagination } from '@/components/ui/pagination';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { ChevronDown, ChevronUp, Edit, FileText, Filter, Fuel, Plus, Trash2, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { usePermission } from '@/hooks/usePermission';

interface Dispenser {
    id: number;
    dispenser_name: string;
    product_id: number;
    product_name: string;
    dispenser_item: number;
    item_rate?: number;
    start_reading?: number;
    end_reading?: number;
    status: boolean;
    created_at: string;
}

interface Product {
    id: number;
    product_name: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
    {
        title: 'Dispensers',
        href: '/dispensers',
    },
];

interface DispensersProps {
    dispensers: {
        data: Dispenser[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
    };
    products: Product[];
    filters: {
        search?: string;
        status?: string;
        product_id?: string;
        start_date?: string;
        end_date?: string;
        sort_by?: string;
        sort_order?: string;
        per_page?: number;
    };
}

export default function Dispensers({ dispensers, products, filters }: DispensersProps) {
    const { can } = usePermission();
    const canFilter = can('can-dispenser-filter');
    const canDownload = can('can-dispenser-download');
    const hasActionPermission = can('update-dispenser') || can('delete-dispenser');

    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editingDispenser, setEditingDispenser] = useState<Dispenser | null>(null);
    const [deletingDispenser, setDeletingDispenser] = useState<Dispenser | null>(null);
    const [selectedDispensers, setSelectedDispensers] = useState<number[]>([]);
    const [isBulkDeleting, setIsBulkDeleting] = useState(false);
    const [search, setSearch] = useState(filters?.search || '');
    const [status, setStatus] = useState(filters?.status || 'all');
    const [productId, setProductId] = useState(filters?.product_id || '');
    const [startDate, setStartDate] = useState(filters?.start_date || '');
    const [endDate, setEndDate] = useState(filters?.end_date || '');
    const [sortBy, setSortBy] = useState(filters?.sort_by || 'dispenser_name');
    const [sortOrder, setSortOrder] = useState(filters?.sort_order || 'asc');
    const [perPage, setPerPage] = useState(filters?.per_page || 10);

    const { data, setData, post, put, processing, errors, reset } = useForm({
        dispenser_name: '',
        product_id: '',
        opening_reading: '',
        status: true
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingDispenser) {
            put(`/dispensers/${editingDispenser.id}`, {
                onSuccess: () => {
                    setEditingDispenser(null);
                    reset();
                }
            });
        } else {
            post('/dispensers', {
                onSuccess: () => {
                    setIsCreateOpen(false);
                    reset();
                }
            });
        }
    };

    const handleEdit = (dispenser: Dispenser) => {
        setEditingDispenser(dispenser);
        setData({
            dispenser_name: dispenser.dispenser_name,
            product_id: dispenser.product_id.toString(),
            opening_reading: dispenser.end_reading?.toString() || '',
            status: dispenser.status
        });
    };

    const handleDelete = (dispenser: Dispenser) => {
        setDeletingDispenser(dispenser);
    };

    const confirmDelete = () => {
        if (deletingDispenser) {
            router.delete(`/dispensers/${deletingDispenser.id}`, {
                onSuccess: () => setDeletingDispenser(null)
            });
        }
    };

    const handleBulkDelete = () => {
        setIsBulkDeleting(true);
    };

    const confirmBulkDelete = () => {
        router.delete('/dispensers/bulk/delete', {
            data: { ids: selectedDispensers },
            onSuccess: () => {
                setSelectedDispensers([]);
                setIsBulkDeleting(false);
            }
        });
    };

    const applyFilters = () => {
        router.get('/dispensers', {
            search: search || undefined,
            status: status === 'all' ? undefined : status,
            product_id: productId || undefined,
            start_date: startDate || undefined,
            end_date: endDate || undefined,
            sort_by: sortBy,
            sort_order: sortOrder,
            per_page: perPage,
        }, { preserveState: true });
    };

    const clearFilters = () => {
        setSearch('');
        setStatus('all');
        setProductId('');
        setStartDate('');
        setEndDate('');
        router.get('/dispensers', {
            sort_by: sortBy,
            sort_order: sortOrder,
            per_page: perPage,
        }, { preserveState: true });
    };

    const handleSort = (column: string) => {
        const newOrder = sortBy === column && sortOrder === 'asc' ? 'desc' : 'asc';
        setSortBy(column);
        setSortOrder(newOrder);
        router.get('/dispensers', {
            search: search || undefined,
            status: status === 'all' ? undefined : status,
            product_id: productId || undefined,
            start_date: startDate || undefined,
            end_date: endDate || undefined,
            sort_by: column,
            sort_order: newOrder,
            per_page: perPage,
        }, { preserveState: true });
    };

    const handlePageChange = (page: number) => {
        router.get('/dispensers', {
            search: search || undefined,
            status: status === 'all' ? undefined : status,
            product_id: productId || undefined,
            start_date: startDate || undefined,
            end_date: endDate || undefined,
            sort_by: sortBy,
            sort_order: sortOrder,
            per_page: perPage,
            page,
        }, { preserveState: true });
    };

    const toggleSelectAll = () => {
        if (selectedDispensers.length === dispensers.data.length) {
            setSelectedDispensers([]);
        } else {
            setSelectedDispensers(dispensers.data.map(dispenser => dispenser.id));
        }
    };

    const toggleSelectDispenser = (dispenserId: number) => {
        if (selectedDispensers.includes(dispenserId)) {
            setSelectedDispensers(selectedDispensers.filter(id => id !== dispenserId));
        } else {
            setSelectedDispensers([...selectedDispensers, dispenserId]);
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
            <Head title="Dispensers" />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold dark:text-white">Dispensers</h1>
                        <p className="text-gray-600 dark:text-gray-400">Manage fuel dispensers</p>
                    </div>
                    <div className="flex gap-2">
                        {selectedDispensers.length > 0 && can('delete-dispenser') && (
                            <Button
                                variant="destructive"
                                onClick={handleBulkDelete}
                            >
                                <Trash2 className="mr-2 h-4 w-4" />
                                Delete Selected ({selectedDispensers.length})
                            </Button>
                        )}
                        {canDownload && (
                            <Button
                                variant="success"
                                onClick={() => {
                                    const params = new URLSearchParams();
                                    if (search) params.append('search', search);
                                    if (status !== 'all') params.append('status', status);
                                    if (productId) params.append('product_id', productId);
                                    if (startDate) params.append('start_date', startDate);
                                    if (endDate) params.append('end_date', endDate);
                                    if (sortBy) params.append('sort_by', sortBy);
                                    if (sortOrder) params.append('sort_order', sortOrder);
                                    window.location.href = `/dispensers/download-pdf?${params.toString()}`;
                                }}
                            >
                                <FileText className="mr-2 h-4 w-4" />
                                Download
                            </Button>
                        )}
                        {can('create-dispenser') && (
                            <Button onClick={() => setIsCreateOpen(true)}>
                                <Plus className="mr-2 h-4 w-4" />
                                Add Dispenser
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
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-6">
                                <div>
                                    <Label className="dark:text-gray-200">Search</Label>
                                    <Input
                                        placeholder="Search dispensers..."
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        className="dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    />
                                </div>
                                <div>
                                    <Label className="dark:text-gray-200">Status</Label>
                                    <Select value={status} onValueChange={setStatus}>
                                        <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                            <SelectValue placeholder="All status" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">All status</SelectItem>
                                            <SelectItem value="true">Active</SelectItem>
                                            <SelectItem value="false">Disabled</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div>
                                    <Label className="dark:text-gray-200">Product</Label>
                                    <Select value={productId} onValueChange={setProductId}>
                                        <SelectTrigger className="dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                            <SelectValue placeholder="All products" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {products.map((product) => (
                                                <SelectItem key={product.id} value={product.id.toString()}>
                                                    {product.product_name}
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
                                        <th className="p-4 text-left font-medium dark:text-gray-300">
                                            <input
                                                type="checkbox"
                                                checked={selectedDispensers.length === dispensers.data.length && dispensers.data.length > 0}
                                                onChange={toggleSelectAll}
                                                className="rounded border-gray-300 dark:border-gray-600"
                                            />
                                        </th>
                                        <th className="cursor-pointer p-4 text-left text-[13px] font-medium dark:text-gray-300" onClick={() => handleSort('dispenser_name')}>
                                            <div className="flex items-center gap-1">
                                                Dispenser Name
                                                {sortBy === 'dispenser_name' && (sortOrder === 'asc' ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />)}
                                            </div>
                                        </th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Product ID</th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Dispenser Items</th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Dispenser Items Rate</th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Start Reading</th>
                                        <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Status</th>
                                        {hasActionPermission && (
                                            <th className="p-4 text-left text-[13px] font-medium dark:text-gray-300">Actions</th>
                                        )}
                                    </tr>
                                </thead>
                                <tbody>
                                    {dispensers.data.length > 0 ? dispensers.data.map((dispenser) => (
                                        <tr key={dispenser.id} className="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">
                                            <td className="p-4">
                                                <input
                                                    type="checkbox"
                                                    checked={selectedDispensers.includes(dispenser.id)}
                                                    onChange={() => toggleSelectDispenser(dispenser.id)}
                                                    className="rounded border-gray-300 dark:border-gray-600"
                                                />
                                            </td>
                                            <td className="p-4 text-[13px] dark:text-white">{dispenser.dispenser_name}</td>
                                            <td className="p-4 text-[13px] dark:text-gray-300">{dispenser.product_name}</td>
                                            <td className="p-4 text-[13px] dark:text-gray-300">{dispenser.dispenser_item}</td>
                                            <td className="p-4 text-[13px] dark:text-gray-300">{dispenser.item_rate || '-'}</td>
                                            <td className="p-4 text-[13px] dark:text-gray-300">{dispenser.end_reading || '-'}</td>
                                            <td className="p-4">
                                                <span className={`px-2 py-1 rounded text-xs ${dispenser.status ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'}`}>
                                                    {dispenser.status ? 'Active' : 'Disabled'}
                                                </span>
                                            </td>
                                            {hasActionPermission && (
                                                <td className="p-4">
                                                    <div className="flex gap-2">
                                                        {can('update-dispenser') && (
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                onClick={() => handleEdit(dispenser)}
                                                                className="text-indigo-600 hover:text-indigo-800"
                                                            >
                                                                <Edit className="h-4 w-4" />
                                                            </Button>
                                                        )}
                                                        {can('delete-dispenser') && (
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                onClick={() => handleDelete(dispenser)}
                                                                className="text-red-600 hover:text-red-800"
                                                            >
                                                                <Trash2 className="h-4 w-4" />
                                                            </Button>
                                                        )}
                                                    </div>
                                                </td>
                                            )}
                                        </tr>
                                    )) : (
                                        <tr>
                                            <td colSpan={hasActionPermission ? 8 : 7} className="p-8 text-center text-gray-500 dark:text-gray-400">
                                                <Fuel className="mx-auto mb-4 h-12 w-12 text-gray-400" />
                                                No dispensers found
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <Pagination
                            currentPage={dispensers.current_page}
                            lastPage={dispensers.last_page}
                            from={dispensers.from}
                            to={dispensers.to}
                            total={dispensers.total}
                            perPage={perPage}
                            onPageChange={handlePageChange}
                            onPerPageChange={(newPerPage) => {
                                setPerPage(newPerPage);
                                router.get('/dispensers', {
                                    search: search || undefined,
                                    status: status === 'all' ? undefined : status,
                                    product_id: productId || undefined,
                                    start_date: startDate || undefined,
                                    end_date: endDate || undefined,
                                    sort_by: sortBy,
                                    sort_order: sortOrder,
                                    per_page: newPerPage,
                                }, { preserveState: true });
                            }}
                        />
                    </CardContent>
                </Card>

                <FormModal
                    isOpen={isCreateOpen}
                    onClose={() => setIsCreateOpen(false)}
                    title="Create Dispenser"
                    onSubmit={handleSubmit}
                    processing={processing}
                    submitText="Create"
                >
                    <div>
                        <Label htmlFor="dispenser_name" className="dark:text-gray-200">Dispenser Name</Label>
                        <Input
                            id="dispenser_name"
                            value={data.dispenser_name}
                            onChange={(e) => setData('dispenser_name', e.target.value)}
                            className="dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                            placeholder="e.g., Dispenser 1"
                        />
                        {errors.dispenser_name && <span className="text-red-500 text-sm">{errors.dispenser_name}</span>}
                    </div>
                    <div>
                        <Label htmlFor="product_id" className="dark:text-gray-200">Product</Label>
                        <Select value={data.product_id} onValueChange={(value) => setData('product_id', value)}>
                            <SelectTrigger className="dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                <SelectValue placeholder="Select product" />
                            </SelectTrigger>
                            <SelectContent>
                                {products.map((product) => (
                                    <SelectItem key={product.id} value={product.id.toString()}>
                                        {product.product_name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.product_id && <span className="text-red-500 text-sm">{errors.product_id}</span>}
                    </div>
                    <div>
                        <Label htmlFor="opening_reading" className="dark:text-gray-200">Opening Reading</Label>
                        <Input
                            id="opening_reading"
                            type="number"
                            step="0.01"
                            value={data.opening_reading}
                            onChange={(e) => setData('opening_reading', e.target.value)}
                            className="dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                            placeholder="0.00"
                        />
                        {errors.opening_reading && <span className="text-red-500 text-sm">{errors.opening_reading}</span>}
                    </div>
                    <div>
                        <Label htmlFor="status" className="dark:text-gray-200">Status</Label>
                        <Select value={data.status ? '1' : '0'} onValueChange={(value) => setData('status', value === '1')}>
                            <SelectTrigger className="dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                <SelectValue placeholder="Select status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="1">Active</SelectItem>
                                <SelectItem value="0">Disabled</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </FormModal>

                <FormModal
                    isOpen={!!editingDispenser}
                    onClose={() => setEditingDispenser(null)}
                    title="Edit Dispenser"
                    onSubmit={handleSubmit}
                    processing={processing}
                    submitText="Update"
                >
                    <div>
                        <Label htmlFor="edit-dispenser_name" className="dark:text-gray-200">Dispenser Name</Label>
                        <Input
                            id="edit-dispenser_name"
                            value={data.dispenser_name}
                            onChange={(e) => setData('dispenser_name', e.target.value)}
                            className="dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                        />
                        {errors.dispenser_name && <span className="text-red-500 text-sm">{errors.dispenser_name}</span>}
                    </div>
                    <div>
                        <Label htmlFor="edit-product_id" className="dark:text-gray-200">Product</Label>
                        <Select value={data.product_id} onValueChange={(value) => setData('product_id', value)}>
                            <SelectTrigger className="dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                <SelectValue placeholder="Select product" />
                            </SelectTrigger>
                            <SelectContent>
                                {products.map((product) => (
                                    <SelectItem key={product.id} value={product.id.toString()}>
                                        {product.product_name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.product_id && <span className="text-red-500 text-sm">{errors.product_id}</span>}
                    </div>
                    <div>
                        <Label htmlFor="edit-opening_reading" className="dark:text-gray-200">Opening Reading</Label>
                        <Input
                            id="edit-opening_reading"
                            type="number"
                            step="0.01"
                            value={data.opening_reading}
                            onChange={(e) => setData('opening_reading', e.target.value)}
                            className="dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                            placeholder="0.00"
                        />
                        {errors.opening_reading && <span className="text-red-500 text-sm">{errors.opening_reading}</span>}
                    </div>
                    <div>
                        <Label htmlFor="edit-status" className="dark:text-gray-200">Status</Label>
                        <Select value={data.status ? '1' : '0'} onValueChange={(value) => setData('status', value === '1')}>
                            <SelectTrigger className="dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                <SelectValue placeholder="Select status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="1">Active</SelectItem>
                                <SelectItem value="0">Disabled</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </FormModal>

                <DeleteModal
                    isOpen={!!deletingDispenser}
                    onClose={() => setDeletingDispenser(null)}
                    onConfirm={confirmDelete}
                    title="Delete Dispenser"
                    message={`Are you sure you want to delete the dispenser "${deletingDispenser?.dispenser_name}"? This action cannot be undone.`}
                />

                <DeleteModal
                    isOpen={isBulkDeleting}
                    onClose={() => setIsBulkDeleting(false)}
                    onConfirm={confirmBulkDelete}
                    title="Delete Selected Dispensers"
                    message={`Are you sure you want to delete ${selectedDispensers.length} selected dispensers? This action cannot be undone.`}
                />
            </div>
        </AppLayout>
    );
}
